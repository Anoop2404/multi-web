<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Premium ID Cards — {{ $eventTitle }}</title>
    <style>
        @page { size: A4 portrait; margin: 5mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #0f172a; background: #f1f5f9; }
        .sheet-title { text-align: center; font-size: 11px; font-weight: bold; color: #334155; margin-bottom: 3mm; }
        .section-title {
            font-size: 9px;
            font-weight: bold;
            color: #0f3d7a;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin: 2mm 0 2.5mm;
            padding-bottom: 1mm;
            border-bottom: 0.3mm solid #cbd5e1;
        }
        .grid { width: 100%; border-collapse: separate; border-spacing: 2.5mm 2.5mm; }
        .grid td { width: 50%; vertical-align: top; padding: 0; }
        .page-break { page-break-after: always; }

        .pcard {
            width: 85.6mm;
            height: 54mm;
            border-radius: 3mm;
            overflow: hidden;
            page-break-inside: avoid;
            background: #fff;
            position: relative;
            border: 0.35mm solid #cbd5e1;
            box-shadow: 0 0.5mm 1.5mm rgba(15, 61, 122, 0.08);
        }
        .pcard__stripe {
            height: 1.2mm;
            background: linear-gradient(90deg, #c9a227 0%, #f4e4a6 35%, #c9a227 70%, #8b6914 100%);
        }
        .pcard__head {
            display: table;
            width: 100%;
            padding: 1.8mm 2.5mm 1.5mm;
            background: linear-gradient(135deg, #0f3d7a 0%, #1e5aa8 55%, #0f3d7a 100%);
            color: #fff;
        }
        .pcard--volunteer .pcard__head { background: linear-gradient(135deg, #065f46 0%, #047857 100%); }
        .pcard--staff .pcard__head { background: linear-gradient(135deg, #7c2d12 0%, #9a3412 100%); }
        .pcard__head-left { display: table-cell; vertical-align: middle; width: 62%; }
        .pcard__head-right { display: table-cell; vertical-align: middle; text-align: right; }
        .pcard__cluster {
            font-size: 5.8px;
            font-weight: bold;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            opacity: 0.92;
            line-height: 1.2;
        }
        .pcard__event {
            font-size: 6.5px;
            margin-top: 0.4mm;
            opacity: 0.85;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 48mm;
        }
        .pcard__role {
            display: block;
            font-size: 6.5px;
            font-weight: bold;
            letter-spacing: 0.1em;
            margin-bottom: 0.8mm;
        }
        .pcard__qr {
            width: 11mm;
            height: 11mm;
            background: #fff;
            border-radius: 0.8mm;
            padding: 0.3mm;
        }
        .pcard__pass-badge,
        .pcard__item-badge {
            font-size: 5.5px;
            font-weight: bold;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            text-align: center;
            padding: 0.7mm 2mm;
        }
        .pcard__pass-badge {
            background: #ecfdf5;
            color: #047857;
            border-bottom: 0.2mm solid #a7f3d0;
        }
        .pcard__item-badge {
            background: #eff6ff;
            color: #1e40af;
            border-bottom: 0.2mm solid #bfdbfe;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcard__body { display: table; width: 100%; padding: 1.8mm 2.5mm 1mm; }
        .pcard__body--team { display: block; }
        .pcard__avatar-wrap {
            display: table-cell;
            width: 16mm;
            vertical-align: top;
        }
        .pcard__photo,
        .pcard__initials {
            width: 15mm;
            height: 15mm;
            border-radius: 50%;
            border: 0.5mm solid #c9a227;
        }
        .pcard__photo { object-fit: cover; }
        .pcard__initials {
            background: #e2e8f0;
            color: #0f3d7a;
            font-size: 12px;
            font-weight: bold;
            text-align: center;
            line-height: 14mm;
        }
        .pcard__info { display: table-cell; vertical-align: top; padding-left: 2mm; }
        .pcard__name { font-size: 10.5px; font-weight: bold; line-height: 1.15; color: #0f172a; }
        .pcard__sub { font-size: 7px; color: #475569; margin-top: 0.5mm; line-height: 1.2; }
        .pcard__detail { font-size: 6.5px; color: #64748b; margin-top: 0.5mm; line-height: 1.25; }
        .pcard__schedule { font-size: 6px; color: #0f3d7a; margin-top: 0.6mm; font-weight: bold; }
        .pcard__items {
            margin: 0.8mm 0 0 3mm;
            padding: 0;
            font-size: 6px;
            color: #334155;
            line-height: 1.35;
        }
        .pcard__items-more { color: #94a3b8; list-style: none; margin-left: -3mm; }
        .pcard__members { margin-top: 0.8mm; }
        .pcard__member { font-size: 5.8px; color: #334155; line-height: 1.35; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .pcard__member--more { color: #94a3b8; }
        .pcard__ids {
            display: table;
            width: 100%;
            border-top: 0.35mm solid #e2e8f0;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 1.2mm 2.5mm;
        }
        .pcard__id-block { display: table-cell; width: 50%; vertical-align: top; }
        .pcard__id-label {
            display: block;
            font-size: 5px;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: #94a3b8;
        }
        .pcard__id-value {
            display: block;
            font-size: 9.5px;
            font-weight: bold;
            font-family: DejaVu Sans Mono, monospace;
            color: #0f3d7a;
            margin-top: 0.3mm;
        }
        .pcard__id-value--sm { font-size: 7.5px; }
        .pcard__foot {
            font-size: 5.5px;
            text-align: center;
            color: #64748b;
            padding: 0.6mm 2mm 1.2mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcard--event-pass .pcard__stripe {
            background: linear-gradient(90deg, #047857 0%, #6ee7b7 50%, #047857 100%);
        }
    </style>
</head>
<body>
@if($showTitle ?? true)
<p class="sheet-title">{{ $clusterName }} · {{ $eventTitle }} · Premium ID cards</p>
@endif

@php
    $renderSections = ! empty($sections);
@endphp

@if($renderSections)
    @foreach($sections as $sectionIndex => $section)
        @if($sectionIndex > 0)<div class="page-break"></div>@endif
        <p class="section-title">{{ $section['item_title'] ?? 'Item' }}</p>
        @php $chunks = array_chunk($section['cards'] ?? [], 10); @endphp
        @foreach($chunks as $pageIndex => $pageCards)
            @if($pageIndex > 0)<div class="page-break"></div>@endif
            <table class="grid">
                @foreach(array_chunk($pageCards, 2) as $row)
                <tr>
                    @foreach($row as $card)
                    <td>@include('fest.id-cards.partials.premium-card', ['card' => $card, 'clusterName' => $clusterName, 'eventTitle' => $eventTitle])</td>
                    @endforeach
                    @if(count($row) === 1)<td></td>@endif
                </tr>
                @endforeach
            </table>
        @endforeach
    @endforeach
@else
    @php $chunks = array_chunk($cards ?? [], 10); @endphp
    @foreach($chunks as $pageIndex => $pageCards)
        @if($pageIndex > 0)<div class="page-break"></div>@endif
        <table class="grid">
            @foreach(array_chunk($pageCards, 2) as $row)
            <tr>
                @foreach($row as $card)
                <td>@include('fest.id-cards.partials.premium-card', ['card' => $card, 'clusterName' => $clusterName, 'eventTitle' => $eventTitle])</td>
                @endforeach
                @if(count($row) === 1)<td></td>@endif
            </tr>
            @endforeach
        </table>
    @endforeach
@endif

@if(empty($cards) && empty($sections))
<p style="text-align:center;padding:20mm;color:#94a3b8;">No cards match your filters.</p>
@endif
</body>
</html>
