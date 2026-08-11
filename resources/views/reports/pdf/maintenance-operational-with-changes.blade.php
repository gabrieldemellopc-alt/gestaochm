@php
    $baseReportHtml = view('reports.pdf.maintenance-operational', get_defined_vars())->render();
    $changesHtml = view('reports.pdf.partials.maintenance-changes', get_defined_vars())->render();

    echo str_replace('</body>', $changesHtml.'</body>', $baseReportHtml);
@endphp
