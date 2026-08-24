<?php

namespace App\Console\Commands;

use App\Models\Procedure;
use App\Models\StockCategory;
use App\Models\StockItem;
use App\Services\AuditLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedImperatrizWorkshop extends Command
{
    protected $signature = 'chm:seed-imperatriz-workshop {--tenant=1} {--division=1} {--location=3} {--commit} {--confirm-location=}';
    protected $description = 'Prepara o estoque e os procedimentos internos de Imperatriz; dry-run por padrão.';

    public function handle(): int
    {
        $tenantId = (int) $this->option('tenant'); $locationId = (int) $this->option('location');
        $commit = (bool) $this->option('commit');
        if ($commit && (string) $locationId !== (string) $this->option('confirm-location')) {
            $this->error('SAFE NO: informe --confirm-location='.$locationId.' para gravar.'); return self::FAILURE;
        }
        if ($tenantId !== 1 || (int) $this->option('division') !== 1 || $locationId !== 3) {
            $this->error('SAFE NO: este comando é restrito ao tenant 1, division 1, location 3.'); return self::FAILURE;
        }

        [$categories, $items, $procedures] = $this->definition();
        $existingCategories = StockCategory::where('tenant_id', $tenantId)->get()->keyBy(fn ($c) => $this->key($c->name));
        if ($existingCategories->has($this->key('Bateriasa')) && ! $existingCategories->has($this->key('Baterias'))) {
            $existingCategories->put($this->key('Baterias'), $existingCategories->get($this->key('Bateriasa')));
        }
        $reused = []; $new = [];
        foreach ($categories as $name) {
            if ($existingCategories->has($this->key($name))) {
                $reused[] = $name;
            } else {
                $new[] = $name;
            }
        }
        $this->table(['CATEGORIES REUSED'], array_map(fn ($name) => [$name], $reused));
        $this->table(['CATEGORIES TO CREATE'], array_map(fn ($name) => [$name], $new));
        $this->table(['STOCK ITEMS TO CREATE', 'Categoria', 'Unidade', 'Quantidade inicial'], array_map(fn ($i) => [$i[0], $i[1], $i[2], '0'], $items));
        $this->table(['PROCEDURES TO CREATE', 'Interno', 'Itens vinculados'], array_map(fn ($name, $links) => [$name, 'SIM', count($links)], array_keys($procedures), $procedures));
        $links = []; foreach ($procedures as $procedure => $itemNames) foreach ($itemNames as $itemName) $links[] = [$procedure, $itemName];
        $this->table(['PROCEDURE ITEMS', 'Stock Item'], $links);
        if (! $commit) { $this->info('SAFE YES — dry-run: nenhuma alteração foi gravada.'); return self::SUCCESS; }

        DB::transaction(function () use ($tenantId, $locationId, $categories, $items, $procedures) {
            $categoryByKey = StockCategory::where('tenant_id', $tenantId)->get()->keyBy(fn ($c) => $this->key($c->name));
            // Corrige somente o typo global conhecido, sem criar uma categoria duplicada.
            $typo = $categoryByKey->get($this->key('Bateriasa'));
            if ($typo && ! $categoryByKey->has($this->key('Baterias'))) {
                $before = $typo->toArray(); $typo->update(['name' => 'Baterias']);
                app(AuditLogService::class)->updated($typo, ['before_data' => $before, 'after_data' => $typo->fresh()->toArray(), 'module' => 'stock']);
                $categoryByKey->forget($this->key('Bateriasa')); $categoryByKey->put($this->key('Baterias'), $typo);
            }
            foreach ($categories as $name) $categoryByKey->put($this->key($name), StockCategory::firstOrCreate(['tenant_id' => $tenantId, 'name' => $name]));
            $itemByName = StockItem::where('tenant_id', $tenantId)->where('location_id', $locationId)->get()->keyBy('name');
            foreach ($items as [$name, $category, $unit]) {
                $itemByName->put($name, StockItem::firstOrCreate(['tenant_id' => $tenantId, 'location_id' => $locationId, 'name' => $name], ['stock_category_id' => $categoryByKey[$this->key($category)]->id, 'unit' => $unit, 'quantity' => 0, 'minimum_quantity' => 0, 'unit_cost' => 0, 'active' => true, 'is_workshop_consumable' => true]));
            }
            foreach ($procedures as $name => $names) {
                $procedure = Procedure::firstOrCreate(['tenant_id' => $tenantId, 'location_id' => $locationId, 'name' => $name], ['can_be_internal' => true, 'validity_km' => false, 'validity_hours' => false, 'validity_period' => false, 'interval_km' => 0, 'interval_hours' => 0, 'interval_days' => 0]);
                $procedure->fields()->firstOrCreate(['slug' => 'materiais_utilizados'], ['label' => 'Materiais utilizados', 'field_type' => 'stock_item', 'required' => false, 'has_quantity' => true, 'sort_order' => 0]);
                $procedure->stockItems()->syncWithoutDetaching(collect($names)->map(fn ($item) => $itemByName[$item]->id)->all());
            }
        });
        $this->info('SAFE YES — cadastro gravado sem movimentos de estoque nem saldos fictícios.'); return self::SUCCESS;
    }

    private function key(string $value): string { return Str::lower(Str::ascii(trim($value))); }
    private function definition(): array
    {
        $categories = ['Óleos','Lubrificantes','Hidráulica','Pneumática','Freios','Elétrica e iluminação','Baterias','Cardan e transmissão','Embreagem','Suspensão','Filtros'];
        $items = [
            ['Óleo hidráulico 68','Óleos','L'],['Óleo câmbio Eaton 40','Óleos','L'],['Óleo ATF','Óleos','L'],['Graxa para chassi','Lubrificantes','KG'],['Graxa para meloso','Lubrificantes','KG'],
            ['Mangueira hidráulica','Hidráulica','M'],['Capa para mangueira hidráulica','Hidráulica','M'],['Conexão hidráulica','Hidráulica','UNID'],['Cotovelo hidráulico','Hidráulica','UNID'],['Kit reparo cilindro hidráulico','Hidráulica','UNID'],['Vedações para cilindro hidráulico','Hidráulica','UNID'],
            ['Mangueira de ar','Pneumática','M'],['Conexão pneumática','Pneumática','UNID'],['Válvula relé','Pneumática','UNID'],['Válvula secador','Pneumática','UNID'],['Cuíca de freio','Pneumática','UNID'],
            ['Lona de freio','Freios','UNID'],['Sapata de freio','Freios','UNID'],['Tambor de freio','Freios','UNID'],['Rolete de freio','Freios','UNID'],['Rebite para lona','Freios','UNID'],['Retentor','Freios','UNID'],
            ['Lâmpada H4','Elétrica e iluminação','UNID'],['Lâmpada 1141','Elétrica e iluminação','UNID'],['Lâmpada pingo','Elétrica e iluminação','UNID'],['Lanterna traseira','Elétrica e iluminação','UNID'],['Lanterna lateral','Elétrica e iluminação','UNID'],['Soquete','Elétrica e iluminação','UNID'],['Relé','Elétrica e iluminação','UNID'],['Fusível','Elétrica e iluminação','UNID'],['Fio elétrico','Elétrica e iluminação','M'],['Conector elétrico','Elétrica e iluminação','UNID'],['Terminal de bateria','Elétrica e iluminação','UNID'],['Fita isolante','Elétrica e iluminação','UNID'],['Bateria 150 Ah','Baterias','UNID'],
            ['Cruzeta de cardan','Cardan e transmissão','UNID'],['Garfo de cardan','Cardan e transmissão','UNID'],['Luva central','Cardan e transmissão','UNID'],['Rolamento central de cardan','Cardan e transmissão','UNID'],['Abraçadeira/fixador de cardan','Cardan e transmissão','UNID'],['Kit de embreagem','Embreagem','UNID'],['Mola auxiliar','Suspensão','UNID'],['Mola / feixe de molas','Suspensão','UNID'],['Bucha de mola','Suspensão','UNID'],['Grampo de mola','Suspensão','UNID'],['Pino de mola','Suspensão','UNID'],['Amortecedor dianteiro','Suspensão','UNID'],['Filtro de ar externo','Filtros','UNID'],['Filtro de ar interno','Filtros','UNID'],
        ];
        $procedures = [
            'Troca / reposição de óleo hidráulico'=>['Óleo hidráulico 68'],'Troca de óleo da transmissão'=>['Óleo câmbio Eaton 40','Óleo ATF'],'Lubrificação / engraxamento'=>['Graxa para chassi','Graxa para meloso'],'Manutenção de mangueira hidráulica'=>['Mangueira hidráulica','Capa para mangueira hidráulica','Conexão hidráulica','Cotovelo hidráulico'],'Manutenção pneumática'=>['Mangueira de ar','Conexão pneumática','Válvula relé','Válvula secador','Cuíca de freio'],'Manutenção do sistema de iluminação'=>['Lâmpada H4','Lâmpada 1141','Lâmpada pingo','Lanterna traseira','Lanterna lateral','Soquete'],'Manutenção elétrica'=>['Relé','Fusível','Fio elétrico','Conector elétrico','Terminal de bateria','Fita isolante'],'Substituição de bateria'=>['Bateria 150 Ah'],'Manutenção do sistema de freios'=>['Lona de freio','Sapata de freio','Tambor de freio','Rolete de freio','Rebite para lona','Retentor','Cuíca de freio'],'Manutenção de cardan'=>['Cruzeta de cardan','Garfo de cardan','Luva central','Rolamento central de cardan','Abraçadeira/fixador de cardan'],'Manutenção da embreagem'=>['Kit de embreagem'],'Manutenção de suspensão / molas'=>['Mola auxiliar','Mola / feixe de molas','Bucha de mola','Grampo de mola','Pino de mola'],'Troca de amortecedor'=>['Amortecedor dianteiro'],'Troca de filtro de ar'=>['Filtro de ar externo','Filtro de ar interno'],'Manutenção de cilindro hidráulico'=>['Kit reparo cilindro hidráulico','Vedações para cilindro hidráulico'],
        ]; return [$categories, $items, $procedures];
    }
}
