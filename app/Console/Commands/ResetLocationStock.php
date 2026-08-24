<?php

namespace App\Console\Commands;

use App\Models\Division;
use App\Models\Location;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ResetLocationStock extends Command
{
    protected $signature = 'chm:reset-location-stock
        {--tenant= : Tenant obrigatório}
        {--division= : Divisão obrigatória}
        {--location= : Location obrigatória}
        {--commit : Executa somente após o dry-run aprovado}
        {--confirm-location= : Deve coincidir exatamente com --location}';

    protected $description = 'Audita e limpa somente estoque e procedimentos locais, preservando categorias globais';

    public function handle(): int
    {
        $context = $this->resolveResetContext();
        if (! $context) return self::FAILURE;

        $plan = $this->plan($context);
        $this->renderPlan($plan);

        if (! $this->option('commit')) {
            $this->info('SAFE '.($plan['safe'] ? 'YES' : 'NO').' — dry-run: NENHUMA ALTERAÇÃO FOI REALIZADA.');
            return $plan['safe'] ? self::SUCCESS : self::FAILURE;
        }

        if (! $plan['safe'] || (string) $this->option('confirm-location') !== (string) $context['location']->id) {
            $this->error('Commit recusado: reveja o dry-run e informe --confirm-location='.$context['location']->id.'.');
            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($context, $plan) {
                $ids = $plan['ids'];
                if ($ids['stock_movement_ids']) DB::table('stock_movements')->whereIn('id', $ids['stock_movement_ids'])->delete();
                if ($ids['stock_item_ids']) DB::table('stock_items')->whereIn('id', $ids['stock_item_ids'])->delete();
                if ($ids['procedure_ids']) {
                    DB::table('procedure_fields')->whereIn('procedure_id', $ids['procedure_ids'])->delete();
                    DB::table('procedure_vehicle')->whereIn('procedure_id', $ids['procedure_ids'])->delete();
                    DB::table('procedures')->whereIn('id', $ids['procedure_ids'])->delete();
                }

                if (DB::table('stock_items')->where('tenant_id', $context['tenant']->id)->where('location_id', $context['location']->id)->exists()
                    || DB::table('stock_movements')->where('tenant_id', $context['tenant']->id)->where('location_id', $context['location']->id)->exists()
                    || DB::table('procedures')->where('tenant_id', $context['tenant']->id)->where('location_id', $context['location']->id)->whereIn('id', $ids['procedure_ids'])->exists()) {
                    throw new RuntimeException('Validação pós-limpeza falhou para a location alvo.');
                }
            });
        } catch (\Throwable $exception) {
            $this->error('RESET FAILED — transação revertida: '.$exception->getMessage());
            return self::FAILURE;
        }

        $this->info('RESET COMPLETE — estoque local removido; categorias globais foram preservadas.');
        return self::SUCCESS;
    }

    private function resolveResetContext(): ?array
    {
        foreach (['tenant', 'division', 'location'] as $option) {
            if (! ctype_digit((string) $this->option($option)) || (int) $this->option($option) < 1) {
                $this->error("Informe --{$option} com um ID numérico positivo.");
                return null;
            }
        }
        $tenant = Tenant::find((int) $this->option('tenant'));
        $division = Division::find((int) $this->option('division'));
        $location = Location::find((int) $this->option('location'));
        if (! $tenant || ! $division || ! $location || (int) $division->tenant_id !== (int) $tenant->id || (int) $location->tenant_id !== (int) $tenant->id || (int) $location->division_id !== (int) $division->id) {
            $this->error('Tenant, divisão e location não formam um contexto válido.');
            return null;
        }
        return compact('tenant', 'division', 'location');
    }

    private function plan(array $context): array
    {
        $tenantId = $context['tenant']->id;
        $locationId = $context['location']->id;
        $stockItemIds = DB::table('stock_items')->where('tenant_id', $tenantId)->where('location_id', $locationId)->pluck('id')->all();
        $stockMovementIds = DB::table('stock_movements')->where('tenant_id', $tenantId)->where('location_id', $locationId)->pluck('id')->all();
        $orphanMovementCount = $stockItemIds ? DB::table('stock_movements')->whereIn('stock_item_id', $stockItemIds)->where('location_id', '!=', $locationId)->count() : 0;
        $procedures = DB::table('procedures as p')
            ->where('p.tenant_id', $tenantId)->where('p.location_id', $locationId)
            ->select('p.id', 'p.name')
            ->selectRaw('(select count(*) from procedure_fields pf where pf.procedure_id = p.id) as fields_count')
            ->selectRaw('(select count(*) from maintenance_records mr where mr.procedure_id = p.id) as maintenance_count')
            ->selectRaw('(select count(*) from maintenance_record_items mri where mri.procedure_id = p.id) as maintenance_item_count')
            ->selectRaw('(select count(*) from procedure_vehicle pv join vehicles v on v.id = pv.vehicle_id where pv.procedure_id = p.id and v.location_id <> ?) as external_vehicle_count', [$locationId])
            ->orderBy('p.id')->get();
        $deletable = $procedures->filter(fn ($p) => ! $p->maintenance_count && ! $p->maintenance_item_count && ! $p->external_vehicle_count)->values();
        $preserved = $procedures->reject(fn ($p) => $deletable->contains('id', $p->id))->values();
        $categories = DB::table('stock_categories as sc')->leftJoin('stock_items as si', function ($join) { $join->on('si.stock_category_id', '=', 'sc.id')->on('si.tenant_id', '=', 'sc.tenant_id'); })->where('sc.tenant_id', $tenantId)->groupBy('sc.id', 'sc.name')->orderBy('sc.id')->get(['sc.id', 'sc.name', DB::raw('count(si.id) as items_total')]);

        return [
            'safe' => $orphanMovementCount === 0,
            'issues' => $orphanMovementCount ? ["{$orphanMovementCount} movimento(s) de outro contexto referenciam item(ns) locais."] : [],
            'stock' => ['movements' => count($stockMovementIds), 'items' => count($stockItemIds)],
            'categories' => $categories,
            'procedures_delete' => $deletable,
            'procedures_preserve' => $preserved,
            'ids' => ['stock_movement_ids' => $stockMovementIds, 'stock_item_ids' => $stockItemIds, 'procedure_ids' => $deletable->pluck('id')->all()],
        ];
    }

    private function renderPlan(array $plan): void
    {
        $this->table(['STOCK TO DELETE', 'Quantidade'], [['stock_movements', $plan['stock']['movements']], ['stock_items', $plan['stock']['items']], ['categorias locais', 0]]);
        $this->table(['PRESERVED SHARED CATEGORY', 'Itens no tenant'], $plan['categories']->map(fn ($c) => ["#{$c->id} {$c->name}", $c->items_total])->all());
        $this->table(['PROCEDURE', 'Classificação', 'Campos', 'Manutenções', 'Itens manutenção', 'Vínculos externos'], $plan['procedures_delete']->map(fn ($p) => ["#{$p->id} {$p->name}", 'DELETE_LOCAL', $p->fields_count, $p->maintenance_count, $p->maintenance_item_count, $p->external_vehicle_count])->merge($plan['procedures_preserve']->map(fn ($p) => ["#{$p->id} {$p->name}", 'PRESERVE_SHARED', $p->fields_count, $p->maintenance_count, $p->maintenance_item_count, $p->external_vehicle_count]))->all());
        foreach ($plan['issues'] as $issue) $this->error($issue);
    }
}
