<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ChecklistTemplate;
use App\Models\ChecklistTemplateItem;
use App\Models\Division;

class ChecklistTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $divisions = Division::all();

        foreach ($divisions as $division)
        {

            /*
            |--------------------------------------------------------------------------
            | MOTORISTA • PRÉ-OPERAÇÃO
            |--------------------------------------------------------------------------
            */

            $driverPre =
                ChecklistTemplate::create([

                    'division_id' =>
                        $division->id,

                    'name' =>
                        'Pré-operação',

                    'type' =>
                        'driver_pre',

                    'active' => true

                ]);

            $this->createItems(
                $driverPre,
                [

                    [
                        'label' =>
                            'KM inicial',

                        'field_type' =>
                            'number'
                    ],

                    [
                        'label' =>
                            'Horímetro inicial',

                        'field_type' =>
                            'number'
                    ],

                    [
                        'label' =>
                            'Pneus OK?',

                        'field_type' =>
                            'boolean'
                    ],

                    [
                        'label' =>
                            'Faróis OK?',

                        'field_type' =>
                            'boolean'
                    ],

                    [
                        'label' =>
                            'Setas OK?',

                        'field_type' =>
                            'boolean'
                    ],

                    [
                        'label' =>
                            'Combustível OK?',

                        'field_type' =>
                            'boolean'
                    ],

                    [
                        'label' =>
                            'ARLA OK?',

                        'field_type' =>
                            'boolean'
                    ],

                    [
                        'label' =>
                            'Foto painel',

                        'field_type' =>
                            'photo'
                    ],

                ]
            );

            /*
            |--------------------------------------------------------------------------
            | MOTORISTA • PÓS-OPERAÇÃO
            |--------------------------------------------------------------------------
            */

            $driverPost =
                ChecklistTemplate::create([

                    'division_id' =>
                        $division->id,

                    'name' =>
                        'Pós-operação',

                    'type' =>
                        'driver_post',

                    'active' => true

                ]);

            $this->createItems(
                $driverPost,
                [

                    [
                        'label' =>
                            'KM final',

                        'field_type' =>
                            'number'
                    ],

                    [
                        'label' =>
                            'Horímetro final',

                        'field_type' =>
                            'number'
                    ],

                    [
                        'label' =>
                            'Houve avaria?',

                        'field_type' =>
                            'boolean'
                    ],

                    [
                        'label' =>
                            'Observações',

                        'field_type' =>
                            'text'
                    ],

                    [
                        'label' =>
                            'Foto final',

                        'field_type' =>
                            'photo'
                    ],

                ]
            );

            /*
            |--------------------------------------------------------------------------
            | GESTOR • DIÁRIO
            |--------------------------------------------------------------------------
            */

            $manager =
                ChecklistTemplate::create([

                    'division_id' =>
                        $division->id,

                    'name' =>
                        'Checklist diário',

                    'type' =>
                        'manager_daily',

                    'active' => true

                ]);

            $this->createItems(
                $manager,
                [

                    [
                        'label' =>
                            'Veículos indisponíveis?',

                        'field_type' =>
                            'boolean'
                    ],

                    [
                        'label' =>
                            'Preventivas vencidas?',

                        'field_type' =>
                            'boolean'
                    ],

                    [
                        'label' =>
                            'Estoque crítico?',

                        'field_type' =>
                            'boolean'
                    ],

                    [
                        'label' =>
                            'Pendências críticas?',

                        'field_type' =>
                            'boolean'
                    ],

                ]
            );

        }
    }

    private function createItems(
        $template,
        array $items
    ): void {

        foreach ($items as $index => $item)
        {

            ChecklistTemplateItem::create([

                'checklist_template_id' =>
                    $template->id,

                'label' =>
                    $item['label'],

                'field_type' =>
                    $item['field_type'],

                'required' => true,

                'is_active' => true,

                'sort_order' =>
                    $index + 1

            ]);

        }
    }
}