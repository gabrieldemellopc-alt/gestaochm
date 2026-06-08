<?php

namespace App\Services;

use App\Models\Procedure;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceRecordValue;

class MaintenanceService
{
    public static function create(array $data)
    {
        $procedure = Procedure::with('fields')
            ->findOrFail($data['procedure_id']);

        /*
        |--------------------------------------------------------------------------
        | TIPO DE EXECUÇÃO
        |--------------------------------------------------------------------------
        */

        $executionType =
            $data['maintenance_type']
            ?? 'external';

        /*
        |--------------------------------------------------------------------------
        | PRÓXIMAS PREVENTIVAS
        |--------------------------------------------------------------------------
        */

        $nextDueKm = null;

        $nextDueHours = null;

        $nextDueDate = null;

        if (
            $procedure->validity_km &&
            $procedure->interval_km
        ) {

            $nextDueKm =

                $data['performed_km']
                +
                $procedure->interval_km;
        }

        if (
            $procedure->validity_hours &&
            $procedure->interval_hours
        ) {

            $nextDueHours =

                $data['performed_hours']
                +
                $procedure->interval_hours;
        }

        if (
            $procedure->validity_period &&
            $procedure->interval_days
        ) {

            $nextDueDate =

                now()
                    ->parse(
                        $data['performed_at']
                    )
                    ->addDays(
                        $procedure->interval_days
                    );
        }

        /*
        |--------------------------------------------------------------------------
        | REGISTRO PRINCIPAL
        |--------------------------------------------------------------------------
        */

        $maintenance = MaintenanceRecord::create([

            'tenant_id' => auth()->user()->tenant_id,

            'vehicle_id' =>
                $data['vehicle_id'],

            'procedure_id' =>
                $procedure->id,

            'maintenance_type' =>
                $executionType,

            'performed_km' =>
                $data['performed_km'] ?? null,

            'performed_hours' =>
                $data['performed_hours'] ?? null,

            'performed_at' =>
                $data['performed_at'],

            'next_due_km' =>
                $nextDueKm,

            'next_due_hours' =>
                $nextDueHours,

            'next_due_date' =>
                $nextDueDate,

            'extra_cost' =>

                $data['extra_cost']
                ?? 0,

            'reason' =>

                $data['reason']
                ?? null,
            'provider_name' =>
            
                $executionType == 'external'
            
                    ? ($data['provider_name'] ?? null)
            
                    : null,
            'notes' =>

                $data['notes']
                ?? null
        ]);

        /*
        |--------------------------------------------------------------------------
        | TOTAL
        |--------------------------------------------------------------------------
        */

        $totalCost = 0;

        $updatedStockItem = null;

        /*
        |--------------------------------------------------------------------------
        | CAMPOS DINÂMICOS
        |--------------------------------------------------------------------------
        */

        foreach ($procedure->fields as $field) {

            $value =

                $data['fields'][$field->slug]
                ?? null;

            MaintenanceRecordValue::create([

                'maintenance_record_id' =>
                    $maintenance->id,

                'procedure_field_id' =>
                    $field->id,

                'value' =>
                    $value,

                'quantity' =>

                    $field->field_type == 'stock_item'

                        ? (

                            $data['fields'][
                                $field->slug . '_quantity'
                            ] ?? 1

                        )

                        : null
            ]);

            /*
            |--------------------------------------------------------------------------
            | ESTOQUE
            |--------------------------------------------------------------------------
            |
            | Apenas manutenção INTERNA
            |
            */

            if (

                $executionType == 'internal'
                &&

                $field->field_type === 'stock_item'
                &&

                $value

            ) {

                $stockItem =
                    StockItem::find($value);

                if (!$stockItem) {
                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | QUANTIDADE
                |--------------------------------------------------------------------------
                */

                $quantity =

                    $data['fields'][
                        $field->slug . '_quantity'
                    ] ?? 1;

                $quantity =
                    (float) $quantity;

                /*
                |--------------------------------------------------------------------------
                | MOVIMENTAÇÃO
                |--------------------------------------------------------------------------
                */

                StockMovement::create([

                    'tenant_id' =>
                        auth()->user()->tenant_id,

                    'stock_item_id' =>
                        $stockItem->id,

                    'movement_type' =>
                        'out',

                    'quantity' =>
                        $quantity,

                    'unit_cost' =>
                        $stockItem->unit_cost,

                    'description' =>

                        'Manutenção #'
                        .
                        $maintenance->id
                ]);

                /*
                |--------------------------------------------------------------------------
                | BAIXA
                |--------------------------------------------------------------------------
                */

                $stockItem->decrement(

                    'quantity',

                    $quantity

                );

                /*
                |--------------------------------------------------------------------------
                | SOMA CUSTO
                |--------------------------------------------------------------------------
                */

                $totalCost +=

                    $stockItem->unit_cost
                    *
                    $quantity;

                $updatedStockItem =
                    $stockItem;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | CUSTO EXTERNO
        |--------------------------------------------------------------------------
        */

        $totalCost +=

            $data['extra_cost']
            ?? 0;

        /*
        |--------------------------------------------------------------------------
        | UPDATE FINAL
        |--------------------------------------------------------------------------
        */

        $maintenance->update([

            'total_cost' =>
                $totalCost

        ]);

        $maintenance->load(

            'procedure'

        );

        /*
        |--------------------------------------------------------------------------
        | RESPONSE
        |--------------------------------------------------------------------------
        */

        return [

            'maintenance' =>
                $maintenance,

            'updated_stock_item' =>
                $updatedStockItem
        ];
    }
}