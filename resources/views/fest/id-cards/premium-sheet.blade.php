<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Premium ID Cards — {{ $eventTitle }}</title>
    <style>
        @page { size: A4 portrait; margin: 4mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #0f172a; background: #f1f5f9; }
        .sheet-title { text-align: center; font-size: 10px; font-weight: bold; color: #334155; margin-bottom: 2mm; }
        @media print {
            .sheet-title { display: none !important; }
            body { background: #fff; }
        }
        .section-title {
            font-size: 9px;
            font-weight: bold;
            color: #0f3d7a;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin: 1.5mm 0 2mm;
            padding-bottom: 0.8mm;
            border-bottom: 0.3mm solid #cbd5e1;
        }
        .grid { width: 100%; border-collapse: separate; border-spacing: 2.5mm 2.5mm; }
        .grid td { width: 50%; vertical-align: top; padding: 0; }
        .page-break { page-break-after: always; }

        .pcard {
            width: {{ \App\Support\FestIdCardTemplates::CARD_WIDTH_MM }}mm;
            height: {{ \App\Support\FestIdCardTemplates::CARD_HEIGHT_MM }}mm;
            border-radius: 3mm;
            overflow: hidden;
            page-break-inside: avoid;
            background: #ffffff;
            border: 0.4mm solid #042a5b;
            box-shadow: 0 1mm 3mm rgba(4, 42, 91, 0.15);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        /* Header */
        .pcard__header {
            flex-shrink: 0;
            height: 22mm;
            background: #042a5b;
            color: #ffffff;
            padding: 2.5mm 3mm 1mm;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }
        .pcard__brand {
            display: flex;
            align-items: center;
            gap: 2mm;
            flex: 1;
            min-width: 0;
        }
        .pcard__logo,
        .pcard__logo-fallback {
            width: 12.5mm;
            height: 12.5mm;
            border-radius: 50%;
            border: 0.4mm solid #10b981;
            background: #ffffff;
            object-fit: cover;
            flex-shrink: 0;
        }
        .pcard__logo-fallback {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #042a5b;
            font-size: 8px;
            font-weight: bold;
        }
        .pcard__cluster {
            font-size: 7px;
            font-weight: 800;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #ffffff;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcard__event {
            font-size: 13px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.15;
            margin-top: 0.4mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcard__pass-ribbon {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            background: #059669;
            color: #ffffff;
            font-size: 7.5px;
            font-weight: 800;
            padding: 1.2mm 5mm 1.5mm;
            border-bottom-left-radius: 2mm;
            border-bottom-right-radius: 2mm;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .pcard__qr-wrap {
            text-align: center;
            flex-shrink: 0;
        }
        .pcard__qr {
            width: 12.5mm;
            height: 12.5mm;
            background: #ffffff;
            border-radius: 1mm;
            padding: 0.5mm;
            display: block;
        }
        .pcard__qr-label {
            display: block;
            font-size: 4.8px;
            font-weight: 800;
            color: #10b981;
            letter-spacing: 0.06em;
            margin-top: 0.5mm;
            text-transform: uppercase;
        }

        /* Wave separator */
        .pcard__wave-separator {
            height: 3.5mm;
            margin-top: -3.5mm;
            position: relative;
            z-index: 2;
        }
        .pcard__wave-separator svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        /* Body */
        .pcard__body {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 3mm;
            padding: 1mm 3mm 2mm;
            background: #ffffff;
            position: relative;
        }
        .pcard__portrait {
            width: 22mm;
            height: 27mm;
            border-radius: 1.8mm;
            border: 0.45mm solid #0d9488;
            overflow: hidden;
            background: #f8fafc;
            flex-shrink: 0;
        }
        .pcard__photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .pcard__initials {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: bold;
            color: #042a5b;
            background: #e0f2fe;
        }
        .pcard__info-col {
            flex: 1;
            min-width: 0;
            border-left: 0.3mm solid #cbd5e1;
            padding-left: 2.5mm;
        }
        .pcard__name {
            font-size: 12.5px;
            font-weight: 800;
            color: #042a5b;
            text-transform: uppercase;
            line-height: 1.15;
            margin-bottom: 1.2mm;
        }
        .pcard__meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .pcard__meta-table td {
            font-size: 7.2px;
            line-height: 1.35;
            padding: 0.2mm 0;
            vertical-align: middle;
        }
        .pcard__meta-icon {
            width: 3.5mm;
            font-size: 7px;
        }
        .pcard__meta-label {
            color: #475569;
            font-weight: 600;
            width: 14mm;
        }
        .pcard__meta-sep {
            color: #64748b;
            width: 2mm;
            text-align: center;
        }
        .pcard__meta-val {
            color: #0f172a;
            font-weight: 700;
        }

        /* Footer */
        .pcard__footer {
            flex-shrink: 0;
            height: 8mm;
            background: #042a5b;
            padding: 0 3mm;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .pcard__school-pill {
            display: inline-flex;
            align-items: center;
            gap: 1mm;
            background: #07264a;
            border: 0.2mm solid #1e3a8a;
            border-radius: 999px;
            padding: 0.8mm 2.5mm;
            max-width: 68%;
        }
        .pcard__school-icon { font-size: 6.5px; }
        .pcard__school-text {
            font-size: 6.5px;
            font-weight: 800;
            color: #ffffff;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcard__role-pill {
            background: #059669;
            color: #ffffff;
            font-size: 6.5px;
            font-weight: 800;
            padding: 0.8mm 2.8mm;
            border-radius: 999px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
@if($showTitle ?? true)
<p class="sheet-title">{{ $clusterName }} · {{ $eventTitle }} · Premium ID cards</p>
@endif

@php
    $cardBranding = [
        'clusterName' => $clusterName,
        'clusterLogoSrc' => $clusterLogoSrc ?? null,
        'clusterInitials' => collect(preg_split('/\s+/', trim($clusterName)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->join(''),
        'eventTitle' => $eventTitle,
    ];
@endphp

@if($renderSections)
    @foreach($sections as $sectionIndex => $section)
        @if($sectionIndex > 0)<div class="page-break"></div>@endif
        <p class="section-title">{{ $section['item_title'] ?? 'Item' }}</p>
        @php $chunks = array_chunk($section['cards'] ?? [], \App\Support\FestIdCardTemplates::CARDS_PER_PAGE); @endphp
        @foreach($chunks as $pageIndex => $pageCards)
            @if($pageIndex > 0)<div class="page-break"></div>@endif
            <table class="grid">
                @foreach(array_chunk($pageCards, 2) as $row)
                <tr>
                    @foreach($row as $card)
                    <td>@include('fest.id-cards.partials.premium-card', array_merge($cardBranding, ['card' => $card]))</td>
                    @endforeach
                    @if(count($row) === 1)<td></td>@endif
                </tr>
                @endforeach
            </table>
        @endforeach
    @endforeach
@else
    @php $chunks = array_chunk($cards ?? [], \App\Support\FestIdCardTemplates::CARDS_PER_PAGE); @endphp
    @foreach($chunks as $pageIndex => $pageCards)
        @if($pageIndex > 0)<div class="page-break"></div>@endif
        <table class="grid">
            @foreach(array_chunk($pageCards, 2) as $row)
            <tr>
                @foreach($row as $card)
                <td>@include('fest.id-cards.partials.premium-card', array_merge($cardBranding, ['card' => $card]))</td>
                @endforeach
                @if(count($row) === 1)<td></td>@endif
            </tr>
            @endforeach
        </table>
    @endforeach
@endif

</body>
</html>
