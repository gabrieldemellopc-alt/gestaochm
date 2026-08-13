@php
    $baseReportHtml = view('reports.pdf.maintenance', get_defined_vars())->render();
    $materialsHtml = view('reports.pdf.partials.maintenance-materials', get_defined_vars())->render();
    $footerMarker = '<div class="report-footer">';
    echo str_contains($baseReportHtml, $footerMarker)
        ? str_replace($footerMarker, $materialsHtml.$footerMarker, $baseReportHtml)
        : str_replace('</body>', $materialsHtml.'</body>', $baseReportHtml);
@endphp
