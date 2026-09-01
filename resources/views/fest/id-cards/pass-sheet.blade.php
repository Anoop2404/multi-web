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
        @page { size: A4 portrait; margin: 0; }
        html, body { font-family: Montserrat, Arial, DejaVu Sans, sans-serif; background: #eef2f7; color: #10213d; }
        .sheet-title { text-align: center; font-size: 11px; font-weight: bold; color: #475569; padding: 4mm 0; }
        .page-break { page-break-after: always; }

        /* =====================================================
           RICH CARD (browser preview + Chromium PDF conversion)
        ===================================================== */

        .a4-sheet {
            width: 210mm;
            height: 297mm;
            margin: 12px auto;
            padding: 9.5mm 16.9mm;
            background: #fff;
            display: grid;
            grid-template-columns: {{ \App\Support\FestIdCardTemplates::PASS_CARD_WIDTH_MM }}mm {{ \App\Support\FestIdCardTemplates::PASS_CARD_WIDTH_MM }}mm;
            grid-template-rows: repeat(5, {{ \App\Support\FestIdCardTemplates::PASS_CARD_HEIGHT_MM }}mm);
            column-gap: 5mm;
            row-gap: 2mm;
            box-shadow: 0 5px 30px rgba(0,0,0,.10);
            overflow: hidden;
        }

        .id-card {
            --primary: #073f82;
            --secondary: #1767b7;
            --accent: #ec1470;
            width: {{ \App\Support\FestIdCardTemplates::PASS_CARD_WIDTH_MM }}mm;
            height: {{ \App\Support\FestIdCardTemplates::PASS_CARD_HEIGHT_MM }}mm;
            border: .28mm solid #bdd0e5;
            border-radius: 2.8mm;
            background: linear-gradient(135deg, #ffffff 0%, #ffffff 65%, #f4f9ff 100%);
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
            page-break-inside: avoid;
        }

        .card-header {
            height: 10mm;
            min-height: 10mm;
            display: grid;
            grid-template-columns: 8mm 1fr minmax(0, 30mm);
            align-items: center;
            padding: 1.4mm 2.5mm;
            border-bottom: .25mm solid #dde7f1;
            position: relative;
            background: linear-gradient(90deg, #ffffff 0%, #ffffff 72%, #eef6ff 100%);
        }
        .card-header::before {
            content: "";
            position: absolute;
            left: 0; top: 0;
            width: 100%; height: 1mm;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent), #ff9e24);
        }
        .sahodaya-logo { width: 6.8mm; height: 6.8mm; object-fit: contain; }
        .branding { padding-left: 1.4mm; min-width: 0; }
        .sahodaya-name {
            font-size: 5.7pt; font-weight: 800; color: var(--primary); text-transform: uppercase;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .sahodaya-tagline {
            margin-top: .35mm; font-size: 3.2pt; color: #73839a; text-transform: uppercase; letter-spacing: .15mm;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .event-branding { text-align: right; padding-right: 1mm; min-width: 0; }
        .event-name {
            font-size: 6pt; line-height: 1.15; font-weight: 900; color: var(--accent);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .card-content {
            flex: 1;
            min-height: 0;
            display: grid;
            grid-template-columns: 19mm minmax(0, 1fr);
            gap: 2mm;
            padding: 1.8mm 2.5mm 1.2mm;
        }

        .photo-column { min-width: 0; }
        .photo-box {
            width: 19mm; height: 24mm;
            border-radius: 2mm;
            overflow: hidden;
            background: linear-gradient(145deg, #e5edf7, #c8daed);
            border: .25mm solid #cddaea;
        }
        .student-photo { width: 100%; height: 100%; display: block; object-fit: cover; }
        .photo-fallback {
            width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;
            font-size: 11px; font-weight: 800; color: var(--primary);
        }
        .participant-label {
            margin-top: 1mm; width: 100%; text-align: center; padding: .8mm .5mm;
            border-radius: 1mm; background: var(--accent); color: #fff;
            font-size: 3.6pt; font-weight: 800; text-transform: uppercase; letter-spacing: .2mm;
        }

        .student-details { min-width: 0; }
        .student-name {
            font-size: 6.6pt; line-height: 1.15; font-weight: 800; color: #0b1e3a;
            display: -webkit-box; -webkit-box-orient: vertical; -webkit-line-clamp: 2; overflow: hidden;
        }
        .school-name {
            margin-top: .6mm; font-size: 4.1pt; line-height: 1.1; font-weight: 600; color: #53667e;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .meta-grid { margin-top: 1.3mm; display: grid; grid-template-columns: 1fr 1fr; gap: .7mm 1mm; }
        .meta-box { min-width: 0; padding: .7mm 1mm; background: #f2f7fc; border-radius: 1mm; }
        .meta-label { display: block; font-size: 2.9pt; font-weight: 700; text-transform: uppercase; color: #8391a4; margin-bottom: .25mm; }
        .meta-value {
            display: block; font-size: 3.8pt; line-height: 1.05; font-weight: 700; color: #173557;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .category-value { color: var(--primary); }

        .items-section { margin-top: 1.2mm; padding-top: 1mm; border-top: .2mm solid #dce6f0; }
        .items-title { display: flex; justify-content: space-between; align-items: center; margin-bottom: .7mm; }
        .items-title strong { font-size: 4pt; color: var(--primary); text-transform: uppercase; }
        .item-count { padding: .4mm 1.2mm; border-radius: 3mm; background: #e4f1ff; color: var(--primary); font-size: 3.1pt; font-weight: 800; }
        .item-list {
            list-style: none; counter-reset: event-item;
            column-count: 2; column-gap: 2mm;
        }
        .item-list li {
            counter-increment: event-item; break-inside: avoid;
            display: flex; gap: .6mm; margin-bottom: .6mm;
            font-size: 4.6pt; line-height: 1.15; font-weight: 600; color: #253850;
        }
        .item-list li::before { content: counter(event-item) "."; flex-shrink: 0; font-weight: 800; color: var(--primary); }

        .card-footer {
            height: 4mm; min-height: 4mm; position: relative; overflow: hidden;
            display: flex; align-items: center; justify-content: center; color: #fff;
            background: linear-gradient(90deg, var(--primary), #135ea6 45%, var(--accent) 82%, #ff9d20);
        }
        .footer-text { position: relative; z-index: 2; font-size: 2.9pt; font-weight: 700; text-transform: uppercase; letter-spacing: .25mm; }
        .corner-decoration {
            position: absolute; width: 19mm; height: 19mm; right: -10mm; bottom: -11mm;
            border-radius: 50%; border: 3mm solid rgba(255,255,255,.20);
        }

        @media print {
            html, body { width: 210mm; height: auto; background: #fff; }
            .a4-sheet { margin: 0; box-shadow: none; }
        }

        /* =====================================================
           DOMPDF-FALLBACK CARD (table layout — no grid/flex/gradients/
           multi-column, none of which DomPDF renders)
        ===================================================== */
        @if(!empty($isPdf))
        .grid { width: 100%; border-collapse: separate; border-spacing: 5mm 2mm; table-layout: fixed; }
        .grid td { width: 50%; vertical-align: top; padding: 0; }

        .pass-card-pdf {
            width: {{ \App\Support\FestIdCardTemplates::PASS_CARD_WIDTH_MM }}mm;
            height: {{ \App\Support\FestIdCardTemplates::PASS_CARD_HEIGHT_MM }}mm;
            border: 0.3mm solid #bdd0e5;
            border-radius: 2.8mm;
            overflow: hidden;
            page-break-inside: avoid;
            background: #ffffff;
        }
        .pass-card-pdf__header {
            display: table; width: 100%; table-layout: fixed; border-collapse: collapse;
            border-bottom: 0.25mm solid #dde7f1; background: #f7fafd;
        }
        .pass-card-pdf__logo-cell, .pass-card-pdf__brand-cell, .pass-card-pdf__event-cell {
            display: table-cell; vertical-align: middle; padding: 1.3mm 1.5mm;
        }
        .pass-card-pdf__logo-cell { width: 8mm; }
        .pass-card-pdf__logo { width: 6.5mm; height: 6.5mm; object-fit: contain; }
        .pass-card-pdf__brand-cell { width: 55%; }
        .pass-card-pdf__org-name {
            font-size: 5.6pt; font-weight: 800; color: #073f82; text-transform: uppercase;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pass-card-pdf__tagline { margin-top: 0.3mm; font-size: 3.1pt; color: #73839a; text-transform: uppercase; letter-spacing: 0.1mm; }
        .pass-card-pdf__event-cell { text-align: right; }
        .pass-card-pdf__event-name {
            font-size: 5.8pt; font-weight: 900; color: #ec1470;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pass-card-pdf__body { display: table; width: 100%; padding: 1.6mm 2.2mm 1mm; }
        .pass-card-pdf__photo-cell { display: table-cell; width: 19mm; vertical-align: top; padding: 1.6mm 0 1mm 2.2mm; }
        .pass-card-pdf__photo-box {
            width: 19mm; height: 24mm; border-radius: 1.6mm; overflow: hidden;
            background: #dce8f7; border: 0.25mm solid #cddaea;
        }
        .pass-card-pdf__photo { width: 100%; height: 100%; }
        .pass-card-pdf__photo-fallback {
            width: 100%; height: 100%; text-align: center; line-height: 24mm;
            font-size: 11px; font-weight: bold; color: #073f82;
        }
        .pass-card-pdf__role-label {
            margin-top: 1mm; text-align: center; padding: 0.7mm 0; border-radius: 1mm;
            background: #ec1470; color: #fff; font-size: 3.4pt; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.15mm;
        }
        .pass-card-pdf__info-cell { display: table-cell; vertical-align: top; padding: 1.6mm 2.2mm 1mm 2mm; }
        .pass-card-pdf__name {
            font-size: 7pt; font-weight: 800; color: #0b1e3a;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pass-card-pdf__school {
            margin-top: 0.5mm; font-size: 4pt; font-weight: 600; color: #53667e;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pass-card-pdf__meta { width: 100%; border-collapse: separate; border-spacing: 1mm 0.6mm; margin-top: 1mm; }
        .pass-card-pdf__meta-box { width: 50%; background: #f2f7fc; border-radius: 1mm; padding: 0.6mm 1mm; vertical-align: top; }
        .pass-card-pdf__meta-label { display: block; font-size: 2.8pt; font-weight: 700; text-transform: uppercase; color: #8391a4; }
        .pass-card-pdf__meta-value {
            display: block; font-size: 3.7pt; font-weight: 700; color: #173557;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pass-card-pdf__meta-value--accent { color: #073f82; }
        .pass-card-pdf__items { margin-top: 1mm; padding-top: 0.8mm; border-top: 0.2mm solid #dce6f0; }
        .pass-card-pdf__items-title { width: 100%; border-collapse: collapse; margin-bottom: 0.5mm; }
        .pass-card-pdf__items-title strong { font-size: 3.9pt; color: #073f82; text-transform: uppercase; }
        .pass-card-pdf__items-count-cell { text-align: right; }
        .pass-card-pdf__items-count {
            display: inline-block; padding: 0.3mm 1.1mm; border-radius: 3mm; background: #e4f1ff;
            color: #073f82; font-size: 3pt; font-weight: 800;
        }
        .pass-card-pdf__items-table { width: 100%; border-collapse: collapse; }
        .pass-card-pdf__items-col { width: 50%; vertical-align: top; padding-right: 1mm; }
        .pass-card-pdf__items-table ol { margin: 0; padding-left: 3mm; }
        .pass-card-pdf__items-table li { font-size: 4.4pt; font-weight: 600; color: #253850; line-height: 1.4; }
        .pass-card-pdf__footer {
            height: 3.6mm; text-align: center; color: #fff; font-size: 2.8pt; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.2mm; line-height: 3.6mm;
            background: #073f82;
        }
        @endif
    </style>
</head>
<body>
@if($showTitle ?? true)
<p class="sheet-title">{{ $clusterName }} · {{ $eventTitle }} · {{ ucfirst($audience ?? 'participant') }} passes</p>
@endif

@php
    $renderSections = ! empty($sections);
    $pagePartial = ! empty($isPdf) ? 'fest.id-cards.partials.pass-card-page-pdf' : 'fest.id-cards.partials.pass-card-page';
@endphp

@if($renderSections)
    @foreach($sections as $sectionIndex => $section)
        @if($sectionIndex > 0)<div class="page-break"></div>@endif
        <p class="sheet-title" style="margin-top: 0;">{{ $section['item_title'] ?? 'Item' }}</p>
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
