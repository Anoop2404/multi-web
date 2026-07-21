<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Premium ID Cards — {{ $eventTitle }}</title>
    <style>
        @page { size: A4 portrait; margin: 6mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #0f172a; background: #f1f5f9; }
        .sheet-title { text-align: center; font-size: 10px; font-weight: bold; color: #334155; margin-bottom: 2mm; }

        /* On-screen preview only: constrain each page to true A4 print width
           (210mm minus the 6mm @page margin on each side = 198mm) so the
           browser preview matches the printed/exported PDF exactly instead
           of stretching the grid across the full viewport width. */
        .page {
            width: 210mm;
            margin: 0 auto 8mm;
            background: #ffffff;
            padding: 3mm;
            box-shadow: 0 1mm 4mm rgba(15, 23, 42, 0.12);
        }
        @media print {
            .sheet-title { display: none !important; }
            body { background: #fff; }
            .page { width: auto; margin: 0; padding: 0; box-shadow: none; }
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
        .grid { width: 100%; border-collapse: separate; border-spacing: 2mm 3mm; }
        .grid td { width: 50%; vertical-align: top; padding: 0; }
        .page-break { page-break-after: always; }

        /* ========= Card ========= */
        .pcard {
            width: {{ \App\Support\FestIdCardTemplates::CARD_WIDTH_MM }}mm;
            height: {{ \App\Support\FestIdCardTemplates::CARD_HEIGHT_MM }}mm;
            border-radius: 3mm;
            overflow: hidden;
            page-break-inside: avoid;
            background: #ffffff;
            border: 0.4mm solid #042a5b;
            box-shadow: 0 1mm 3mm rgba(4, 42, 91, 0.12);
            display: flex;
            flex-direction: column;
            position: relative;
        }

        /* Header */
        .pcard__header {
            flex-shrink: 0;
            height: 18mm;
            background: linear-gradient(135deg, #042a5b 0%, #0a3d7a 100%);
            color: #ffffff;
            padding: 2mm 3mm 1mm;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
        }
        .pcard__brand {
            display: flex;
            align-items: center;
            gap: 2.2mm;
            flex: 1;
            min-width: 0;
        }
        .pcard__logo,
        .pcard__logo-fallback {
            width: 11mm;
            height: 11mm;
            border-radius: 50%;
            border: 0.4mm solid rgba(16, 185, 129, 0.6);
            background: #ffffff;
            object-fit: cover;
            flex-shrink: 0;
        }
        .pcard__logo-fallback {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #042a5b;
            font-size: 7px;
            font-weight: bold;
        }
        .pcard__cluster {
            font-size: 6.5px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: rgba(255,255,255,0.9);
            line-height: 1.1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcard__event {
            font-size: 12px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1.15;
            margin-top: 0.3mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcard__pass-ribbon {
            position: absolute;
            top: 0;
            right: 3mm;
            background: #059669;
            color: #ffffff;
            font-size: 6.5px;
            font-weight: 800;
            padding: 1mm 4mm 1.3mm;
            border-bottom-left-radius: 1.8mm;
            border-bottom-right-radius: 1.8mm;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        /* Wave separator */
        .pcard__wave-separator {
            height: 3mm;
            margin-top: -3mm;
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
            align-items: flex-start;
            gap: 2.5mm;
            padding: 1.5mm 3mm 1mm;
            background: #ffffff;
        }
        .pcard__portrait {
            width: 20mm;
            height: 25mm;
            border-radius: 1.5mm;
            border: 0.45mm solid #0d9488;
            overflow: hidden;
            background: #f0fdf4;
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
            font-size: 14px;
            font-weight: bold;
            color: #042a5b;
            background: #e0f2fe;
        }
        .pcard__info-col {
            flex: 1;
            min-width: 0;
        }
        .pcard__name {
            font-size: 11px;
            font-weight: 800;
            color: #042a5b;
            text-transform: uppercase;
            line-height: 1.15;
            margin-bottom: 1mm;
            word-wrap: break-word;
        }
        .pcard__meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .pcard__meta-table td {
            font-size: 6.8px;
            line-height: 1.35;
            padding: 0.2mm 0;
            vertical-align: middle;
        }

        /* Colored dot bullets matching reference */
        .pcard__meta-dot {
            width: 3.5mm;
            padding-right: 0.5mm;
        }
        .dot {
            display: inline-block;
            width: 2mm;
            height: 2mm;
            border-radius: 50%;
        }
        .dot--blue   { background: #3b82f6; }
        .dot--amber  { background: #f59e0b; }
        .dot--pink   { background: #ec4899; }
        .dot--red    { background: #ef4444; }
        .dot--teal   { background: #14b8a6; }
        .dot--orange { background: #f97316; }

        .pcard__meta-label {
            color: #475569;
            font-weight: 600;
            width: 13mm;
        }
        .pcard__meta-sep {
            color: #64748b;
            width: 1.8mm;
            text-align: center;
        }
        .pcard__meta-val {
            color: #0f172a;
            font-weight: 700;
        }

        /* QR column */
        .pcard__qr-col {
            width: 17mm;
            text-align: center;
            flex-shrink: 0;
            align-self: center;
        }
        .pcard__qr {
            width: 15.5mm;
            height: 15.5mm;
            background: #ffffff;
            border-radius: 1mm;
            border: 0.3mm solid #d1d5db;
            padding: 0.5mm;
            display: block;
            margin: 0 auto;
        }
        .pcard__qr-label {
            display: block;
            font-size: 4.5px;
            font-weight: 800;
            color: #10b981;
            letter-spacing: 0.06em;
            margin-top: 0.5mm;
            text-transform: uppercase;
        }

        /* Footer */
        .pcard__footer {
            flex-shrink: 0;
            height: 7mm;
            background: #042a5b;
            padding: 0 3mm;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .pcard__school-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.8mm;
            background: rgba(255,255,255,0.08);
            border: 0.2mm solid rgba(255,255,255,0.15);
            border-radius: 999px;
            padding: 0.6mm 2.5mm;
            max-width: 68%;
        }
        .pcard__school-icon { font-size: 5.5px; }
        .pcard__school-text {
            font-size: 5.8px;
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
            font-size: 5.8px;
            font-weight: 800;
            padding: 0.7mm 3mm;
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

@if(!empty($renderSections) && !empty($sections))
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
