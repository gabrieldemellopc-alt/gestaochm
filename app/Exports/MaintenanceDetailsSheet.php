<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;

use Maatwebsite\Excel\Events\AfterSheet;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class MaintenanceDetailsSheet implements
    FromArray,
    WithTitle,
    ShouldAutoSize,
    WithStyles,
    WithEvents
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /*
    |--------------------------------------------------------------------------
    | ARRAY
    |--------------------------------------------------------------------------
    */

    public function array(): array
    {
        return [];
    }

    /*
    |--------------------------------------------------------------------------
    | TITLE
    |--------------------------------------------------------------------------
    */

    public function title(): string
    {
        return 'Detalhado';
    }

    /*
    |--------------------------------------------------------------------------
    | EVENTS
    |--------------------------------------------------------------------------
    */

    public function registerEvents(): array
    {
        return [

            AfterSheet::class => function(AfterSheet $event)
            {
                $sheet =
                    $event->sheet->getDelegate();

                /*
                |--------------------------------------------------------------------------
                | LARGURAS
                |--------------------------------------------------------------------------
                */

                $sheet->getColumnDimension('A')->setWidth(18);
                $sheet->getColumnDimension('B')->setWidth(28);
                $sheet->getColumnDimension('C')->setWidth(18);
                $sheet->getColumnDimension('D')->setWidth(30);
                $sheet->getColumnDimension('E')->setWidth(18);
                $sheet->getColumnDimension('F')->setWidth(18);

                /*
                |--------------------------------------------------------------------------
                | HEADER
                |--------------------------------------------------------------------------
                */

                $sheet->mergeCells('A1:F1');

                $sheet->setCellValue(
                    'A1',
                    'DETALHAMENTO DE MANUTENÇÕES'
                );

                $sheet->setCellValue(
                    'A3',
                    'Período'
                );

                $sheet->setCellValue(
                    'B3',
                    $this->data['startDate']
                    .' até '.
                    $this->data['endDate']
                );

                /*
                |--------------------------------------------------------------------------
                | HEADER TABELA
                |--------------------------------------------------------------------------
                */

                $sheet->setCellValue('A6', 'Data');
                $sheet->setCellValue('B6', 'Veículo');
                $sheet->setCellValue('C6', 'Placa');
                $sheet->setCellValue('D6', 'Procedimento');
                $sheet->setCellValue('E6', 'Tipo');
                $sheet->setCellValue('F6', 'Valor');

                /*
                |--------------------------------------------------------------------------
                | DADOS
                |--------------------------------------------------------------------------
                */

                $row = 7;

            foreach (
                $this->data['maintenances']
                as $maintenances_raw
            )
            {
                $sheet->setCellValue(
                    'A'.$row,
                    \Carbon\Carbon::parse(
                        $maintenances_raw['created_at']
                    )->format('d/m/Y')
                );
            
                $sheet->setCellValue(
                    'B'.$row,
                    $maintenances_raw['vehicle_name']
                    ?? '-'
                );
            
                $sheet->setCellValue(
                    'C'.$row,
                    $maintenances_raw['vehicle_plate']
                    ?? '-'
                );
            
                $sheet->setCellValue(
                    'D'.$row,
                    $maintenances_raw['procedure_name']
                    ?? '-'
                );
            
                $sheet->setCellValue(
                    'E'.$row,
                    $maintenances_raw['maintenance_type']
                    === 'internal'
                        ? 'Interna'
                        : 'Terceirizada'
                );
            
                $sheet->setCellValue(
                    'F'.$row,
                    $maintenances_raw['total_cost']
                );
            
                $row++;
            }

                $lastRow = $row - 1;

                /*
                |--------------------------------------------------------------------------
                | TÍTULO
                |--------------------------------------------------------------------------
                */

                $sheet
                    ->getStyle('A1')
                    ->applyFromArray([

                        'font' => [

                            'bold' => true,
                            'size' => 18

                        ],

                        'alignment' => [

                            'horizontal' => 'center'

                        ]

                    ]);

                /*
                |--------------------------------------------------------------------------
                | HEADER ESCURO
                |--------------------------------------------------------------------------
                */

                $sheet
                    ->getStyle('A6:F6')
                    ->applyFromArray([

                        'font' => [

                            'bold' => true,

                            'color' => [
                                'rgb' => 'FFFFFF'
                            ]

                        ],

                        'fill' => [

                            'fillType' =>
                                Fill::FILL_SOLID,

                            'startColor' => [
                                'rgb' => '0F172A'
                            ]

                        ]

                    ]);

                /*
                |--------------------------------------------------------------------------
                | BORDAS
                |--------------------------------------------------------------------------
                */

                $sheet
                    ->getStyle('A6:F'.$lastRow)
                    ->applyFromArray([

                        'borders' => [

                            'allBorders' => [

                                'borderStyle' =>
                                    Border::BORDER_THIN,

                                'color' => [
                                    'rgb' => 'E5E7EB'
                                ]

                            ]

                        ]

                    ]);

                /*
                |--------------------------------------------------------------------------
                | ZEBRA ROWS
                |--------------------------------------------------------------------------
                */

                for (
                    $i = 7;
                    $i <= $lastRow;
                    $i++
                )
                {
                    if ($i % 2 == 0)
                    {
                        $sheet
                            ->getStyle('A'.$i.':F'.$i)
                            ->applyFromArray([

                                'fill' => [

                                    'fillType' =>
                                        Fill::FILL_SOLID,

                                    'startColor' => [
                                        'rgb' => 'F8FAFC'
                                    ]

                                ]

                            ]);
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | MOEDA
                |--------------------------------------------------------------------------
                */

                $sheet
                    ->getStyle('F7:F'.$lastRow)
                    ->getNumberFormat()
                    ->setFormatCode(
                        'R$ #,##0.00'
                    );

                /*
                |--------------------------------------------------------------------------
                | ALINHAMENTOS
                |--------------------------------------------------------------------------
                */

                $sheet
                    ->getStyle('A6:F'.$lastRow)
                    ->getAlignment()
                    ->setVertical('center');

                /*
                |--------------------------------------------------------------------------
                | FILTRO
                |--------------------------------------------------------------------------
                */

                $sheet->setAutoFilter(
                    'A6:F'.$lastRow
                );

                /*
                |--------------------------------------------------------------------------
                | FREEZE HEADER
                |--------------------------------------------------------------------------
                */

                $sheet->freezePane('A7');
            }

        ];
    }

    /*
    |--------------------------------------------------------------------------
    | STYLES
    |--------------------------------------------------------------------------
    */

    public function styles(Worksheet $sheet)
    {
        return [];
    }
}