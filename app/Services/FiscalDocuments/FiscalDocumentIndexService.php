<?php

namespace App\Services\FiscalDocuments;

use App\Models\FuelFilling;
use App\Models\FuelReceipt;
use App\Models\StockMovement;
use App\Models\TireEntry;
use App\Models\User;
use App\Services\Reports\ReportContextService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class FiscalDocumentIndexService
{
    public function __construct(
        private readonly ReportContextService $reportContextService
    ) {
    }

    public function build(array $filters = []): array
    {
        $context = $this->reportContextService->resolve();

        if (! $context) {
            return [
                'context' => null,
                'documents' => collect(),
                'summary' => $this->emptySummary(),
                'filters' => $this->normalizeFilters($filters),
                'period_error' => 'Selecione uma divisão e uma unidade ativa para consultar notas fiscais.',
            ];
        }

        $this->authorize($context['user']);

        $filters = $this->normalizeFilters($filters);
        $periodError = $this->periodError($filters);

        if ($periodError) {
            return [
                'context' => $context,
                'documents' => collect(),
                'summary' => $this->emptySummary(),
                'filters' => $filters,
                'period_error' => $periodError,
            ];
        }

        $documents = collect()
            ->merge($this->fuelReceipts($context, $filters))
            ->merge($this->externalFuelFillings($context, $filters))
            ->merge($this->stockMovements($context, $filters))
            ->merge($this->tireEntries($context, $filters))
            ->when($filters['search'] !== '', function (Collection $collection) use ($filters) {
                $search = mb_strtolower($filters['search']);

                return $collection->filter(
                    fn (array $document) => str_contains($document['search_text'], $search)
                );
            })
            ->when($filters['module'] !== '', function (Collection $collection) use ($filters) {
                return $collection->where('module', $filters['module']);
            })
            ->when($filters['type'] !== '', function (Collection $collection) use ($filters) {
                return $collection->where('type', $filters['type']);
            })
            ->sortByDesc(fn (array $document) => optional($document['date'])->timestamp ?? 0)
            ->values();

        return [
            'context' => $context,
            'documents' => $documents,
            'summary' => $this->summary($documents),
            'filters' => $filters,
            'period_error' => null,
            'modules' => $this->modules(),
            'types' => $this->types(),
        ];
    }

    private function authorize(User $user): void
    {
        $allowed = ((int) $user->id === 1)
            || userHasProfile('admin')
            || userHasProfile('manager');

        abort_unless($allowed, 403);
    }

    private function normalizeFilters(array $filters): array
    {
        return [
            'start_date' => $filters['start_date'] ?? now()->subDays(30)->toDateString(),
            'end_date' => $filters['end_date'] ?? now()->toDateString(),
            'module' => $filters['module'] ?? '',
            'type' => $filters['type'] ?? '',
            'search' => trim((string) ($filters['search'] ?? '')),
            'include_without_document' => filter_var(
                $filters['include_without_document'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            ),
        ];
    }

    private function periodError(array $filters): ?string
    {
        if (! $filters['start_date'] || ! $filters['end_date']) {
            return 'Informe a data inicial e final para consultar documentos fiscais.';
        }

        if (Carbon::parse($filters['start_date'])->gt(Carbon::parse($filters['end_date']))) {
            return 'A data inicial não pode ser maior que a data final.';
        }

        return null;
    }

    private function fuelReceipts(array $context, array $filters): Collection
    {
        return FuelReceipt::query()
            ->with(['tank.product', 'product', 'responsible', 'location', 'division'])
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->whereNull('cancelled_at')
            ->whereBetween('received_at', $this->period($filters))
            ->when(! $filters['include_without_document'], fn ($query) => $this->whereHasDocument($query, 'invoice_number'))
            ->get()
            ->map(function (FuelReceipt $receipt) use ($context) {
                $productName = $receipt->product?->name ?? $receipt->tank?->product?->name ?? 'Produto não informado';
                $tankName = $receipt->tank?->name ?? 'Tanque não informado';
                $linked = trim($tankName . ' · ' . $productName);

                return $this->documentRow(
                    context: $context,
                    module: 'fuel',
                    type: 'fuel_receipt',
                    date: $receipt->received_at,
                    documentNumber: $receipt->invoice_number,
                    supplierName: $receipt->supplier_name,
                    amount: $receipt->total_cost,
                    linkedRecord: $linked . ' · ' . $this->liters($receipt->quantity_liters),
                    responsibleName: $receipt->responsible?->name,
                    locationName: $receipt->location?->name ?? $context['location']->name,
                    originUrl: route('fuel.tanks.index'),
                    details: [
                        $this->field('Tanque', $tankName),
                        $this->field('Produto', $productName),
                        $this->field('Litros', $this->liters($receipt->quantity_liters)),
                        $this->field('Custo unitário', $this->money($receipt->unit_cost, 4)),
                        $this->field('Custo total', $this->money($receipt->total_cost)),
                        $this->field('Fornecedor', $receipt->supplier_name),
                        $this->field('Documento', $receipt->invoice_number),
                        $this->field('Responsável', $receipt->responsible?->name),
                        $this->field('Data', $this->dateTimeLabel($receipt->received_at)),
                    ],
                    technical: $this->technicalFields($context, 'fuel_receipts', FuelReceipt::class, $receipt->id),
                    notes: $receipt->notes
                );
            });
    }

    private function externalFuelFillings(array $context, array $filters): Collection
    {
        return FuelFilling::query()
            ->with(['vehicle', 'product', 'tank.product', 'driver', 'responsible', 'location', 'division'])
            ->where('tenant_id', $context['tenant_id'])
            ->where('division_id', $context['division']->id)
            ->where('location_id', $context['location']->id)
            ->where('source', FuelFilling::SOURCE_EXTERNAL_STATION)
            ->whereNull('cancelled_at')
            ->whereBetween('filled_at', $this->period($filters))
            ->when(! $filters['include_without_document'], fn ($query) => $this->whereHasDocument($query, 'document_number'))
            ->get()
            ->map(function (FuelFilling $filling) use ($context) {
                $vehicle = trim(($filling->vehicle?->name ?? 'Veículo não informado') . ' / ' . ($filling->vehicle?->plate ?? 'Sem placa'));
                $product = $filling->product?->name ?? $filling->tank?->product?->name ?? 'Produto não informado';

                return $this->documentRow(
                    context: $context,
                    module: 'fuel',
                    type: 'fuel_external_filling',
                    date: $filling->filled_at,
                    documentNumber: $filling->document_number,
                    supplierName: $filling->supplier_name,
                    amount: $filling->total_cost,
                    linkedRecord: $vehicle . ' · ' . $product . ' · ' . $this->liters($filling->quantity_liters),
                    responsibleName: $filling->responsible?->name,
                    locationName: $filling->location?->name ?? $context['location']->name,
                    originUrl: route('fuel.tanks.index'),
                    details: [
                        $this->field('Veículo', $vehicle),
                        $this->field('Motorista/Condutor', $filling->driver?->name),
                        $this->field('Produto', $product),
                        $this->field('Litros', $this->liters($filling->quantity_liters)),
                        $this->field('Custo unitário', $this->money($filling->unit_cost, 4)),
                        $this->field('Custo total', $this->money($filling->total_cost)),
                        $this->field('Fornecedor/Posto', $filling->supplier_name),
                        $this->field('Documento/Cupom', $filling->document_number),
                        $this->field('KM', $this->km($filling->vehicle_km)),
                        $this->field('Horímetro', $this->hours($filling->vehicle_hours)),
                        $this->field('Responsável', $filling->responsible?->name),
                        $this->field('Data', $this->dateTimeLabel($filling->filled_at)),
                    ],
                    technical: $this->technicalFields($context, 'fuel_fillings', FuelFilling::class, $filling->id),
                    notes: $filling->notes
                );
            });
    }

    private function stockMovements(array $context, array $filters): Collection
    {
        return StockMovement::query()
            ->with(['stockItem.category', 'location'])
            ->where('tenant_id', $context['tenant_id'])
            ->where('location_id', $context['location']->id)
            ->whereNull('cancelled_at')
            ->whereNull('reversed_from_movement_id')
            ->whereNull('reversal_movement_id')
            ->whereBetween('moved_at', $this->period($filters))
            ->when(! $filters['include_without_document'], fn ($query) => $this->whereHasDocument($query, 'invoice_number'))
            ->get()
            ->map(function (StockMovement $movement) use ($context) {
                $item = $movement->stockItem?->name ?? 'Item não informado';
                $category = $movement->stockItem?->category?->name;
                $type = $movement->movement_type === 'in' ? 'stock_entry' : 'stock_output';
                $quantity = number_format((float) $movement->quantity, 2, ',', '.');
                $unit = $movement->stockItem?->unit ?: 'un.';
                $quantityLabel = "{$quantity} {$unit}";

                return $this->documentRow(
                    context: $context,
                    module: 'stock',
                    type: $type,
                    date: $movement->moved_at ?? $movement->created_at,
                    documentNumber: $movement->invoice_number,
                    supplierName: $movement->supplier_name,
                    amount: $movement->total_cost,
                    linkedRecord: trim($item . ($category ? " · {$category}" : '') . " · {$quantityLabel}"),
                    responsibleName: null,
                    locationName: $movement->location?->name ?? $context['location']->name,
                    originUrl: route('stock.index'),
                    details: [
                        $this->field('Item/Produto', $item),
                        $this->field('Categoria', $category),
                        $this->field('Quantidade', $quantityLabel),
                        $this->field('Unidade de medida', $unit),
                        $this->field('Custo unitário', $this->money($movement->unit_cost, 4)),
                        $this->field('Custo total', $this->money($movement->total_cost)),
                        $this->field('Fornecedor', $movement->supplier_name),
                        $this->field('Nota/Documento', $movement->invoice_number),
                        $this->field('Responsável', null),
                        $this->field('Data', $this->dateTimeLabel($movement->moved_at ?? $movement->created_at)),
                    ],
                    technical: $this->technicalFields($context, 'stock_movements', StockMovement::class, $movement->id),
                    notes: $movement->description
                );
            });
    }

    private function tireEntries(array $context, array $filters): Collection
    {
        return TireEntry::query()
            ->with(['creator', 'location'])
            ->where('tenant_id', $context['tenant_id'])
            ->where('location_id', $context['location']->id)
            ->whereNull('cancelled_at')
            ->whereBetween('entry_date', [
                $filters['start_date'],
                $filters['end_date'],
            ])
            ->when(! $filters['include_without_document'], fn ($query) => $this->whereHasDocument($query, 'invoice_number'))
            ->get()
            ->map(function (TireEntry $entry) use ($context) {
                $parts = array_filter([
                    $entry->brand,
                    $entry->model,
                    $entry->size,
                ]);
                $tireDescription = count($parts) ? implode(' · ', $parts) : 'Pneu sem marca/modelo/medida informados';

                return $this->documentRow(
                    context: $context,
                    module: 'tires',
                    type: 'tire_entry',
                    date: $entry->entry_date,
                    documentNumber: $entry->invoice_number,
                    supplierName: $entry->supplier_name,
                    amount: $entry->total_cost,
                    linkedRecord: 'Entrada de ' . (int) $entry->quantity . ' pneu(s)' . (count($parts) ? ' · ' . $tireDescription : ''),
                    responsibleName: $entry->creator?->name,
                    locationName: $entry->location?->name ?? $context['location']->name,
                    originUrl: route('workshop.tires.index'),
                    details: [
                        $this->field('Entrada', 'Entrada de pneus'),
                        $this->field('Quantidade de pneus', (int) $entry->quantity),
                        $this->field('Marca/Modelo/Medida', $tireDescription),
                        $this->field('Fornecedor', $entry->supplier_name),
                        $this->field('Nota/Documento', $entry->invoice_number),
                        $this->field('Valor', $this->money($entry->total_cost)),
                        $this->field('Responsável', $entry->creator?->name),
                        $this->field('Data', $this->dateTimeLabel($entry->entry_date)),
                    ],
                    technical: $this->technicalFields($context, 'tire_entries', TireEntry::class, $entry->id),
                    notes: $entry->notes
                );
            });
    }

    private function documentRow(
        array $context,
        string $module,
        string $type,
        mixed $date,
        mixed $documentNumber,
        mixed $supplierName,
        mixed $amount,
        string $linkedRecord,
        ?string $responsibleName,
        string $locationName,
        string $originUrl,
        array $details = [],
        array $technical = [],
        mixed $notes = null
    ): array {
        $documentNumber = trim((string) ($documentNumber ?? ''));
        $supplierName = trim((string) ($supplierName ?? ''));
        $date = $date ? Carbon::parse($date) : null;

        $row = [
            'module' => $module,
            'module_label' => $this->modules()[$module] ?? ucfirst($module),
            'type' => $type,
            'type_label' => $this->types()[$type] ?? ucfirst(str_replace('_', ' ', $type)),
            'date' => $date,
            'date_label' => $this->dateTimeLabel($date),
            'document_number' => $documentNumber !== '' ? $documentNumber : 'Não informado',
            'has_document' => $documentNumber !== '',
            'supplier_name' => $supplierName !== '' ? $supplierName : 'Não informado',
            'amount' => $amount !== null ? (float) $amount : null,
            'amount_label' => $this->money($amount),
            'linked_record' => $linkedRecord,
            'responsible_name' => $responsibleName ?: 'Não informado',
            'location_name' => $locationName,
            'division_name' => $context['division']->name ?? 'Não informado',
            'origin_url' => $originUrl,
            'details' => collect($details)->filter(fn (array $field) => $field['value'] !== '')->values()->all(),
            'technical_fields' => collect($technical)->filter(fn (array $field) => $field['value'] !== '')->values()->all(),
            'notes' => $this->displayValue($notes),
        ];

        $row['search_text'] = mb_strtolower(implode(' ', [
            $row['module_label'],
            $row['type_label'],
            $row['document_number'],
            $row['supplier_name'],
            $row['linked_record'],
            $row['responsible_name'],
            $row['location_name'],
            $row['division_name'],
        ]));

        return $row;
    }

    private function period(array $filters): array
    {
        return [
            Carbon::parse($filters['start_date'])->startOfDay(),
            Carbon::parse($filters['end_date'])->endOfDay(),
        ];
    }

    private function whereHasDocument($query, string $column)
    {
        return $query
            ->whereNotNull($column)
            ->where($column, '<>', '');
    }

    private function summary(Collection $documents): array
    {
        return [
            'total_documents' => $documents->count(),
            'total_amount' => (float) $documents->sum(fn (array $document) => $document['amount'] ?? 0),
            'modules_count' => $documents->pluck('module')->unique()->count(),
            'without_amount' => $documents->filter(fn (array $document) => $document['amount'] === null)->count(),
            'without_document' => $documents->where('has_document', false)->count(),
        ];
    }

    private function emptySummary(): array
    {
        return [
            'total_documents' => 0,
            'total_amount' => 0.0,
            'modules_count' => 0,
            'without_amount' => 0,
            'without_document' => 0,
        ];
    }

    public function modules(): array
    {
        return [
            'fuel' => 'Abastecimentos',
            'stock' => 'Estoque',
            'tires' => 'Pneus',
        ];
    }

    public function types(): array
    {
        return [
            'fuel_receipt' => 'Recebimento de combustível',
            'fuel_external_filling' => 'Abastecimento externo',
            'stock_entry' => 'Entrada de estoque',
            'stock_output' => 'Saída de estoque',
            'tire_entry' => 'Entrada de pneus',
        ];
    }

    private function technicalFields(array $context, string $table, string $model, mixed $id): array
    {
        return [
            $this->field('Origem técnica', $table),
            $this->field('Registro original', '#' . $id),
            $this->field('Modelo', class_basename($model)),
            $this->field('Tenant', $context['tenant_id'] ?? null),
            $this->field('Divisão', $context['division']->id ?? null),
            $this->field('Unidade', $context['location']->id ?? null),
        ];
    }

    private function field(string $label, mixed $value): array
    {
        return [
            'label' => $label,
            'value' => $this->displayValue($value),
        ];
    }

    private function displayValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Não informado';
        }

        return (string) $value;
    }

    private function money(mixed $value, int $decimals = 2): string
    {
        if ($value === null || $value === '') {
            return 'Não informado';
        }

        return 'R$ ' . number_format((float) $value, $decimals, ',', '.');
    }

    private function liters(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Não informado';
        }

        return number_format((float) $value, 2, ',', '.') . ' L';
    }

    private function km(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Não informado';
        }

        return number_format((float) $value, 0, ',', '.') . ' km';
    }

    private function hours(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'Não informado';
        }

        return number_format((float) $value, 2, ',', '.') . ' h';
    }

    private function dateTimeLabel(mixed $value): string
    {
        if (! $value) {
            return 'Não informado';
        }

        return Carbon::parse($value)->format('d/m/Y H:i');
    }
}