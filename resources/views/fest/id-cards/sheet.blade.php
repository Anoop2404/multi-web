<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ID Cards — {{ $eventTitle }}</title>
    <style>
        @include('partials.id-card-base-styles')
        @page { size: A4 portrait; margin: 6mm; }
        .sheet-title { text-align: center; font-size: 11px; font-weight: bold; color: #475569; margin-bottom: 4mm; }
        .grid { width: 100%; border-collapse: separate; border-spacing: 3mm 3mm; }
        .grid td { width: 50%; vertical-align: top; padding: 0; }
        .card {
            width: 96mm;
            height: 72mm;
            aspect-ratio: 4 / 3;
            border: 1px solid #cbd5e1;
            border-radius: 2.5mm;
            overflow: hidden;
            page-break-inside: avoid;
            background: #fff;
            position: relative;
        }
        .card__head {
            display: table;
            width: 100%;
            padding: 2mm 2.5mm;
            color: #fff;
        }
        .card__head--student { background: #0f3d7a; }
        .card__head--volunteer { background: #047857; }
        .card__head--staff { background: #7c2d12; }
        .card__org { display: table-cell; font-size: 6.5px; font-weight: bold; letter-spacing: 0.04em; text-transform: uppercase; vertical-align: middle; width: 58%; }
        .card__qr-head { display: table-cell; width: 12mm; vertical-align: middle; text-align: right; }
        .card__qr-head img { width: 10mm; height: 10mm; }
        .card__body { display: table; width: 100%; padding: 2.5mm 2.5mm 1.5mm; min-height: 28mm; }
        .card__avatar {
            display: table-cell;
            width: 18mm;
            height: 18mm;
            vertical-align: top;
        }
        .card__photo {
            width: 18mm;
            height: 18mm;
            border-radius: 50%;
            object-fit: cover;
        }
        .card__info { display: table-cell; vertical-align: top; padding-left: 2.5mm; padding-right: 1mm; }
        .card__name { font-size: 10px; font-weight: bold; line-height: 1.2; margin-bottom: 0.8mm; }
        .card__sub { font-size: 7px; color: #475569; line-height: 1.25; }
        .card__detail { font-size: 6.5px; color: #64748b; margin-top: 0.5mm; }
        .card__members { margin-top: 1mm; }
        .card__member { font-size: 5.8px; color: #334155; line-height: 1.35; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card__member-role { color: #94a3b8; font-size: 5.5px; }
        .card__ids {
            display: table;
            width: 100%;
            border-top: 0.4mm solid #e2e8f0;
            padding: 1.2mm 2.5mm;
            background: #f8fafc;
        }
        .card__id-block { display: table-cell; width: 50%; vertical-align: top; }
        .card__id-value { font-size: 9px; font-weight: bold; font-family: DejaVu Sans Mono, monospace; color: #0f3d7a; }
        .card__foot {
            font-size: 5.5px;
            text-align: center;
            color: #64748b;
            padding: 0.8mm 2mm 1.5mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
@if($showTitle ?? true)
<p class="sheet-title">{{ $clusterName }} · {{ $eventTitle }} · {{ ucfirst($audience ?? 'participant') }} ID cards</p>
@endif

@php
    $renderSections = ! empty($sections);
@endphp

@if($renderSections)
    @foreach($sections as $sectionIndex => $section)
        @if($sectionIndex > 0)<div class="page-break"></div>@endif
        <p class="sheet-title" style="margin-top: 0;">{{ $section['item_title'] ?? 'Item' }}</p>
        @php $chunks = array_chunk($section['cards'] ?? [], \App\Support\FestIdCardTemplates::CARDS_PER_PAGE); @endphp
        @foreach($chunks as $pageIndex => $pageCards)
            @if($pageIndex > 0)<div class="page-break"></div>@endif
            @include('fest.id-cards.partials.standard-card-page', ['pageCards' => $pageCards, 'clusterName' => $clusterName])
        @endforeach
    @endforeach
@else
    @php $chunks = array_chunk($cards ?? [], \App\Support\FestIdCardTemplates::CARDS_PER_PAGE); @endphp
    @foreach($chunks as $pageIndex => $pageCards)
        @if($pageIndex > 0)<div class="page-break"></div>@endif
        @include('fest.id-cards.partials.standard-card-page', ['pageCards' => $pageCards, 'clusterName' => $clusterName])
    @endforeach
@endif

@if(empty($cards) && empty($sections))
<p style="text-align:center;padding:20mm;color:#94a3b8;">No cards match your filters.</p>
@endif
</body>
</html>
