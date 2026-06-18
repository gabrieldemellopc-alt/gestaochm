<?php

namespace App\Services;

use App\Models\Procedure;
use Carbon\Carbon;
use App\Models\TireInstallation;
use App\Models\TireMeasurement;

class PreventiveService
{
    public static function getVehicleAlerts($vehicle)
    {
        $alerts = [];

        $procedures =
            $vehicle->procedures;
            
        foreach ($procedures as $procedure) {

            $latest =
                $vehicle->validMaintenances()
                ->where('procedure_id', $procedure->id)
                ->latest('performed_at')
                ->first();

            /*
            |--------------------------------------------------------------------------
            | COM HISTÓRICO
            |--------------------------------------------------------------------------
            */

            if ($latest) {

                // KM
                if (
                    $latest->next_due_km &&
                    $vehicle->current_km >= $latest->next_due_km
                ) {

                    $alerts[] = [
                        'status' => 'danger',
                        'procedure' => $procedure->name,
                        'message' => 'Preventiva vencida por KM',
                    ];

                    continue;
                }

                // HORAS
                if (
                    $latest->next_due_hours &&
                    $vehicle->current_hours >= $latest->next_due_hours
                ) {

                    $alerts[] = [
                        'status' => 'danger',
                        'procedure' => $procedure->name,
                        'message' => 'Preventiva vencida por horímetro',
                    ];

                    continue;
                }

                // DATA
                if (
                    $latest->next_due_date &&
                    now()->greaterThan($latest->next_due_date)
                ) {

                    $alerts[] = [
                        'status' => 'danger',
                        'procedure' => $procedure->name,
                        'message' => 'Preventiva vencida por período',
                    ];

                    continue;
                }

                /*
                |--------------------------------------------------------------------------
                | WARNING
                |--------------------------------------------------------------------------
                */

                if (
                    $latest->next_due_km &&
                    $vehicle->current_km >= ($latest->next_due_km - 2000)
                ) {

                    $alerts[] = [
                        'status' => 'warning',
                        'procedure' => $procedure->name,
                        'message' => 'Preventiva próxima por KM',
                    ];

                    continue;
                }

                if (
                    $latest->next_due_hours &&
                    $vehicle->current_hours >= ($latest->next_due_hours - 100)
                ) {

                    $alerts[] = [
                        'status' => 'warning',
                        'procedure' => $procedure->name,
                        'message' => 'Preventiva próxima por horímetro',
                    ];

                    continue;
                }

                if (
                    $latest->next_due_date &&
                    now()->addDays(7)->greaterThan($latest->next_due_date)
                ) {

                    $alerts[] = [
                        'status' => 'warning',
                        'procedure' => $procedure->name,
                        'message' => 'Preventiva próxima por período',
                    ];

                    continue;
                }

            }

            /*
            |--------------------------------------------------------------------------
            | SEM HISTÓRICO
            |--------------------------------------------------------------------------
            */

            else {
                
                // KM inicial
                if (
                    $procedure->validity_km &&
                    $procedure->interval_km &&
                    $vehicle->current_km >= $procedure->interval_km
                ) {

                    $alerts[] = [
                        'status' => 'danger',
                        'procedure' => $procedure->name,
                        'message' => 'Preventiva inicial vencida por KM',
                    ];

                    continue;
                }

                // HORAS inicial
                if (
                    $procedure->validity_hours &&
                    $procedure->interval_hours &&
                    $vehicle->current_hours >= $procedure->interval_hours
                ) {

                    $alerts[] = [
                        'status' => 'danger',
                        'procedure' => $procedure->name,
                        'message' => 'Preventiva inicial vencida por horímetro',
                    ];

                    continue;
                }

                // PERÍODO inicial
                if (
                    $procedure->validity_period &&
                    $vehicle->operation_started_at
                ) {

                    $dueDate =
                        Carbon::parse(
                            $vehicle->operation_started_at
                        )->addDays($procedure->interval_days);

                    if (now()->greaterThan($dueDate)) {

                        $alerts[] = [
                            'status' => 'danger',
                            'procedure' => $procedure->name,
                            'message' => 'Preventiva inicial vencida por período',
                        ];

                        continue;
                    }
                }

                if (
                
                    $procedure->validity_km &&
                    $procedure->interval_km &&
                    $vehicle->current_km >=
                    ($procedure->interval_km * 0.8)                
                ) {
                
                    $alerts[] = [
                
                        'status' => 'warning',
                
                        'procedure' => $procedure->name,
                
                        'message' => 'Preventiva inicial próxima por KM',
                
                    ];
                
                    continue;
                }

                if (
                
                    $procedure->validity_hours &&
                    $procedure->interval_hours &&
                    $vehicle->current_hours >=
                    ($procedure->interval_hours * 0.8)                
                ) {
                
                    $alerts[] = [
                
                        'status' => 'warning',
                
                        'procedure' => $procedure->name,
                
                        'message' => 'Preventiva inicial próxima por horímetro',
                
                    ];
                
                    continue;
                }

                if (

                    $procedure->validity_period &&
                    $vehicle->operation_started_at
                
                ) {
                
                    $dueDate =
                        Carbon::parse(
                            $vehicle->operation_started_at
                        )->addDays($procedure->interval_days);
                
                    if (
                
                        now()->addDays(7)
                            ->greaterThan($dueDate)
                
                    ) {
                
                        $alerts[] = [
                
                            'status' => 'warning',
                
                            'procedure' => $procedure->name,
                
                            'message' => 'Preventiva inicial próxima por período',
                
                        ];
                
                        continue;
                    }
                }

            }
        }

        /*
        |--------------------------------------------------------------------------
        | REFERÊNCIA KM
        |--------------------------------------------------------------------------
        */

        $kmReferenceDate =
            $vehicle->last_km_update_at
            ??
            $vehicle->operation_started_at;

        /*
        |--------------------------------------------------------------------------
        | ALERTA KM
        |--------------------------------------------------------------------------
        */

        if ($kmReferenceDate) {

            $daysWithoutKmUpdate =
                Carbon::parse($kmReferenceDate)
                    ->diffInDays(now(), false);
            if ($daysWithoutKmUpdate <= 0) {
                $daysWithoutKmUpdate = 0;
            }
            if ($daysWithoutKmUpdate >= 60) {

                $alerts[] = [

                    'status' => 'danger',

                    'procedure' =>
                        'Atualização operacional',

                    'message' =>
                        'KM sem atualização há mais de 60 dias',

                ];

            }

            elseif ($daysWithoutKmUpdate >= 30) {

                $alerts[] = [

                    'status' => 'danger',

                    'procedure' =>
                        'Atualização operacional',

                    'message' =>
                        'KM sem atualização há mais de 30 dias',

                ];

            }

            elseif ($daysWithoutKmUpdate >= 15) {

                $alerts[] = [

                    'status' => 'warning',

                    'procedure' =>
                        'Atualização operacional',

                    'message' =>
                        'KM sem atualização há mais de 15 dias',

                ];

            }

            elseif ($daysWithoutKmUpdate >= 7) {

                $alerts[] = [

                    'status' => 'warning',

                    'procedure' =>
                        'Atualização operacional',

                    'message' =>
                        'KM sem atualização há 7 dias',

                ];

            }

        }

        /*
        |--------------------------------------------------------------------------
        | REFERÊNCIA HORÍMETRO
        |--------------------------------------------------------------------------
        */

        $hoursReferenceDate =
            $vehicle->last_hours_update_at
            ??
            $vehicle->operation_started_at;

        /*
        |--------------------------------------------------------------------------
        | ALERTA HORÍMETRO
        |--------------------------------------------------------------------------
        */

        if ($hoursReferenceDate) {

            $daysWithoutHoursUpdate =
                Carbon::parse($hoursReferenceDate)
                    ->diffInDays(now(), false);
            if ($daysWithoutHoursUpdate <= 0) {
                $daysWithoutHoursUpdate = 0;
            }
            if ($daysWithoutHoursUpdate >= 60) {

                $alerts[] = [

                    'status' => 'danger',

                    'procedure' =>
                        'Atualização operacional',

                    'message' =>
                        'Horímetro sem atualização há mais de 60 dias',

                ];

            }

            elseif ($daysWithoutHoursUpdate >= 30) {

                $alerts[] = [

                    'status' => 'danger',

                    'procedure' =>
                        'Atualização operacional',

                    'message' =>
                        'Horímetro sem atualização há mais de 30 dias',

                ];

            }

            elseif ($daysWithoutHoursUpdate >= 15) {

                $alerts[] = [

                    'status' => 'warning',

                    'procedure' =>
                        'Atualização operacional',

                    'message' =>
                        'Horímetro sem atualização há mais de 15 dias',

                ];

            }

            elseif ($daysWithoutHoursUpdate >= 7) {

                $alerts[] = [

                    'status' => 'warning',

                    'procedure' =>
                        'Atualização operacional',

                    'message' =>
                        'Horímetro sem atualização há 7 dias',

                ];

            }

        }
        
        /*
        |--------------------------------------------------------------------------
        | ALERTAS DE PNEUS
        |--------------------------------------------------------------------------
        */
        
        $activeTireInstallations =
            TireInstallation::with([
                'tire',
            ])
                ->where('tenant_id', $vehicle->tenant_id)
                ->where('vehicle_id', $vehicle->id)
                ->where('active', true)
                ->get();
        
        foreach ($activeTireInstallations as $installation) {
        
            $tire =
                $installation->tire;
        
            if (! $tire) {
                continue;
            }
        
            $latestMeasurement =
                TireMeasurement::where('tenant_id', $vehicle->tenant_id)
                    ->where('vehicle_id', $vehicle->id)
                    ->where('tire_id', $tire->id)
                    ->where('position_code', $installation->position_code)
                    ->whereNull('cancelled_at')

                    ->latest('measured_at')
                    ->latest('id')
                    ->first();
        
            /*
            |--------------------------------------------------------------------------
            | PNEU SEM MEDIÇÃO
            |--------------------------------------------------------------------------
            */
        
            if (! $latestMeasurement) {
            
                $installedKm =
                    $installation->installed_km !== null
                        ? (float) $installation->installed_km
                        : null;
            
                $currentKm =
                    $vehicle->current_km !== null
                        ? (float) $vehicle->current_km
                        : null;
            
                $kmWithoutMeasurement =
                    null;
            
                if (
                    $installedKm !== null
                    &&
                    $currentKm !== null
                    &&
                    $currentKm >= $installedKm
                ) {
                    $kmWithoutMeasurement =
                        $currentKm - $installedKm;
                }
            
                if (
                    $kmWithoutMeasurement !== null
                    &&
                    $kmWithoutMeasurement >= 3000
                ) {
                    $alerts[] = [
                        'status' =>
                            'danger',
            
                        'procedure' =>
                            'Controle de pneus',
            
                        'message' =>
                            'Pneu ' . $tire->code . ' instalado na posição ' . $installation->position_code . ' rodou ' . number_format($kmWithoutMeasurement, 0, ',', '.') . ' km sem medição de sulco',
                    ];
            
                    continue;
                }
            
                if (
                    $kmWithoutMeasurement !== null
                    &&
                    $kmWithoutMeasurement >= 1000
                ) {
                    $alerts[] = [
                        'status' =>
                            'warning',
            
                        'procedure' =>
                            'Controle de pneus',
            
                        'message' =>
                            'Pneu ' . $tire->code . ' instalado na posição ' . $installation->position_code . ' rodou ' . number_format($kmWithoutMeasurement, 0, ',', '.') . ' km sem medição de sulco',
                    ];
            
                    continue;
                }
            
                $daysSinceInstallation =
                    $installation->installed_at
                        ? Carbon::parse($installation->installed_at)
                            ->diffInDays(now(), false)
                        : null;
            
                if (
                    $daysSinceInstallation !== null
                    &&
                    $daysSinceInstallation >= 15
                ) {
                    $alerts[] = [
                        'status' =>
                            'warning',
            
                        'procedure' =>
                            'Controle de pneus',
            
                        'message' =>
                            'Pneu ' . $tire->code . ' instalado na posição ' . $installation->position_code . ' está há ' . $daysSinceInstallation . ' dias sem medição de sulco',
                    ];
            
                    continue;
                }
            
                
            
                continue;
            }
        
            $currentTread =
                $latestMeasurement->minimum_tread !== null
                    ? (float) $latestMeasurement->minimum_tread
                    : null;
        
            if ($currentTread === null) {
        
                $alerts[] = [
                    'status' =>
                        'warning',
        
                    'procedure' =>
                        'Controle de pneus',
        
                    'message' =>
                        'Pneu ' . $tire->code . ' sem valor de sulco informado na posição ' . $installation->position_code,
                ];
        
                continue;
            }
            
            $criticalLimit =
                $tire->critical_tread_depth !== null
                    ? (float) $tire->critical_tread_depth
                    : 3.00;
        
            $warningLimit =
                $tire->warning_tread_depth !== null
                    ? (float) $tire->warning_tread_depth
                    : 5.00;
        
            /*
            |--------------------------------------------------------------------------
            | PNEU CRÍTICO
            |--------------------------------------------------------------------------
            */
        
            if ($currentTread <= $criticalLimit) {
        
                $alerts[] = [
                    'status' =>
                        'danger',
        
                    'procedure' =>
                        'Controle de pneus',
        
                    'message' =>
                        'Pneu ' . $tire->code .
                        ' (' . $installation->position_code . ')' .
                        ' crítico — sulco atual: ' .
                        number_format($currentTread, 2, ',', '.') . ' mm',
                ];
        
                continue;
            }
        
            /*
            |--------------------------------------------------------------------------
            | PNEU EM ATENÇÃO
            |--------------------------------------------------------------------------
            */
        
            if ($currentTread <= $warningLimit) {
        
                $alerts[] = [
                    'status' =>
                        'warning',
        
                    'procedure' =>
                        'Controle de pneus',
        
                    'message' =>
                        'Pneu ' . $tire->code .
                        ' (' . $installation->position_code . ')' .
                        ' em atenção — sulco atual: ' .
                        number_format($currentTread, 2, ',', '.') . ' mm',
                ];
        
                continue;
            }
        
        
            /*
            |--------------------------------------------------------------------------
            | PNEU RODOU SEM NOVA MEDIÇÃO DE SULCO
            |--------------------------------------------------------------------------
            */
            
            $lastMeasurementKm =
                $latestMeasurement->vehicle_km !== null
                    ? (float) $latestMeasurement->vehicle_km
                    : null;
            
            $currentVehicleKm =
                $vehicle->current_km !== null
                    ? (float) $vehicle->current_km
                    : null;
            
            $kmSinceLastTreadMeasurement =
                null;
            
            if (
                $lastMeasurementKm !== null
                &&
                $currentVehicleKm !== null
                &&
                $currentVehicleKm >= $lastMeasurementKm
            ) {
                $kmSinceLastTreadMeasurement =
                    $currentVehicleKm - $lastMeasurementKm;
            }
            
            if (
                $kmSinceLastTreadMeasurement !== null
                &&
                $kmSinceLastTreadMeasurement >= 3000
            ) {
                $alerts[] = [
                    'status' =>
                        'danger',
            
                    'procedure' =>
                        'Controle de pneus',
            
                    'message' =>
                        'Pneu ' . $tire->code .
                        ' (' . $installation->position_code . ')' .
                        ' rodou ' . number_format($kmSinceLastTreadMeasurement, 0, ',', '.') .
                        ' km desde a última medição de sulco',
                ];
            
                continue;
            }
            
            if (
                $kmSinceLastTreadMeasurement !== null
                &&
                $kmSinceLastTreadMeasurement >= 1000
            ) {
                $alerts[] = [
                    'status' =>
                        'warning',
            
                    'procedure' =>
                        'Controle de pneus',
            
                    'message' =>
                        'Pneu ' . $tire->code . 
                            ' (' . $installation->position_code . ')' . 
                            ' rodou ' . number_format($kmSinceLastTreadMeasurement, 0, ',', '.') . ' km desde a última medição de sulco',
                ];
            
                continue;
            }
            
            /*
            |--------------------------------------------------------------------------
            | PNEU HÁ MUITO TEMPO SEM MEDIÇÃO
            |--------------------------------------------------------------------------
            */
        
            if ($latestMeasurement->measured_at) {
        
                $daysWithoutTireMeasurement =
                    Carbon::parse($latestMeasurement->measured_at)
                        ->diffInDays(now(), false);
        
                if ($daysWithoutTireMeasurement >= 60) {
        
                    $alerts[] = [
                        'status' =>
                            'danger',
        
                        'procedure' =>
                            'Controle de pneus',
        
                        'message' =>
                            'Pneu ' . $tire->code .
                            ' (' . $installation->position_code . ')' .
                            ' está há ' . $daysSinceInstallation .
                            ' dias sem medição de sulco',
                    ];
        
                    continue;
                }
        
                if ($daysWithoutTireMeasurement >= 30) {
        
                    $alerts[] = [
                        'status' =>
                            'warning',
        
                        'procedure' =>
                            'Controle de pneus',
        
                        'message' =>
                            'Pneu ' . $tire->code .
                            ' (' . $installation->position_code . ')' .
                            ' está há ' . $daysSinceInstallation .
                            ' dias sem medição de sulco',
                    ];
        
                    continue;
                }
            }
        }
        return $alerts;
    }

    public static function getVehicleStatus($vehicle)
    {
        $alerts =
            self::getVehicleAlerts($vehicle);

        if (
            collect($alerts)->contains('status', 'danger')
        ) {

            return 'danger';
        }

        if (
            collect($alerts)->contains('status', 'warning')
        ) {

            return 'warning';
        }

        return 'ok';
    }
}
