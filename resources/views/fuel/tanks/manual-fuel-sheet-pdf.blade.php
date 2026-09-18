<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">

<style>

@page {
    size: A4 landscape;
    margin: 13mm 11mm 11mm;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: DejaVu Sans, sans-serif;
    color: #111;
    font-size: 9px;
}

.header {
    width: 100%;
    border: 1.5px solid #111;
    margin-bottom: 5px;
}

.header-main {
    width: 100%;
    border-collapse: collapse;
}

.header-main td {
    padding: 6px 8px;
    vertical-align: middle;
}

.brand {
    width: 15%;
    font-size: 17px;
    font-weight: 800;
    text-align: center;
}

.title {
    width: 70%;
    text-align: center;
    font-size: 15px;
    font-weight: 800;
}

.unit {
    display: block;
    margin-top: 2px;
    font-size: 8px;
    font-weight: 500;
    text-transform: uppercase;
}

.top-fields {
    width: 100%;
    border-collapse: collapse;
    border-top: 1px solid #111;
}

.top-fields td {
    border-right: 1px solid #111;
    padding: 5px 7px;
    height: 29px;
}

.top-fields td:last-child {
    border-right: none;
}

.top-label {
    display: block;
    margin-bottom: 3px;
    font-size: 6.5px;
    font-weight: 800;
    text-transform: uppercase;
}

.write-line {
    display: inline-block;
    min-width: 80px;
    border-bottom: 1px solid #111;
    height: 11px;
}

.sheet {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.sheet thead {
    display: table-header-group;
}

.sheet th {
    background: #171717;
    color: #fff;
    border: 1px solid #111;
    padding: 5px 3px;
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
}

.sheet td {
    border: 1px solid #222;
    height: 23px;
    padding: 2px 4px;
    vertical-align: middle;
    overflow: hidden;
}

.col-num {
    width: 4%;
    text-align: center;
    font-weight: 700;
}

.col-code {
    width: 14%;
}

.col-plate {
    width: 15%;
}

.col-liters {
    width: 15%;
}

.col-km-previous {
    width: 17%;
}

.col-km-current {
    width: 19%;
}

.col-arla {
    width: 16%;
}

.prefilled {
    text-align: center;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .3px;
}

/*
 * "Casinhas" para escrita manual.
 * O operador tende a escrever um caractere sobre cada traço.
 */
.slots {
    white-space: nowrap;
    text-align: center;
}

.slot {
    display: inline-block;
    width: 12px;
    height: 13px;
    margin: 0 2px;
    border-bottom: 1.3px solid #111;
    vertical-align: bottom;
}

.slot.wide {
    width: 15px;
}

.separator {
    display: inline-block;
    width: 6px;
    text-align: center;
    font-size: 11px;
    font-weight: bold;
}

.signature-area {
    margin-top: 18px;
    text-align: center;
}

.signature-line {
    font-size: 10px;
    letter-spacing: .4px;
}

.signature-label {
    margin-top: 2px;
    font-size: 8px;
    font-weight: 700;
}

.footer-note {
    margin-top: 8px;
    font-size: 6.5px;
    color: #555;
    text-align: right;
}


/* ==========================================================
   AJUSTES DE IMPRESSÃO - FICHA MANUAL
   ========================================================== */

/* código sempre em destaque e maiúsculo */
.col-code .prefilled {
    text-transform: uppercase;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: .2px;
    line-height: 1.1;
}

/* usar melhor o espaço da placa */
.col-plate .prefilled {
    text-transform: uppercase;
    font-size: 16px;
    font-weight: 800;
    letter-spacing: .6px;
    line-height: 1.05;
}

/* usar melhor o espaço do KM */
.col-km-previous .prefilled,
.col-km-current .prefilled {
    font-size: 16px;
    font-weight: 800;
    letter-spacing: .3px;
    line-height: 1.05;
}

/* melhora centralização visual */
.col-code .prefilled,
.col-plate .prefilled,
.col-km-previous .prefilled,
.col-km-current .prefilled {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 26px;
}

/* pequenos ajustes de largura para aproveitar melhor a linha */
.col-code {
    width: 22%;
}

.col-plate {
    width: 18%;
}

.col-km-previous,
.col-km-current {
    width: 18%;
}

</style>
</head>

<body>

<div class="header">

    <table class="header-main">
        <tr>
            <td class="brand">
                AKSA
            </td>

            <td class="title">
                CONTROLE MANUAL DE ABASTECIMENTO

                <span class="unit">
                    {{ $location?->name ?? 'Unidade' }}
                </span>
            </td>

            <td class="brand">
                CHM
            </td>
        </tr>
    </table>


    <table class="top-fields">
        <tr>

            <td style="width: 28%;">
                <span class="top-label">
                    Data
                </span>

                <span class="write-line" style="min-width: 125px;"></span>
            </td>


            <td style="width: 24%;">
                <span class="top-label">
                    Qtd. abastecimentos
                </span>

                <span class="write-line" style="min-width: 95px;"></span>
            </td>


            <td style="width: 28%;">
                <span class="top-label">
                    Total abastecido (litros)
                </span>

                <span class="write-line" style="min-width: 120px;"></span>
            </td>


            <td style="width: 20%;">
                <span class="top-label">
                    Operador
                </span>

                <span class="write-line" style="min-width: 90px;"></span>
            </td>

        </tr>
    </table>

</div>


<table class="sheet">

    <thead>
        <tr>

            <th class="col-num">
                Nº
            </th>

            <th class="col-code">
                Código
            </th>

            <th class="col-plate">
                Placa
            </th>

            <th class="col-liters">
                Litros
            </th>

            <th class="col-km-previous">
                Último KM registrado
            </th>

            <th class="col-km-current">
                KM no abastecimento
            </th>

            @if($options['show_arla'])
                <th class="col-arla">
                    ARLA
                </th>
            @endif

        </tr>
    </thead>

    <tbody>

        @php
            $rowNumber = 1;

            $slots = function ($count, $wide = false) {
                $html = '<div class="slots">';

                for ($i = 0; $i < $count; $i++) {
                    $html .= '<span class="slot'
                        .($wide ? ' wide' : '')
                        .'"></span>';
                }

                $html .= '</div>';

                return $html;
            };

            $decimalSlots = function () {
                return '<div class="slots">'
                    .'<span class="slot"></span>'
                    .'<span class="slot"></span>'
                    .'<span class="slot"></span>'
                    .'<span class="slot"></span>'
                    .'<span class="separator">,</span>'
                    .'<span class="slot"></span>'
                    .'</div>';
            };

            $arlaSlots = function () {
                return '<div class="slots">'
                    .'<span class="slot"></span>'
                    .'<span class="slot"></span>'
                    .'<span class="slot"></span>'
                    .'<span class="separator">,</span>'
                    .'<span class="slot"></span>'
                    .'<span class="slot"></span>'
                    .'</div>';
            };
        @endphp


        @if($options['mode'] === 'prefilled')

            @foreach($vehicles as $vehicle)

                <tr>

                    <td class="col-num">
                        {{ $rowNumber++ }}
                    </td>


                    <td class="col-code">

                        @if($options['show_code'] && filled($vehicle->name))

                            <div class="prefilled">
                                {{ strtoupper($vehicle->name) }}
                            </div>

                        @else

                            {!! $slots(7) !!}

                        @endif

                    </td>


                    <td class="col-plate">

                        @if($options['show_plate'] && filled($vehicle->plate))

                            <div class="prefilled">
                                {{ strtoupper($vehicle->plate) }}
                            </div>

                        @else

                            {!! $slots(7) !!}

                        @endif

                    </td>


                    <td class="col-liters">
                        {!! $decimalSlots() !!}
                    </td>


                    <td class="col-km-previous">

                        @if(
                            $options['show_km']
                            && $vehicle->current_km !== null
                        )

                            <div class="prefilled">
                                {{ number_format(
                                    (float) $vehicle->current_km,
                                    0,
                                    ',',
                                    '.'
                                ) }}
                            </div>

                        @else

                            {!! $slots(7, true) !!}

                        @endif

                    </td>


                    <td class="col-km-current">
                        {!! $slots(7, true) !!}
                    </td>


                    @if($options['show_arla'])

                        <td class="col-arla">
                            {!! $arlaSlots() !!}
                        </td>

                    @endif

                </tr>

            @endforeach

        @endif


        @for($i = 0; $i < $options['blank_rows']; $i++)

            <tr>

                <td class="col-num">
                    {{ $rowNumber++ }}
                </td>

                <td class="col-code">
                    {!! $slots(7) !!}
                </td>

                <td class="col-plate">
                    {!! $slots(7) !!}
                </td>

                <td class="col-liters">
                    {!! $decimalSlots() !!}
                </td>

                <td class="col-km-previous">
                    {!! $slots(7, true) !!}
                </td>

                <td class="col-km-current">
                    {!! $slots(7, true) !!}
                </td>

                @if($options['show_arla'])

                    <td class="col-arla">
                        {!! $arlaSlots() !!}
                    </td>

                @endif

            </tr>

        @endfor

    </tbody>

</table>


<div class="signature-area">

    <div class="signature-line">
        __________________________________________
    </div>

    <div class="signature-label">
        Operador de Combustível
    </div>

</div>

<div class="footer-note">
    Ficha de apoio operacional · posteriormente conferir os lançamentos no CHM.
</div>

</body>
</html>

<style>
/* Ajustes finais para A4 vertical */

@page {
    size: A4 portrait;
    margin: 9mm 7mm 9mm;
}

body {
    font-size: 7.5px;
}

.header-main td {
    padding: 4px 5px;
}

.brand {
    width: 14%;
    font-size: 13px;
}

.title {
    width: 72%;
    font-size: 12px;
}

.top-fields td {
    padding: 4px 5px;
    height: 25px;
}

.sheet th {
    padding: 4px 2px;
    font-size: 7px;
}

.sheet td {
    height: 21px;
    padding: 2px;
}

.col-num {
    width: 4%;
}

.col-code {
    width: 13%;
}

.col-plate {
    width: 14%;
}

.col-liters {
    width: 14%;
}

.col-km-previous {
    width: 18%;
}

.col-km-current {
    width: 19%;
}

.col-arla {
    width: 14%;
}

.prefilled {
    font-size: 7.5px;
}

.slot {
    width: 8px;
    height: 11px;
    margin: 0 1px;
}

.slot.wide {
    width: 10px;
}

.separator {
    width: 4px;
    font-size: 9px;
}

.signature-area {
    margin-top: 14px;
}
</style>

<style>
/* Ajuste de legibilidade - A4 vertical */

body {
    font-size: 8.8px;
}

.brand {
    font-size: 14px;
}

.title {
    font-size: 13.5px;
}

.unit {
    font-size: 8.5px;
}

.top-label {
    font-size: 7.2px;
}

.sheet th {
    font-size: 8px;
    padding: 5px 2px;
}

.sheet td {
    height: 23px;
    padding: 2px 3px;
}

.prefilled {
    font-size: 8.8px;
}

.col-num {
    font-size: 8.5px;
}

.slot {
    width: 9px;
    height: 12px;
    margin: 0 1px;
}

.slot.wide {
    width: 11px;
}

.separator {
    font-size: 10px;
}

.signature-label {
    font-size: 8.5px;
}

.footer-note {
    font-size: 7px;
}
</style>
