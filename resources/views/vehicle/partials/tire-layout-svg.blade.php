@php
    $layout = $layout ?? 'truck_6_mixed';
@endphp

<svg
    class="tire-layout-illustration"
    viewBox="0 0 120 120"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
>
    {{-- CHASSI --}}
    <rect x="56" y="18" width="8" height="84" rx="3" class="svg-chassis-main"/>
    <rect x="36" y="22" width="48" height="14" rx="4" class="svg-cab"/>
    <rect x="48" y="34" width="24" height="8" rx="3" class="svg-axle-center"/>

    @switch($layout)

        {{-- =========================================================
           4 PNEUS
        ========================================================= --}}
        @case('car_4_single')

            {{-- eixo 1 --}}
            <rect x="22" y="30" width="8" height="22" rx="2" class="svg-tire"/>
            <rect x="90" y="30" width="8" height="22" rx="2" class="svg-tire"/>
            <rect x="30" y="39" width="26" height="4" rx="2" class="svg-axle"/>
            <rect x="64" y="39" width="26" height="4" rx="2" class="svg-axle"/>

            {{-- eixo 2 --}}
            <rect x="22" y="78" width="8" height="22" rx="2" class="svg-tire"/>
            <rect x="90" y="78" width="8" height="22" rx="2" class="svg-tire"/>
            <rect x="30" y="87" width="26" height="4" rx="2" class="svg-axle"/>
            <rect x="64" y="87" width="26" height="4" rx="2" class="svg-axle"/>

            {{-- cubo --}}
            <rect x="50" y="81" width="20" height="16" rx="3" class="svg-hub"/>
            @break


        {{-- =========================================================
           6 PNEUS
        ========================================================= --}}
        @case('truck_6_mixed')

            {{-- eixo 1 simples --}}
            <rect x="22" y="26" width="8" height="22" rx="2" class="svg-tire"/>
            <rect x="90" y="26" width="8" height="22" rx="2" class="svg-tire"/>
            <rect x="30" y="35" width="26" height="4" rx="2" class="svg-axle"/>
            <rect x="64" y="35" width="26" height="4" rx="2" class="svg-axle"/>

            {{-- eixo 2 duplo --}}
            <rect x="18" y="72" width="8" height="22" rx="2" class="svg-tire"/>
            <rect x="28" y="72" width="8" height="22" rx="2" class="svg-tire"/>
            <rect x="84" y="72" width="8" height="22" rx="2" class="svg-tire"/>
            <rect x="94" y="72" width="8" height="22" rx="2" class="svg-tire"/>

            <rect x="36" y="81" width="20" height="4" rx="2" class="svg-axle"/>
            <rect x="64" y="81" width="20" height="4" rx="2" class="svg-axle"/>

            <rect x="50" y="75" width="20" height="16" rx="3" class="svg-hub"/>
            @break

        {{-- =========================================================
           8 PNEUS
        ========================================================= --}}
            @case('truck_8_mixed')
            
                {{-- eixo 1 simples --}}
                <rect x="22" y="18" width="8" height="20" rx="2" class="svg-tire"/>
                <rect x="90" y="18" width="8" height="20" rx="2" class="svg-tire"/>
                <rect x="30" y="26" width="26" height="4" rx="2" class="svg-axle"/>
                <rect x="64" y="26" width="26" height="4" rx="2" class="svg-axle"/>
            
                {{-- eixo 2 simples --}}
                <rect x="22" y="52" width="8" height="20" rx="2" class="svg-tire"/>
                <rect x="90" y="52" width="8" height="20" rx="2" class="svg-tire"/>
                <rect x="30" y="60" width="26" height="4" rx="2" class="svg-axle"/>
                <rect x="64" y="60" width="26" height="4" rx="2" class="svg-axle"/>
                <rect x="50" y="55" width="20" height="14" rx="3" class="svg-hub"/>
            
                {{-- eixo 3 duplo --}}
                <rect x="18" y="84" width="8" height="20" rx="2" class="svg-tire"/>
                <rect x="28" y="84" width="8" height="20" rx="2" class="svg-tire"/>
                <rect x="84" y="84" width="8" height="20" rx="2" class="svg-tire"/>
                <rect x="94" y="84" width="8" height="20" rx="2" class="svg-tire"/>
                <rect x="36" y="92" width="20" height="4" rx="2" class="svg-axle"/>
                <rect x="64" y="92" width="20" height="4" rx="2" class="svg-axle"/>
                <rect x="50" y="87" width="20" height="14" rx="3" class="svg-hub"/>
                @break
        {{-- =========================================================
           10 PNEUS
        ========================================================= --}}
        @case('truck_10_mixed')

            {{-- eixo 1 simples --}}
            <rect x="22" y="18" width="8" height="20" rx="2" class="svg-tire"/>
            <rect x="90" y="18" width="8" height="20" rx="2" class="svg-tire"/>
            <rect x="30" y="26" width="26" height="4" rx="2" class="svg-axle"/>
            <rect x="64" y="26" width="26" height="4" rx="2" class="svg-axle"/>

            {{-- eixo 2 duplo --}}
            <rect x="18" y="54" width="8" height="20" rx="2" class="svg-tire"/>
            <rect x="28" y="54" width="8" height="20" rx="2" class="svg-tire"/>
            <rect x="84" y="54" width="8" height="20" rx="2" class="svg-tire"/>
            <rect x="94" y="54" width="8" height="20" rx="2" class="svg-tire"/>
            <rect x="36" y="62" width="20" height="4" rx="2" class="svg-axle"/>
            <rect x="64" y="62" width="20" height="4" rx="2" class="svg-axle"/>
            <rect x="50" y="57" width="20" height="14" rx="3" class="svg-hub"/>

            {{-- eixo 3 duplo --}}
            <rect x="18" y="82" width="8" height="20" rx="2" class="svg-tire"/>
            <rect x="28" y="82" width="8" height="20" rx="2" class="svg-tire"/>
            <rect x="84" y="82" width="8" height="20" rx="2" class="svg-tire"/>
            <rect x="94" y="82" width="8" height="20" rx="2" class="svg-tire"/>
            <rect x="36" y="90" width="20" height="4" rx="2" class="svg-axle"/>
            <rect x="64" y="90" width="20" height="4" rx="2" class="svg-axle"/>
            <rect x="50" y="85" width="20" height="14" rx="3" class="svg-hub"/>
            @break


        {{-- =========================================================
           12 PNEUS
        ========================================================= --}}
        @case('truck_12_mixed')

            {{-- eixo 1 simples --}}
            <rect x="22" y="14" width="8" height="18" rx="2" class="svg-tire"/>
            <rect x="90" y="14" width="8" height="18" rx="2" class="svg-tire"/>
            <rect x="30" y="21" width="26" height="4" rx="2" class="svg-axle"/>
            <rect x="64" y="21" width="26" height="4" rx="2" class="svg-axle"/>

            {{-- eixo 2 simples --}}
            <rect x="22" y="42" width="8" height="18" rx="2" class="svg-tire"/>
            <rect x="90" y="42" width="8" height="18" rx="2" class="svg-tire"/>
            <rect x="30" y="49" width="26" height="4" rx="2" class="svg-axle"/>
            <rect x="64" y="49" width="26" height="4" rx="2" class="svg-axle"/>
            <rect x="50" y="44" width="20" height="14" rx="3" class="svg-hub"/>

            {{-- eixo 3 duplo --}}
            <rect x="18" y="68" width="8" height="18" rx="2" class="svg-tire"/>
            <rect x="28" y="68" width="8" height="18" rx="2" class="svg-tire"/>
            <rect x="84" y="68" width="8" height="18" rx="2" class="svg-tire"/>
            <rect x="94" y="68" width="8" height="18" rx="2" class="svg-tire"/>
            <rect x="36" y="75" width="20" height="4" rx="2" class="svg-axle"/>
            <rect x="64" y="75" width="20" height="4" rx="2" class="svg-axle"/>
            <rect x="50" y="70" width="20" height="14" rx="3" class="svg-hub"/>

            {{-- eixo 4 duplo --}}
            <rect x="18" y="92" width="8" height="18" rx="2" class="svg-tire"/>
            <rect x="28" y="92" width="8" height="18" rx="2" class="svg-tire"/>
            <rect x="84" y="92" width="8" height="18" rx="2" class="svg-tire"/>
            <rect x="94" y="92" width="8" height="18" rx="2" class="svg-tire"/>
            <rect x="36" y="99" width="20" height="4" rx="2" class="svg-axle"/>
            <rect x="64" y="99" width="20" height="4" rx="2" class="svg-axle"/>
            <rect x="50" y="94" width="20" height="14" rx="3" class="svg-hub"/>
            @break

        @default
            {{-- fallback = 6 pneus --}}
            <rect x="22" y="26" width="8" height="22" rx="2" class="svg-tire"/>
            <rect x="90" y="26" width="8" height="22" rx="2" class="svg-tire"/>
            <rect x="30" y="35" width="26" height="4" rx="2" class="svg-axle"/>
            <rect x="64" y="35" width="26" height="4" rx="2" class="svg-axle"/>

            <rect x="18" y="72" width="8" height="22" rx="2" class="svg-tire"/>
            <rect x="28" y="72" width="8" height="22" rx="2" class="svg-tire"/>
            <rect x="84" y="72" width="8" height="22" rx="2" class="svg-tire"/>
            <rect x="94" y="72" width="8" height="22" rx="2" class="svg-tire"/>
            <rect x="36" y="81" width="20" height="4" rx="2" class="svg-axle"/>
            <rect x="64" y="81" width="20" height="4" rx="2" class="svg-axle"/>
            <rect x="50" y="75" width="20" height="16" rx="3" class="svg-hub"/>
    @endswitch
</svg>