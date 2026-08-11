@php
    $baseReportHtml = view('reports.pdf.maintenance', get_defined_vars())->render();
    $changesHtml = view('reports.pdf.partials.maintenance-changes', get_defined_vars())->render();
    $footerMarker = '<div class="report-footer">';

    echo str_contains($baseReportHtml, $footerMarker)
        ? str_replace($footerMarker, $changesHtml.$footerMarker, $baseReportHtml)
        : str_replace('</body>', $changesHtml.'</body>', $baseReportHtml);
@endphp
