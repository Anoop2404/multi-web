<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Participant Pass — {{ $eventTitle }}</title>
    <style>
        /*
         * Two renderers can produce this page's PDF (see App\Support\PdfGenerator):
         * an external headless-Chromium service (PDF_CONVERTER_URL) when configured,
         * which gets the exact markup/CSS below — CSS Grid, gradients, multi-column
         * text, everything a real browser supports — or, when no such service is
         * configured, a DomPDF fallback that can't render any of that. $isPdf is true
         * only in the DomPDF-fallback case (see FestIdCardController::pdf()), so the
         * ".pdf-fallback" block further down carries a second, table-based card
         * design that's DomPDF-safe, and only ships when it's actually needed.
         */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        @page { size: A4 landscape; margin: 0; }
        html, body { font-family: Montserrat, Arial, DejaVu Sans, sans-serif; background: #eef2f7; color: #10213d; }
        .page-break { page-break-after: always; }

        /* =====================================================
           RICH CARD (browser preview + Chromium PDF conversion)
           4 cards per A4 LANDSCAPE page (2x2) — same proven page geometry
           as the Premium template (138x86mm cards). Every mm/pt value below
           is the old 10-per-page portrait design scaled up ~1.6x (138/85.6,
           86/54) to fill the bigger card instead of floating in empty space.
        ===================================================== */

        .a4-sheet {
            width: 297mm;
            height: 210mm;
            margin: 12px auto;
            padding: 6mm;
            background: #fff;
            display: grid;
            grid-template-columns: {{ \App\Support\FestIdCardTemplates::PASS_CARD_WIDTH_MM }}mm {{ \App\Support\FestIdCardTemplates::PASS_CARD_WIDTH_MM }}mm;
            grid-template-rows: repeat(2, {{ \App\Support\FestIdCardTemplates::PASS_CARD_HEIGHT_MM }}mm);
            column-gap: 2mm;
            row-gap: 4mm;
            box-shadow: 0 5px 30px rgba(0,0,0,.10);
            overflow: hidden;
        }

        .id-card {
            --primary: #073f82;
            --secondary: #1767b7;
            --accent: #ec1470;
            width: {{ \App\Support\FestIdCardTemplates::PASS_CARD_WIDTH_MM }}mm;
            height: {{ \App\Support\FestIdCardTemplates::PASS_CARD_HEIGHT_MM }}mm;
            border: .45mm solid #bdd0e5;
            border-radius: 4.5mm;
            background: linear-gradient(135deg, #ffffff 0%, #ffffff 65%, #f4f9ff 100%);
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
            page-break-inside: avoid;
        }

        .card-header {
            height: 16mm;
            min-height: 16mm;
            display: grid;
            grid-template-columns: 13mm 1fr minmax(0, 48mm);
            align-items: center;
            padding: 2.2mm 4mm;
            border-bottom: .4mm solid #dde7f1;
            position: relative;
            background: linear-gradient(90deg, #ffffff 0%, #ffffff 72%, #eef6ff 100%);
        }
        .card-header::before {
            content: "";
            position: absolute;
            left: 0; top: 0;
            width: 100%; height: 1.6mm;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent), #ff9e24);
        }
        .sahodaya-logo { width: 10.9mm; height: 10.9mm; object-fit: contain; }
        .branding { padding-left: 2.2mm; min-width: 0; }
        .sahodaya-name {
            font-size: 9.6pt; font-weight: 800; color: var(--primary); text-transform: uppercase;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .sahodaya-tagline {
            margin-top: .5mm; font-size: 10.6pt; font-weight: 700; color: var(--accent);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .event-branding { text-align: right; padding-right: 1.6mm; min-width: 0; }
        .event-name {
            font-size: 9.9pt; line-height: 1.1; font-weight: 900; color: var(--accent); text-transform: uppercase;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .event-year {
            margin-top: .5mm; font-size: 6.4pt; line-height: 1.1; font-weight: 700; color: var(--primary);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .card-content {
            flex: 1;
            min-height: 0;
            display: grid;
            grid-template-columns: 30.5mm minmax(0, 1fr);
            gap: 3.2mm;
            padding: 2.9mm 4mm 1.9mm;
        }

        .photo-column { min-width: 0; }
        .photo-box {
            width: 30.5mm; height: 38.5mm;
            border-radius: 3.2mm;
            overflow: hidden;
            background: linear-gradient(145deg, #e5edf7, #c8daed);
            border: .4mm solid #cddaea;
        }
        .student-photo { width: 100%; height: 100%; display: block; object-fit: cover; }
        .photo-fallback {
            width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
            font-size: 18px; font-weight: 800; color: var(--primary);
        }
        .participant-label {
            margin-top: 1.6mm; width: 100%; text-align: center; padding: 1.3mm .8mm;
            border-radius: 1.6mm; background: var(--accent); color: #fff;
            font-size: 5.8pt; font-weight: 800; text-transform: uppercase; letter-spacing: .32mm;
        }

        .student-details { min-width: 0; }
        .student-name {
            font-size: 10.6pt; line-height: 1.15; font-weight: 800; color: #0b1e3a;
            display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden;
        }
        .school-name {
            margin-top: 1mm; font-size: 7pt; line-height: 1.15; font-weight: 600; color: #53667e;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .meta-grid { margin-top: 2.1mm; display: grid; grid-template-columns: 1fr 1fr; gap: 1.1mm 1.6mm; }
        .meta-box { min-width: 0; padding: 1.1mm 1.6mm; background: #f2f7fc; border-radius: 1.6mm; }
        .meta-label { display: block; font-size: 5pt; font-weight: 700; text-transform: uppercase; color: #8391a4; margin-bottom: .5mm; }
        .meta-value {
            display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden;
            font-size: 6.9pt; line-height: 1.2; font-weight: 700; color: #173557;
        }
        .category-value { color: var(--primary); }

        .items-section { margin-top: 1.9mm; padding-top: 1.6mm; border-top: .32mm solid #dce6f0; }
        .items-title { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.1mm; }
        .items-title strong { font-size: 6.9pt; color: var(--primary); text-transform: uppercase; }
        .item-count { padding: .6mm 1.9mm; border-radius: 4.8mm; background: #e4f1ff; color: var(--primary); font-size: 5.4pt; font-weight: 800; }
        .item-list {
            list-style: none; counter-reset: event-item;
            column-count: 2; column-gap: 3.2mm;
        }
        .item-list li {
            counter-increment: event-item; break-inside: avoid;
            display: flex; gap: 1mm; margin-bottom: 1mm;
            font-size: 7.4pt; line-height: 1.15; font-weight: 600; color: #253850;
        }
        .item-list li::before { content: counter(event-item) "."; flex-shrink: 0; font-weight: 800; color: var(--primary); }

        .card-footer {
            height: 6.4mm; min-height: 6.4mm; position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: center; color: #fff;
            background: linear-gradient(90deg, var(--primary), #135ea6 45%, var(--accent) 82%, #ff9d20);
        }
        .footer-text { position: relative; z-index: 2; font-size: 5.1pt; font-weight: 700; text-transform: uppercase; letter-spacing: .4mm; }
        .corner-decoration {
            position: absolute; width: 30.5mm; height: 30.5mm; right: -16mm; bottom: -17.6mm;
            border-radius: 50%; border: 4.8mm solid rgba(255,255,255,.20);
        }

        @media print {
            html, body { width: 297mm; height: auto; background: #fff; }
            .a4-sheet { margin: 0; box-shadow: none; }
        }

        /* =====================================================
           DOMPDF-FALLBACK CARD (table layout — no grid/flex/gradients/
           multi-column, none of which DomPDF renders)
        ===================================================== */
        @if(!empty($isPdf))
        .grid { width: 100%; border-collapse: separate; border-spacing: 2mm 4mm; table-layout: fixed; }
        .grid td { width: 50%; vertical-align: top; padding: 0; }

        .pass-card-pdf {
            width: {{ \App\Support\FestIdCardTemplates::PASS_CARD_WIDTH_MM }}mm;
            height: {{ \App\Support\FestIdCardTemplates::PASS_CARD_HEIGHT_MM }}mm;
            border: 0.48mm solid #bdd0e5;
            border-radius: 4.5mm;
            overflow: hidden;
            page-break-inside: avoid;
            background: #ffffff;
        }
        .pass-card-pdf__header {
            display: table; width: 100%; table-layout: fixed; border-collapse: collapse;
            border-bottom: 0.4mm solid #dde7f1; background: #f7fafd;
        }
        .pass-card-pdf__logo-cell, .pass-card-pdf__brand-cell, .pass-card-pdf__event-cell {
            display: table-cell; vertical-align: middle; padding: 2.1mm 2.4mm;
        }
        .pass-card-pdf__logo-cell { width: 13mm; }
        .pass-card-pdf__logo { width: 10.4mm; height: 10.4mm; object-fit: contain; }
        .pass-card-pdf__brand-cell { width: 55%; }
        .pass-card-pdf__org-name {
            font-size: 9.6pt; font-weight: 800; color: #073f82; text-transform: uppercase;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pass-card-pdf__tagline {
            margin-top: 0.5mm; font-size: 10.6pt; font-weight: 700; color: #ec1470;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pass-card-pdf__event-cell { text-align: right; }
        .pass-card-pdf__event-name {
            font-size: 9.9pt; font-weight: 900; color: #ec1470; text-transform: uppercase;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pass-card-pdf__event-year {
            margin-top: 0.5mm; font-size: 6.4pt; font-weight: 700; color: #073f82;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pass-card-pdf__body { display: table; width: 100%; table-layout: fixed; padding: 2.6mm 3.5mm 1.6mm; }
        .pass-card-pdf__photo-cell { display: table-cell; width: 30.5mm; vertical-align: top; padding: 2.6mm 0 1.6mm 3.5mm; }
        .pass-card-pdf__photo-box {
            width: 30.5mm; height: 38.5mm; border-radius: 2.6mm; overflow: hidden;
            background: #dce8f7; border: 0.4mm solid #cddaea;
        }
        .pass-card-pdf__photo { width: 100%; height: 100%; }
        .pass-card-pdf__photo-fallback {
            width: 100%; height: 100%; text-align: center; line-height: 38.5mm;
            font-size: 18px; font-weight: bold; color: #073f82;
        }
        .pass-card-pdf__role-label {
            margin-top: 1.6mm; text-align: center; padding: 1.1mm 0; border-radius: 1.6mm;
            background: #ec1470; color: #fff; font-size: 5.4pt; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.24mm;
        }
        .pass-card-pdf__info-cell { display: table-cell; vertical-align: top; padding: 2.6mm 3.5mm 1.6mm 3.2mm; }
        .pass-card-pdf__name {
            font-size: 11.2pt; font-weight: 800; color: #0b1e3a;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pass-card-pdf__school {
            margin-top: 0.8mm; font-size: 7pt; font-weight: 600; color: #53667e;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pass-card-pdf__meta { width: 100%; table-layout: fixed; border-collapse: separate; border-spacing: 1.6mm 1mm; margin-top: 1.6mm; }
        .pass-card-pdf__meta-box { width: 50%; background: #f2f7fc; border-radius: 1.6mm; padding: 1mm 1.6mm; vertical-align: top; }
        .pass-card-pdf__meta-label { display: block; font-size: 5pt; font-weight: 700; text-transform: uppercase; color: #8391a4; }
        .pass-card-pdf__meta-value {
            display: block; font-size: 6.9pt; font-weight: 700; color: #173557;
            white-space: nowrap; overflow: hidden;
        }
        .pass-card-pdf__meta-value--accent { color: #073f82; }
        .pass-card-pdf__items { margin-top: 1.6mm; padding-top: 1.3mm; border-top: 0.32mm solid #dce6f0; }
        .pass-card-pdf__items-title { width: 100%; table-layout: fixed; border-collapse: collapse; margin-bottom: 0.8mm; }
        .pass-card-pdf__items-title strong { font-size: 6.9pt; color: #073f82; text-transform: uppercase; }
        .pass-card-pdf__items-count-cell { text-align: right; }
        .pass-card-pdf__items-count {
            display: inline-block; padding: 0.5mm 1.8mm; border-radius: 4.8mm; background: #e4f1ff;
            color: #073f82; font-size: 5.4pt; font-weight: 800;
        }
        .pass-card-pdf__items-table { width: 100%; table-layout: fixed; border-collapse: collapse; }
        .pass-card-pdf__items-col { width: 50%; vertical-align: top; padding-right: 1.6mm; }
        .pass-card-pdf__items-table ol { margin: 0; padding-left: 4.8mm; }
        .pass-card-pdf__items-table li { font-size: 7pt; font-weight: 600; color: #253850; line-height: 1.4; }
        .pass-card-pdf__footer {
            height: 5.8mm; text-align: center; color: #fff; font-size: 4.5pt; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.32mm; line-height: 5.8mm;
            background: #073f82;
        }
        @endif
    </style>
</head>
<body>
@php
    $renderSections = ! empty($sections);
    $pagePartial = ! empty($isPdf) ? 'fest.id-cards.partials.pass-card-page-pdf' : 'fest.id-cards.partials.pass-card-page';
@endphp

@if($renderSections)
    @foreach($sections as $sectionIndex => $section)
        @if($sectionIndex > 0)<div class="page-break"></div>@endif
        @php $chunks = array_chunk($section['cards'] ?? [], \App\Support\FestIdCardTemplates::PASS_CARDS_PER_PAGE); @endphp
        @foreach($chunks as $pageIndex => $pageCards)
            @if($pageIndex > 0)<div class="page-break"></div>@endif
            @include($pagePartial, ['pageCards' => $pageCards, 'clusterName' => $clusterName, 'clusterLogoSrc' => $clusterLogoSrc ?? null, 'eventTitle' => $eventTitle])
        @endforeach
    @endforeach
@else
    @php $chunks = array_chunk($cards ?? [], \App\Support\FestIdCardTemplates::PASS_CARDS_PER_PAGE); @endphp
    @foreach($chunks as $pageIndex => $pageCards)
        @if($pageIndex > 0)<div class="page-break"></div>@endif
        @include($pagePartial, ['pageCards' => $pageCards, 'clusterName' => $clusterName, 'clusterLogoSrc' => $clusterLogoSrc ?? null, 'eventTitle' => $eventTitle])
    @endforeach
@endif

@if(empty($cards) && empty($sections))
<p style="text-align:center;padding:20mm;color:#94a3b8;">No cards match your filters.</p>
@endif
</body>
</html>
