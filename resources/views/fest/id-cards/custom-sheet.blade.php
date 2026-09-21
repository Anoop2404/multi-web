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
        .card__field--wrap {
            display: flex;
            align-items: center;
            white-space: normal;
            text-overflow: clip;
            overflow-wrap: anywhere;
        }
        .card__field--wrap > span {
            display: block;
            width: 100%;
        }
        .card__photo, .card__qr {
            position: absolute;
            object-fit: cover;
        }
        .card__photo {
            border-radius: 50%;
        }
        .card__item-text {
            position: absolute;
            height: 2.4%;
            display: flex; align-items: center;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .card__item-list {
            position: absolute;
            overflow: hidden;
        }
        .card__item-list table {
            width: 100%;
            height: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .card__item-list td {
            padding: 0;
            vertical-align: middle;
        }
        .card__item-list td:first-child:not(:last-child) { padding-right: 1.5%; }
        .card__item-list td + td { padding-left: 1.5%; }
        .card__item-list-row {
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
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
    @if(!($gridLayout ?? null))
        <p class="sheet-title">{{ $clusterName }} · {{ $eventTitle }} · {{ ucfirst($audience ?? 'participant') }} ID cards</p>
    @endif
@endif

@php
    // A grid layout dictates its own per-page count (cols × rows, e.g. a 10-up
    // die-cut sheet) — cardsPerPage only applies to the plain auto-flow table.
    $perPage = ($gridLayout ?? null) ? ($gridLayout['cols'] * $gridLayout['rows']) : max(1, $cardsPerPage ?? 4);
    $renderSections = ! empty($sections);
@endphp

@if($renderSections)
    @php $firstPageRendered = false; @endphp
    @foreach($sections as $sectionIndex => $section)
        @php
            $sectionTitle = $section['school_name'] ?? $section['item_title'] ?? 'Section';
            $chunks = array_chunk($section['cards'] ?? [], $perPage);
        @endphp
        @foreach($chunks as $pageIndex => $pageCards)
            @if($firstPageRendered)<div class="page-break"></div>@endif
            @php $firstPageRendered = true; @endphp
            @if(!($gridLayout ?? null) && ($showTitle ?? true) && $pageIndex === 0)
                <p class="section-title">{{ $sectionTitle }}</p>
            @endif
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
