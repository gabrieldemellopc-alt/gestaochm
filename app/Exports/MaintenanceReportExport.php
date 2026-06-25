<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MaintenanceReportExport implements WithMultipleSheets
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function sheets(): array
    {
        $sheets = [
            new MaintenanceSummarySheet($this->data),
            new MaintenanceDetailsSheet($this->data),
            new MaintenanceVehiclesSheet($this->data),
            new MaintenanceProceduresSheet($this->data),
        ];

        if (! empty($this->data['stockConsumed'])) {
            $sheets[] = new MaintenanceStockConsumedSheet($this->data);
        }

        if (
            ! empty($this->data['canViewCancelled'])
            && ! empty($this->data['cancelledMaintenances'])
        ) {
            $sheets[] = new MaintenanceCancelledSheet($this->data);
        }

        return $sheets;
    }
}
