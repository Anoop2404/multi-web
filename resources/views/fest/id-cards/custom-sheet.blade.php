<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ID Cards — {{ $eventTitle }}</title>
    <style>
        @page {
            size: {{ ($pageWidthMm ?? null) && ($pageHeightMm ?? null) ? $pageWidthMm.'mm '.$pageHeightMm.'mm' : 'A4 portrait' }};
            margin: {{ ($gridLayout ?? null) ? '0' : '6mm' }};
        }
        * { box-sizing: border-box; }
        .die-page { position: relative; width: 100%; }
        .die-card-slot { position: absolute; }
        body { font-family: Arial, DejaVu Sans, sans-serif; color: #1e293b; margin: 0; }
        .sheet-title { text-align: center; font-size: 11px; font-weight: bold; color: #475569; margin-bottom: 4mm; }
        .section-title { font-size: 10px; font-weight: bold; color: #475569; margin: 3mm 0 2mm; }
        .grid { width: 100%; border-collapse: separate; border-spacing: 3mm 3mm; }
        .grid td { width: 50%; vertical-align: top; padding: 0; }
        .card {
            border: 1px solid #cbd5e1;
            border-radius: 2.5mm;
            overflow: hidden;
            page-break-inside: avoid;
            background: #fff;
            position: relative;
        }
        .card__bg {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            object-fit: cover;
        }
        .card__field {
            position: absolute;
            line-height: 1.25;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        .card__photo, .card__qr {
            position: absolute;
            object-fit: cover;
        }
        .card__photo {
            border-radius: 50%;
        }
        .card__item-badge {
            position: absolute;
            left: 5.56%; width: 2.89%; height: 1.86%;
            border-radius: 50%;
            color: #fff; font-size: 4.6pt; font-weight: bold;
            display: flex; align-items: center; justify-content: center;
        }
        .card__item-bar {
            position: absolute;
            left: 10%; width: 84.44%; height: 1.57%;
            border-radius: 1.1mm;
            background: #eef2f9;
        }
        .card__item-text {
            position: absolute;
            left: 11.11%; width: 82.22%; height: 1.57%;
            font-size: 5pt; color: #1e293b;
            display: flex; align-items: center;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .card__shape {
            position: absolute;
        }
        .card__divider {
            position: absolute;
        }
        .card__divider--vertical {
            border-left: 0.6pt solid #cbd5e1;
            width: 0;
        }
        .card__divider--horizontal {
            border-top: 0.6pt solid #cbd5e1;
            height: 0;
        }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
@if($showTitle ?? true)
<p class="sheet-title">{{ $clusterName }} · {{ $eventTitle }} · {{ ucfirst($audience ?? 'participant') }} ID cards</p>
@endif

@php
    // A grid layout dictates its own per-page count (cols × rows, e.g. a 10-up
    // die-cut sheet) — cardsPerPage only applies to the plain auto-flow table.
    $perPage = ($gridLayout ?? null) ? ($gridLayout['cols'] * $gridLayout['rows']) : max(1, $cardsPerPage ?? 4);
    $renderSections = ! empty($sections);
@endphp

@if($renderSections)
    @foreach($sections as $sectionIndex => $section)
        @if($sectionIndex > 0)<div class="page-break"></div>@endif
        <p class="section-title">{{ $section['item_title'] ?? 'Item' }}</p>
        @php $chunks = array_chunk($section['cards'] ?? [], $perPage); @endphp
        @foreach($chunks as $pageIndex => $pageCards)
            @if($pageIndex > 0)<div class="page-break"></div>@endif
            @include('fest.id-cards.partials.custom-sheet-page', ['pageCards' => $pageCards, 'gridLayout' => $gridLayout ?? null, 'backgroundUrl' => $backgroundUrl ?? null, 'fields' => $fields ?? [], 'cardWidthMm' => $cardWidthMm ?? 96, 'cardHeightMm' => $cardHeightMm ?? 72, 'pageHeightMm' => $pageHeightMm ?? null])
        @endforeach
    @endforeach
@else
    @php $chunks = array_chunk($cards ?? [], $perPage); @endphp
    @foreach($chunks as $pageIndex => $pageCards)
        @if($pageIndex > 0)<div class="page-break"></div>@endif
        @include('fest.id-cards.partials.custom-sheet-page', ['pageCards' => $pageCards, 'gridLayout' => $gridLayout ?? null, 'backgroundUrl' => $backgroundUrl ?? null, 'fields' => $fields ?? [], 'cardWidthMm' => $cardWidthMm ?? 96, 'cardHeightMm' => $cardHeightMm ?? 72, 'pageHeightMm' => $pageHeightMm ?? null])
    @endforeach
@endif

@if(empty($cards) && empty($sections))
<p style="text-align:center;padding:20mm;color:#94a3b8;">No cards match your filters.</p>
@endif
</body>
</html>
