<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithEvents;

use Maatwebsite\Excel\Events\AfterSheet;

class MaintenanceSummarySheet implements
    FromArray,
    WithTitle,
    WithStyles,
    ShouldAutoSize,
    WithEvents
{
    protected $data;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /*
    |--------------------------------------------------------------------------
    | ARRAY VAZIO
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
        return 'Visão Geral';
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

                $sheet->getColumnDimension('A')->setWidth(35);
                $sheet->getColumnDimension('B')->setWidth(22);
                $sheet->getColumnDimension('C')->setWidth(18);
                $sheet->getColumnDimension('D')->setWidth(18);
                $sheet->getColumnDimension('E')->setWidth(18);

                /*
                |--------------------------------------------------------------------------
                | HEADER
                |--------------------------------------------------------------------------
                */

                $sheet->mergeCells('A1:E1');

                $sheet->setCellValue(
                    'A1',
                    'RELATÓRIO OPERACIONAL DE MANUTENÇÕES'
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
                | KPIs
                |--------------------------------------------------------------------------
                */

                $sheet->setCellValue('A5', 'Manutenções');
                $sheet->setCellValue(
                    'B5',
                    count($this->data['maintenances'])
                );

                $sheet->setCellValue('A6', 'Internas');
                $sheet->setCellValue(
                    'B6',
                    $this->data['internalCount']
                );

                $sheet->setCellValue('A7', 'Terceirizadas');
                $sheet->setCellValue(
                    'B7',
                    $this->data['externalCount']
                );

                $sheet->setCellValue('A8', 'Custo registrado das ordens');
                $sheet->setCellValue(
                    'B8',
                    $this->data['totalCost']
                );

                /*
                |--------------------------------------------------------------------------
                | PROCEDIMENTOS
                |--------------------------------------------------------------------------
                */

                $sheet->mergeCells('A11:E11');

                $sheet->setCellValue(
                    'A11',
                    'PROCEDIMENTOS MAIS EXECUTADOS'
                );

                $sheet->setCellValue('A13', 'Procedimento');
                $sheet->setCellValue('B13', 'Quantidade');
                $sheet->setCellValue('C13', 'Custo por item/procedimento');
                $sheet->setCellValue('D13', 'Média');

                $row = 14;

                foreach (
                    $this->data['procedureStats']
                    as $stat
                )
                {
                    $sheet->setCellValue(
                        'A'.$row,
                        $stat['procedure']
                    );

                    $sheet->setCellValue(
                        'B'.$row,
                        $stat['count']
                    );

                    $sheet->setCellValue(
                        'C'.$row,
                        $stat['total']
                    );

                    $sheet->setCellValue(
                        'D'.$row,
                        $stat['average']
                    );

                    $row++;
                }

                /*
                |--------------------------------------------------------------------------
                | CUSTOS POR VEÍCULO
                |--------------------------------------------------------------------------
                */

                $vehicleSectionRow =
                    $row + 3;

                $sheet->mergeCells(
                    'A'.$vehicleSectionRow
                    .':E'.$vehicleSectionRow
                );

                $sheet->setCellValue(
                    'A'.$vehicleSectionRow,
                    'CUSTOS POR VEÍCULO'
                );

                $vehicleHeaderRow =
                    $vehicleSectionRow + 2;

                $sheet->setCellValue(
                    'A'.$vehicleHeaderRow,
                    'Veículo'
                );

                $sheet->setCellValue(
                    'B'.$vehicleHeaderRow,
                    'Placa'
                );

                $sheet->setCellValue(
                    'C'.$vehicleHeaderRow,
                    'KM rodados'
                );

                $sheet->setCellValue(
                    'D'.$vehicleHeaderRow,
                    'HR rodadas'
                );

                $sheet->setCellValue(
                    'E'.$vehicleHeaderRow,
                    'Custo registrado das ordens'
                );

                $vehicleRow =
                    $vehicleHeaderRow + 1;

                foreach (
                    $this->data['vehicleCosts']
                    as $vehicle
                )
                {
                    $sheet->setCellValue(
                        'A'.$vehicleRow,
                        $vehicle['vehicle']
                    );

                    $sheet->setCellValue(
                        'B'.$vehicleRow,
                        $vehicle['plate']
                    );

                    $sheet->setCellValue(
                        'C'.$vehicleRow,
                        $vehicle['km_driven']
                    );

                    $sheet->setCellValue(
                        'D'.$vehicleRow,
                        $vehicle['hours_driven']
                    );

                    $sheet->setCellValue(
                        'E'.$vehicleRow,
                        $vehicle['total']
                    );

                    $vehicleRow++;
                }

                /*
                |--------------------------------------------------------------------------
                | ESTILOS
                |--------------------------------------------------------------------------
                */

                // TÍTULO

                $sheet->getStyle('A1')->applyFromArray([

                    'font' => [

                        'bold' => true,
                        'size' => 18

                    ],

                    'alignment' => [

                        'horizontal' => 'center'

                    ]

                ]);

                // HEADERS ESCUROS

                foreach (
                    [
                        'A13:D13',
                        'A'.$vehicleHeaderRow
                        .':E'.$vehicleHeaderRow
                    ]
                    as $headerRange
                )
                {
                    $sheet
                        ->getStyle($headerRange)
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
                }

                // SEÇÕES

                foreach (
                    [
                        'A11',
                        'A'.$vehicleSectionRow
                    ]
                    as $section
                )
                {
                    $sheet
                        ->getStyle($section)
                        ->applyFromArray([

                            'font' => [

                                'bold' => true,
                                'size' => 14

                            ]

                        ]);
                }

                // BORDAS SUAVES

                $lastRow =
                    $vehicleRow - 1;

                $sheet
                    ->getStyle('A1:E'.$lastRow)
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

                // FORMATO MOEDA

                $sheet
                    ->getStyle('C14:D'.$row)
                    ->getNumberFormat()
                    ->setFormatCode(
                        'R$ #,##0.00'
                    );

                $sheet
                    ->getStyle(
                        'E'.($vehicleHeaderRow + 1)
                        .':E'.$lastRow
                    )
                    ->getNumberFormat()
                    ->setFormatCode(
                        'R$ #,##0.00'
                    );

                // NEGRITO KPIs

                $sheet
                    ->getStyle('A5:A8')
                    ->getFont()
                    ->setBold(true);

                $sheet
                    ->getStyle('B5:B8')
                    ->getFont()
                    ->setBold(true);
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
