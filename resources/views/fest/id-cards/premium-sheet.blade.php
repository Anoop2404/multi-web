<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Premium ID Cards — {{ $eventTitle }}</title>
    <style>
        @page { size: A4 landscape; margin: 6mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #0f172a; background: #ffffff; }
        .sheet-title { text-align: center; font-size: 10px; font-weight: bold; color: #334155; margin-bottom: 2mm; }

        .page {
            width: 285mm;
            margin: 0 auto;
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

        /* ========= Card (DomPDF Compatible — Matches Vue Preview Tile) ========= */
        .pcard {
            width: {{ \App\Support\FestIdCardTemplates::CARD_WIDTH_MM }}mm;
            height: {{ \App\Support\FestIdCardTemplates::CARD_HEIGHT_MM }}mm;
            border-radius: 3mm;
            overflow: hidden;
            page-break-inside: avoid;
            background: #ffffff;
            border: 0.4mm solid #042a5b;
        }

        /* ---- Header ---- */
        .pcard__header {
            display: table;
            width: 100%;
            height: 19mm;
            background: #042a5b;
            color: #ffffff;
            padding: 2.5mm 3.5mm 1mm;
        }
        .pcard__brand-cell {
            display: table-cell;
            vertical-align: middle;
            width: 72%;
        }
        .pcard__logo-cell {
            display: table-cell;
            vertical-align: middle;
            width: 13mm;
        }
        .pcard__logo,
        .pcard__logo-fallback {
            width: 12mm;
            height: 12mm;
            border-radius: 50%;
            border: 0.4mm solid #10b981;
            background: #ffffff;
            display: block;
        }
        .pcard__logo-fallback {
            text-align: center;
            line-height: 12mm;
            color: #042a5b;
            font-size: 7px;
            font-weight: bold;
        }
        .pcard__text-cell {
            display: table-cell;
            vertical-align: middle;
            padding-left: 2.5mm;
        }
        .pcard__cluster {
            font-size: 6.5px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #cbd5e1;
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcard__event {
            font-size: 11.5px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.15;
            margin-top: 0.5mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcard__ribbon-cell {
            display: table-cell;
            vertical-align: top;
            text-align: right;
            width: 28%;
        }
        .pcard__pass-ribbon {
            display: inline-block;
            background: #059669;
            color: #ffffff;
            font-size: 6px;
            font-weight: 800;
            padding: 1.2mm 3.5mm;
            border-bottom-left-radius: 1.8mm;
            border-bottom-right-radius: 1.8mm;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        /* ---- Divider ---- */
        .pcard__divider {
            height: 1mm;
            background: #10b981;
            width: 100%;
        }

        /* ---- Body ---- */
        .pcard__body {
            display: table;
            width: 100%;
            padding: 2.5mm 3.5mm 1.5mm;
            background: #ffffff;
        }
        .pcard__portrait-cell {
            display: table-cell;
            vertical-align: top;
            width: 26mm;
        }
        .pcard__portrait {
            width: 25mm;
            height: 32mm;
            border-radius: 1.5mm;
            border: 0.4mm solid #0d9488;
            overflow: hidden;
            background: #f0fdf4;
        }
        .pcard__photo {
            width: 25mm;
            height: 32mm;
            display: block;
        }
        .pcard__initials {
            width: 25mm;
            height: 32mm;
            text-align: center;
            line-height: 32mm;
            font-size: 16px;
            font-weight: bold;
            color: #042a5b;
            background: #e0f2fe;
        }
        .pcard__info-cell {
            display: table-cell;
            vertical-align: top;
            padding-left: 3mm;
            padding-right: 1.5mm;
        }
        .pcard__name {
            font-size: 11.5px;
            font-weight: 800;
            color: #042a5b;
            text-transform: uppercase;
            line-height: 1.2;
            margin-bottom: 1mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcard__meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .pcard__meta-table td {
            font-size: 7.5px;
            line-height: 1.45;
            padding: 0.25mm 0;
            vertical-align: middle;
        }
        .pcard__meta-label {
            color: #475569;
            font-weight: 600;
            width: 16mm;
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

        /* ---- QR ---- */
        .pcard__qr-cell {
            display: table-cell;
            vertical-align: top;
            width: 20mm;
            text-align: center;
        }
        .pcard__qr {
            width: 18mm;
            height: 18mm;
            background: #ffffff;
            border-radius: 1mm;
            border: 0.3mm solid #cbd5e1;
            padding: 0.5mm;
            display: block;
            margin: 0 auto;
        }
        .pcard__qr-label {
            display: block;
            font-size: 4.8px;
            font-weight: 800;
            color: #10b981;
            letter-spacing: 0.05em;
            margin-top: 0.6mm;
            text-transform: uppercase;
        }

        /* ---- Footer ---- */
        .pcard__footer {
            display: table;
            width: 100%;
            height: 8mm;
            background: #042a5b;
            padding: 0 3.5mm;
        }
        .pcard__school-cell {
            display: table-cell;
            vertical-align: middle;
            width: 70%;
        }
        .pcard__school-pill {
            display: inline-block;
            background: #0b376d;
            border: 0.2mm solid #1e4d88;
            border-radius: 3mm;
            padding: 0.6mm 2.5mm;
            max-width: 95%;
        }
        .pcard__school-text {
            font-size: 6.2px;
            font-weight: 800;
            color: #ffffff;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: block;
        }
        .pcard__role-cell {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            width: 30%;
        }
        .pcard__role-pill {
            display: inline-block;
            background: #059669;
            color: #ffffff;
            font-size: 6.2px;
            font-weight: 800;
            padding: 0.6mm 3mm;
            border-radius: 3mm;
            letter-spacing: 0.05em;
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
