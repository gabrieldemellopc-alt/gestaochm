@php
    $baseReportHtml = view('reports.pdf.maintenance-operational', get_defined_vars())->render();
    $materialsHtml = view('reports.pdf.partials.maintenance-materials', get_defined_vars())->render();
    echo str_replace('</body>', $materialsHtml.'</body>', $baseReportHtml);
@endphp
