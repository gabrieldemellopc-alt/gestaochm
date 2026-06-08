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
        return [
        
            new MaintenanceSummarySheet($this->data),
        
            new MaintenanceDetailsSheet($this->data),
        
        ];
    }
}