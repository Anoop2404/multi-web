@php
    $isArtwork = in_array($design['layout'] ?? '', ['stse_three_up', 'custom_background'], true);
    $die = \App\Support\Mcq\McqHallTicketDesign::printGeometry($design);
    $landscape = ($design['page_orientation'] ?? 'landscape') === 'landscape';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Hall Ticket Preview</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; padding:16px; background:#f8fafc; }
        @if($isArtwork)
        .preview-sheet { width:{{ $landscape ? 297 : 210 }}mm; height:{{ $landscape ? 210 : 297 }}mm; padding:{{ $die['margin'] }}mm; background:white; }
        .preview-cards { display:grid; grid-template-columns:repeat({{ $die['columns'] }},{{ $die['width'] }}mm); gap:{{ $die['gap'] }}mm; }
        @endif
        @page { size:A4 {{ $isArtwork && $landscape ? 'landscape' : 'portrait' }}; margin:{{ $isArtwork ? '0' : '10mm' }}; }
        @media print { body { padding:0; background:white; } .sample-note { display:none; } }
    </style>
</head>
<body>
@if(!empty($isSample))
    <p class="sample-note" style="font-family:system-ui,sans-serif;font-size:13px;color:#b45309;">
        <strong>Sample preview</strong> — for client demo only. Not a real participant card.
    </p>
@endif
@if($isArtwork)
    <main class="preview-sheet" aria-label="Saved A4 sheet preview">
        <div class="preview-cards">
            @for($cardIndex = 0; $cardIndex < $die['per_page']; $cardIndex++)
                @include('mcq.partials.hall-ticket-card', ['design' => $design, 'logoUrl' => $logoUrl, 'sample' => $sample])
            @endfor
        </div>
    </main>
@else
    @include('mcq.partials.hall-ticket-card', ['design' => $design, 'logoUrl' => $logoUrl, 'sample' => $sample])
@endif
</body>
</html>
