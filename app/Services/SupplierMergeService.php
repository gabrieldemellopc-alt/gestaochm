<?php

namespace App\Services;

use App\Models\Supplier;
use App\Models\SupplierAlias;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierMergeService
{
    /**
     * Explicit tenant-scoping inventory for every supplier relation.
     * Snapshots (supplier_name, supplier_document and provider_name) are never part of these updates.
     */
    private const RELATION_TABLES = [
        'maintenance_records' => ['scope' => 'tenant_column'],
        'maintenance_record_items' => ['scope' => 'maintenance_parent'],
        'stock_movements' => ['scope' => 'tenant_column'],
        'workshop_expenses' => ['scope' => 'tenant_column'],
        'fuel_receipts' => ['scope' => 'tenant_column'],
        'fuel_fillings' => ['scope' => 'tenant_column'],
        'tire_entries' => ['scope' => 'tenant_column'],
        'tire_retreads' => ['scope' => 'tenant_column'],
        'fiscal_documents' => ['scope' => 'tenant_column'],
        // Does not have tenant_id; aliases are rebuilt from the selected final alias set below.
        'supplier_aliases' => ['scope' => 'supplier_foreign_key', 'transfer' => false],
    ];

    public function __construct(private SupplierNormalizer $normalizer, private AuditLogService $audit) {}

    public function merge(Supplier $primary, int $secondaryId, array $choices, int $userId): array
    {
        return DB::transaction(function () use ($primary, $secondaryId, $choices, $userId) {
            $primary = Supplier::query()->lockForUpdate()->findOrFail($primary->id);
            $secondary = Supplier::query()->lockForUpdate()->findOrFail($secondaryId);
            if ($primary->id === $secondary->id || $primary->tenant_id !== $secondary->tenant_id) {
                throw ValidationException::withMessages(['secondary_supplier_id' => 'Selecione outro fornecedor do mesmo tenant.']);
            }

            $before = ['primary' => $this->summary($primary), 'secondary' => $this->summary($secondary)];
            $nameSource = $choices['name_source'] ?? 'primary';
            $documentSource = $choices['document_source'] ?? null;
            $finalNameSupplier = $nameSource === 'secondary' ? $secondary : $primary;
            $finalDocument = $this->finalDocument($primary, $secondary, $documentSource);

            $conflict = Supplier::forTenant($primary->tenant_id)->where('document', $finalDocument)
                ->whereNotIn('id', [$primary->id, $secondary->id])->exists();
            if ($finalDocument && $conflict) {
                throw ValidationException::withMessages(['document_source' => 'O CPF/CNPJ selecionado já pertence a outro fornecedor deste tenant.']);
            }

            $aliases = $this->finalAliases($primary, $secondary, $choices['aliases_source'] ?? 'both', $finalNameSupplier->displayName());
            $secondary->aliases()->delete();
            // Avoid a temporary tenant/document unique-key collision while transferring the chosen document.
            if ($secondary->document === $finalDocument) $secondary->update(['document' => null, 'document_type' => null]);

            $primary->update([
                'trade_name' => $finalNameSupplier->trade_name,
                'legal_name' => $finalNameSupplier->legal_name,
                'normalized_name' => $this->normalizer->normalizeName($finalNameSupplier->displayName()),
                'document' => $finalDocument,
                'document_type' => $this->normalizer->detectDocumentType($finalDocument),
            ]);
            $primary->aliases()->delete();
            foreach ($aliases as $normalized => $alias) {
                SupplierAlias::create(['supplier_id' => $primary->id, 'alias' => $alias, 'normalized_alias' => $normalized]);
            }

            $transferred = [];
            foreach (self::RELATION_TABLES as $table => $definition) {
                if (($definition['transfer'] ?? true) === false) continue;
                $query = $this->relationQuery($table, $definition['scope'], $primary->tenant_id, $secondary->id);
                $count = (clone $query)->count();
                if ($count) {
                    $query->update(['supplier_id' => $primary->id]);
                    $transferred[$table] = $count;
                }
            }
            $secondary->delete();

            $this->audit->record([
                'tenant_id' => $primary->tenant_id, 'user_id' => $userId, 'module' => 'suppliers', 'action' => 'supplier_merged',
                'auditable' => $primary, 'summary' => 'Fornecedor incorporado ao cadastro principal.',
                'before_data' => $before,
                'after_data' => ['primary' => $this->summary($primary->fresh()), 'aliases' => array_values($aliases)],
                'metadata' => ['incorporated_supplier_id' => $secondary->id, 'transferred_relations' => $transferred, 'transferred_total' => array_sum($transferred)],
            ]);

            return ['supplier' => $primary->fresh(), 'transferred' => $transferred];
        });
    }

    private function finalDocument(Supplier $primary, Supplier $secondary, ?string $source): ?string
    {
        if ($primary->document && $secondary->document && $primary->document !== $secondary->document && ! in_array($source, ['primary', 'secondary'], true)) {
            throw ValidationException::withMessages(['document_source' => 'Selecione qual CPF/CNPJ deverá permanecer.']);
        }
        return match ($source) { 'secondary' => $secondary->document, 'primary' => $primary->document, default => $primary->document ?: $secondary->document };
    }

    private function relationQuery(string $table, string $scope, int $tenantId, int $secondaryId)
    {
        $query = DB::table($table)->where('supplier_id', $secondaryId);

        return match ($scope) {
            'tenant_column' => $query->where('tenant_id', $tenantId),
            // Items inherit tenancy through their mandatory maintenance record parent.
            'maintenance_parent' => $query->whereIn('maintenance_record_id', DB::table('maintenance_records')->select('id')->where('tenant_id', $tenantId)),
            // Used only for the documented alias relation; it is rebuilt rather than bulk-transferred.
            'supplier_foreign_key' => $query,
            default => throw new \LogicException("Escopo de fornecedor desconhecido para {$table}."),
        };
    }

    private function finalAliases(Supplier $primary, Supplier $secondary, string $source, string $finalName): array
    {
        $suppliers = match ($source) { 'primary' => [$primary], 'secondary' => [$secondary], default => [$primary, $secondary] };
        $values = collect($suppliers)->flatMap(fn (Supplier $supplier) => $supplier->aliases->pluck('alias')->push($supplier->displayName()));
        // Preserve each discarded historical name for future fiscal-document recognition.
        foreach ([$primary->displayName(), $secondary->displayName()] as $name) if ($name !== $finalName) $values->push($name);
        return $values->map(fn ($alias) => trim((string) $alias))->filter()->mapWithKeys(fn ($alias) => [$this->normalizer->normalizeName($alias) => $alias])->filter(fn ($alias, $normalized) => $normalized !== '')->all();
    }

    private function summary(Supplier $supplier): array { return ['id' => $supplier->id, 'name' => $supplier->displayName(), 'document' => $supplier->document]; }
}
