<?php

namespace App\Services;

class AlertService
{
    public static function getVehicleStatus($vehicle)
    {
        $latest =
            $vehicle->maintenances
            ->whereNull('cancelled_at')
            ->sortByDesc('performed_at')
            ->first();

        if (!$latest) {

            return 'ok';
        }

        if (

            $latest->next_due_km &&
            $vehicle->current_km >=
            $latest->next_due_km

        ) {

            return 'critical';
        }

        if (

            $latest->next_due_hours &&
            $vehicle->current_hours >=
            $latest->next_due_hours

        ) {

            return 'critical';
        }

        if (

            $latest->next_due_km &&
            $vehicle->current_km >=
            ($latest->next_due_km - 2000)

        ) {

            return 'warning';
        }

        if (

            $latest->next_due_hours &&
            $vehicle->current_hours >=
            ($latest->next_due_hours - 100)

        ) {

            return 'warning';
        }

        return 'ok';
    }
}
