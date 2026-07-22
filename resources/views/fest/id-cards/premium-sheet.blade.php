<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Premium ID Cards — {{ $eventTitle }}</title>
    <style>
        @page { size: A4 landscape; margin: 6mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #0f172a; background: #f1f5f9; }
        .sheet-title { text-align: center; font-size: 10px; font-weight: bold; color: #334155; margin-bottom: 2mm; }

        .page {
            width: 285mm;
            margin: 0 auto 8mm;
            background: #ffffff;
        }
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
        .grid { width: 100%; border-collapse: separate; border-spacing: 2mm 4mm; }
        .grid td { width: 50%; vertical-align: top; padding: 0; }
        .page-break { page-break-after: always; }

        /* ========= Card Base (Flexbox for HTML Browser Preview) ========= */
        .pcard {
            width: {{ \App\Support\FestIdCardTemplates::CARD_WIDTH_MM }}mm;
            height: {{ \App\Support\FestIdCardTemplates::CARD_HEIGHT_MM }}mm;
            border-radius: 3mm;
            overflow: hidden;
            page-break-inside: avoid;
            background: #ffffff;
            border: 0.4mm solid #042a5b;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .pcard__header {
            flex-shrink: 0;
            height: 20mm;
            background: #042a5b;
            color: #ffffff;
            padding: 2.5mm 4mm 1mm;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .pcard__brand {
            display: flex;
            align-items: center;
            gap: 2.8mm;
            flex: 1;
            min-width: 0;
        }
        .pcard__logo,
        .pcard__logo-fallback {
            width: 13mm;
            height: 13mm;
            border-radius: 50%;
            border: 0.45mm solid rgba(16, 185, 129, 0.6);
            background: #ffffff;
            flex-shrink: 0;
            display: block;
        }
        .pcard__logo-fallback {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #042a5b;
            font-size: 8px;
            font-weight: bold;
            text-align: center;
            line-height: 13mm;
        }
        .pcard__brand-text {
            flex: 1;
            min-width: 0;
        }
        .pcard__cluster {
            font-size: 7.5px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.9);
            line-height: 1.2;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcard__event {
            font-size: 13.5px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.2;
            margin-top: 0.6mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcard__pass-ribbon {
            background: #059669;
            color: #ffffff;
            font-size: 6.5px;
            font-weight: 800;
            padding: 1.1mm 4mm 1.4mm;
            border-bottom-left-radius: 1.8mm;
            border-bottom-right-radius: 1.8mm;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            align-self: flex-start;
            flex-shrink: 0;
        }

        /* Wave separator */
        .pcard__wave-separator {
            height: 3.5mm;
            margin-top: -3.5mm;
            flex-shrink: 0;
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
            gap: 3.5mm;
            padding: 2.5mm 4mm;
            background: #ffffff;
        }
        .pcard__portrait {
            width: 26mm;
            height: 33mm;
            border-radius: 1.5mm;
            border: 0.5mm solid #0d9488;
            overflow: hidden;
            background: #f0fdf4;
            flex-shrink: 0;
        }
        .pcard__photo {
            width: 26mm;
            height: 33mm;
            display: block;
        }
        .pcard__initials {
            width: 26mm;
            height: 33mm;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: bold;
            color: #042a5b;
            background: #e0f2fe;
            text-align: center;
            line-height: 33mm;
        }
        .pcard__info-col {
            flex: 1;
            min-width: 0;
        }
        .pcard__name {
            font-size: 13.5px;
            font-weight: 800;
            color: #042a5b;
            text-transform: uppercase;
            line-height: 1.2;
            margin-bottom: 1.5mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcard__meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .pcard__meta-table td {
            font-size: 9.5px;
            line-height: 1.5;
            padding: 0.3mm 0;
            vertical-align: middle;
        }
        .pcard__meta-label {
            color: #475569;
            font-weight: 600;
            width: 17mm;
        }
        .pcard__meta-sep {
            color: #64748b;
            width: 2mm;
            text-align: center;
        }
        .pcard__meta-val {
            color: #0f172a;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* QR column */
        .pcard__qr-col {
            width: 20mm;
            text-align: center;
            flex-shrink: 0;
            align-self: center;
        }
        .pcard__qr {
            width: 18mm;
            height: 18mm;
            background: #ffffff;
            border-radius: 1.2mm;
            border: 0.35mm solid #d1d5db;
            padding: 0.6mm;
            display: block;
            margin: 0 auto;
        }
        .pcard__qr-label {
            display: block;
            font-size: 5px;
            font-weight: 800;
            color: #10b981;
            letter-spacing: 0.06em;
            margin-top: 0.7mm;
            text-transform: uppercase;
        }

        /* Footer */
        .pcard__footer {
            flex-shrink: 0;
            height: 8mm;
            background: #042a5b;
            padding: 0 4mm;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .pcard__school-pill {
            display: inline-flex;
            align-items: center;
            background: rgba(255,255,255,0.08);
            border: 0.2mm solid rgba(255,255,255,0.15);
            border-radius: 999px;
            padding: 0.8mm 3mm;
            max-width: 68%;
        }
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
            padding: 0.8mm 3.5mm;
            border-radius: 999px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        /* ========= DomPDF Table Layout Overrides (For PDF Generation Only) ========= */
        @if(!empty($isPdf))
        body { background: #ffffff !important; }
        .page { margin: 0 auto !important; }
        .pcard {
            display: table !important;
            width: {{ \App\Support\FestIdCardTemplates::CARD_WIDTH_MM }}mm !important;
            height: {{ \App\Support\FestIdCardTemplates::CARD_HEIGHT_MM }}mm !important;
            table-layout: fixed !important;
        }
        .pcard__header {
            display: table-row !important;
            height: 19mm !important;
        }
        .pcard__brand {
            display: table-cell !important;
            vertical-align: middle !important;
            width: 75% !important;
            padding-left: 4mm !important;
            padding-top: 2.5mm !important;
        }
        .pcard__logo, .pcard__logo-fallback {
            display: inline-block !important;
            vertical-align: middle !important;
        }
        .pcard__brand-text {
            display: inline-block !important;
            vertical-align: middle !important;
            margin-left: 2.5mm !important;
            width: 75% !important;
        }
        .pcard__pass-ribbon {
            display: table-cell !important;
            vertical-align: top !important;
            text-align: right !important;
            padding-right: 4mm !important;
            width: 25% !important;
        }
        .pcard__wave-separator {
            display: table-row !important;
            height: 3.5mm !important;
            background: #ffffff !important;
            border-top: 0.8mm solid #10b981 !important;
            margin-top: 0 !important;
        }
        .pcard__body {
            display: table !important;
            width: 100% !important;
            height: 55mm !important;
            table-layout: fixed !important;
            padding: 2mm 4mm !important;
        }
        .pcard__portrait {
            display: table-cell !important;
            vertical-align: middle !important;
            width: 26mm !important;
        }
        .pcard__info-col {
            display: table-cell !important;
            vertical-align: middle !important;
            padding-left: 3.5mm !important;
            padding-right: 1.5mm !important;
        }
        .pcard__qr-col {
            display: table-cell !important;
            vertical-align: middle !important;
            width: 20mm !important;
            text-align: center !important;
        }
        .pcard__footer {
            display: table !important;
            width: 100% !important;
            height: 8mm !important;
            padding: 0 4mm !important;
        }
        .pcard__school-pill {
            display: inline-block !important;
            vertical-align: middle !important;
        }
        .pcard__role-pill {
            display: inline-block !important;
            vertical-align: middle !important;
            float: right !important;
        }
        @endif
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

@if(!empty($sections))
    @foreach($sections as $sectionIndex => $section)
        @if($sectionIndex > 0)<div class="page-break"></div>@endif
        @php $chunks = array_chunk($section['cards'] ?? [], \App\Support\FestIdCardTemplates::CARDS_PER_PAGE); @endphp
        @foreach($chunks as $pageIndex => $pageCards)
            @if($pageIndex > 0)<div class="page-break"></div>@endif
            <div class="page">
                <p class="section-title">{{ $section['item_title'] ?? 'Item' }}</p>
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
            </div>
        @endforeach
    @endforeach
@else
    @php $chunks = array_chunk($cards ?? [], \App\Support\FestIdCardTemplates::CARDS_PER_PAGE); @endphp
    @foreach($chunks as $pageIndex => $pageCards)
        @if($pageIndex > 0)<div class="page-break"></div>@endif
        <div class="page">
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
        </div>
    @endforeach
@endif

</body>
</html>
