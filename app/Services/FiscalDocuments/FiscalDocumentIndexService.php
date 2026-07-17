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
                $linked = trim(($receipt->tank?->name ?? 'Tanque não informado') . ' · ' . $productName);

                return $this->documentRow(
                    module: 'fuel',
                    type: 'fuel_receipt',
                    date: $receipt->received_at,
                    documentNumber: $receipt->invoice_number,
                    supplierName: $receipt->supplier_name,
                    amount: $receipt->total_cost,
                    linkedRecord: $linked . ' · ' . $this->liters($receipt->quantity_liters),
                    responsibleName: $receipt->responsible?->name,
                    locationName: $receipt->location?->name ?? $context['location']->name,
                    originUrl: route('fuel.tanks.index')
                );
            });
    }

    private function externalFuelFillings(array $context, array $filters): Collection
    {
        return FuelFilling::query()
            ->with(['vehicle', 'product', 'tank.product', 'responsible', 'location', 'division'])
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
                    module: 'fuel',
                    type: 'fuel_external_filling',
                    date: $filling->filled_at,
                    documentNumber: $filling->document_number,
                    supplierName: $filling->supplier_name,
                    amount: $filling->total_cost,
                    linkedRecord: $vehicle . ' · ' . $product . ' · ' . $this->liters($filling->quantity_liters),
                    responsibleName: $filling->responsible?->name,
                    locationName: $filling->location?->name ?? $context['location']->name,
                    originUrl: route('fuel.tanks.index')
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

                return $this->documentRow(
                    module: 'stock',
                    type: $type,
                    date: $movement->moved_at ?? $movement->created_at,
                    documentNumber: $movement->invoice_number,
                    supplierName: $movement->supplier_name,
                    amount: $movement->total_cost,
                    linkedRecord: trim($item . ($category ? " · {$category}" : '') . " · {$quantity} {$unit}"),
                    responsibleName: null,
                    locationName: $movement->location?->name ?? $context['location']->name,
                    originUrl: route('stock.index')
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

                return $this->documentRow(
                    module: 'tires',
                    type: 'tire_entry',
                    date: $entry->entry_date,
                    documentNumber: $entry->invoice_number,
                    supplierName: $entry->supplier_name,
                    amount: $entry->total_cost,
                    linkedRecord: 'Entrada de ' . (int) $entry->quantity . ' pneu(s)' . (count($parts) ? ' · ' . implode(' · ', $parts) : ''),
                    responsibleName: $entry->creator?->name,
                    locationName: $entry->location?->name ?? $context['location']->name,
                    originUrl: route('workshop.tires.index')
                );
            });
    }

    private function documentRow(
        string $module,
        string $type,
        mixed $date,
        mixed $documentNumber,
        mixed $supplierName,
        mixed $amount,
        string $linkedRecord,
        ?string $responsibleName,
        string $locationName,
        string $originUrl
    ): array {
        $documentNumber = trim((string) ($documentNumber ?? ''));
        $supplierName = trim((string) ($supplierName ?? ''));

        $row = [
            'module' => $module,
            'module_label' => $this->modules()[$module] ?? ucfirst($module),
            'type' => $type,
            'type_label' => $this->types()[$type] ?? ucfirst(str_replace('_', ' ', $type)),
            'date' => $date ? Carbon::parse($date) : null,
            'document_number' => $documentNumber !== '' ? $documentNumber : 'Não informado',
            'has_document' => $documentNumber !== '',
            'supplier_name' => $supplierName !== '' ? $supplierName : 'Não informado',
            'amount' => $amount !== null ? (float) $amount : null,
            'linked_record' => $linkedRecord,
            'responsible_name' => $responsibleName ?: 'Não informado',
            'location_name' => $locationName,
            'origin_url' => $originUrl,
        ];

        $row['search_text'] = mb_strtolower(implode(' ', [
            $row['module_label'],
            $row['type_label'],
            $row['document_number'],
            $row['supplier_name'],
            $row['linked_record'],
            $row['responsible_name'],
            $row['location_name'],
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

    private function liters(mixed $value): string
    {
        return number_format((float) $value, 2, ',', '.') . ' L';
    }
}
