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
        .grid { width: 100%; border-collapse: separate; border-spacing: 3mm 3mm; }
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
            height: 19mm;
            background: #042a5b;
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
            gap: 2mm;
            flex: 1;
            min-width: 0;
        }
        .pcard__logo,
        .pcard__logo-fallback {
            width: 11.5mm;
            height: 11.5mm;
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
            font-size: 7.5px;
            font-weight: bold;
        }
        .pcard__cluster {
            font-size: 6.8px;
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
            font-size: 12.5px;
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
            right: 12mm;
            background: #059669;
            color: #ffffff;
            font-size: 7px;
            font-weight: 800;
            padding: 1.2mm 4.5mm 1.5mm;
            border-bottom-left-radius: 1.8mm;
            border-bottom-right-radius: 1.8mm;
            letter-spacing: 0.08em;
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
            align-items: flex-start;
            gap: 2.5mm;
            padding: 1.5mm 3mm 1mm;
            background: #ffffff;
            position: relative;
        }
        .pcard__portrait {
            width: 21mm;
            height: 26mm;
            border-radius: 1.5mm;
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
            font-size: 15px;
            font-weight: bold;
            color: #042a5b;
            background: #e0f2fe;
        }
        .pcard__info-col {
            flex: 1;
            min-width: 0;
            padding-right: 1mm;
        }
        .pcard__name {
            font-size: 12px;
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
            padding: 0.15mm 0;
            vertical-align: top;
        }
        .pcard__meta-icon {
            width: 3.2mm;
            font-size: 6.5px;
        }
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
        .pcard__qr-col {
            width: 16mm;
            text-align: center;
            flex-shrink: 0;
            align-self: center;
        }
        .pcard__qr {
            width: 15mm;
            height: 15mm;
            background: #ffffff;
            border-radius: 1mm;
            border: 0.3mm solid #cbd5e1;
            padding: 0.4mm;
            display: block;
            margin: 0 auto;
        }
        .pcard__qr-label {
            display: block;
            font-size: 4.5px;
            font-weight: 800;
            color: #10b981;
            letter-spacing: 0.06em;
            margin-top: 0.4mm;
            text-transform: uppercase;
        }

        /* Footer */
        .pcard__footer {
            flex-shrink: 0;
            height: 7.5mm;
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
            max-width: 70%;
        }
        .pcard__school-icon { font-size: 6px; }
        .pcard__school-text {
            font-size: 6px;
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
            font-size: 6px;
            font-weight: 800;
            padding: 0.8mm 3mm;
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
