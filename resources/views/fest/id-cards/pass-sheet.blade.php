<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Participant Pass — {{ $eventTitle }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        @page { size: A4 portrait; margin: 9.5mm 16.9mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #10213d; }
        .sheet-title { text-align: center; font-size: 11px; font-weight: bold; color: #475569; margin-bottom: 4mm; }
        .page-break { page-break-after: always; }

        .grid { width: 100%; border-collapse: separate; border-spacing: 5mm 2mm; table-layout: fixed; }
        .grid td { width: 50%; vertical-align: top; padding: 0; }

        .pass-card {
            width: {{ \App\Support\FestIdCardTemplates::PASS_CARD_WIDTH_MM }}mm;
            height: {{ \App\Support\FestIdCardTemplates::PASS_CARD_HEIGHT_MM }}mm;
            border: 0.3mm solid #bdd0e5;
            border-radius: 2.5mm;
            overflow: hidden;
            page-break-inside: avoid;
            background: #ffffff;
        }

        /* Header */
        .pass-card__header {
            display: table;
            width: 100%;
            border-collapse: collapse;
            border-bottom: 0.25mm solid #dde7f1;
            background: #eef6ff;
        }
        .pass-card__logo-cell,
        .pass-card__brand-cell,
        .pass-card__event-cell {
            display: table-cell;
            vertical-align: middle;
            padding: 1.3mm 1.5mm;
        }
        .pass-card__logo-cell { width: 8mm; }
        .pass-card__logo { width: 6.5mm; height: 6.5mm; object-fit: contain; }
        .pass-card__brand-cell { width: 55%; }
        .pass-card__org-name {
            font-size: 5.6pt; font-weight: 800; color: #073f82; text-transform: uppercase;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pass-card__tagline { margin-top: 0.3mm; font-size: 3.1pt; color: #73839a; text-transform: uppercase; letter-spacing: 0.1mm; }
        .pass-card__event-cell { text-align: right; }
        .pass-card__event-name {
            font-size: 5.8pt; font-weight: 900; color: #ec1470;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pass-card__card-no { margin-top: 0.4mm; font-size: 3.8pt; font-weight: 700; color: #073f82; }

        /* Body */
        .pass-card__body { display: table; width: 100%; padding: 1.6mm 2.2mm 1mm; }
        .pass-card__photo-cell { display: table-cell; width: 19mm; vertical-align: top; padding: 1.6mm 0 1mm 2.2mm; }
        .pass-card__photo-box {
            width: 19mm; height: 24mm; border-radius: 1.6mm; overflow: hidden;
            background: #dbe7f6; border: 0.25mm solid #cddaea;
        }
        .pass-card__photo { width: 100%; height: 100%; }
        .pass-card__photo-fallback {
            width: 100%; height: 100%; text-align: center; line-height: 24mm;
            font-size: 11px; font-weight: bold; color: #073f82;
        }
        .pass-card__role-label {
            margin-top: 1mm; text-align: center; padding: 0.7mm 0; border-radius: 1mm;
            background: #ec1470; color: #fff; font-size: 3.4pt; font-weight: 800;
            text-transform: uppercase; letter-spacing: 0.15mm;
        }

        .pass-card__info-cell { display: table-cell; vertical-align: top; padding: 1.6mm 2.2mm 1mm 2mm; }
        .pass-card__name {
            font-size: 7pt; font-weight: 800; color: #0b1e3a;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pass-card__school {
            margin-top: 0.5mm; font-size: 4pt; font-weight: 600; color: #53667e;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .pass-card__meta { width: 100%; border-collapse: separate; border-spacing: 1mm 0.6mm; margin-top: 1mm; }
        .pass-card__meta-box { width: 50%; background: #f2f7fc; border-radius: 1mm; padding: 0.6mm 1mm; vertical-align: top; }
        .pass-card__meta-label { display: block; font-size: 2.8pt; font-weight: 700; text-transform: uppercase; color: #8391a4; }
        .pass-card__meta-value {
            display: block; font-size: 3.7pt; font-weight: 700; color: #173557;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .pass-card__meta-value--accent { color: #073f82; }

        .pass-card__items { margin-top: 1mm; padding-top: 0.8mm; border-top: 0.2mm solid #dce6f0; }
        .pass-card__items-title { display: table; width: 100%; margin-bottom: 0.5mm; }
        .pass-card__items-title strong { font-size: 3.9pt; color: #073f82; text-transform: uppercase; }
        .pass-card__items-count {
            float: right; padding: 0.3mm 1.1mm; border-radius: 3mm; background: #e4f1ff;
            color: #073f82; font-size: 3pt; font-weight: 800;
        }
        .pass-card__items-table { width: 100%; border-collapse: collapse; }
        .pass-card__items-col { width: 50%; vertical-align: top; padding-right: 1mm; }
        .pass-card__items-table ol { margin: 0; padding-left: 3mm; }
        .pass-card__items-table li { font-size: 3.3pt; font-weight: 600; color: #253850; line-height: 1.35; }

        /* Footer */
        .pass-card__footer {
            height: 3.6mm; text-align: center; color: #fff; font-size: 2.8pt; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.2mm; line-height: 3.6mm;
            background: #073f82;
        }
    </style>
</head>
<body>
@if($showTitle ?? true)
<p class="sheet-title">{{ $clusterName }} · {{ $eventTitle }} · {{ ucfirst($audience ?? 'participant') }} passes</p>
@endif

@php
    $renderSections = ! empty($sections);
@endphp

@if($renderSections)
    @foreach($sections as $sectionIndex => $section)
        @if($sectionIndex > 0)<div class="page-break"></div>@endif
        <p class="sheet-title" style="margin-top: 0;">{{ $section['item_title'] ?? 'Item' }}</p>
        @php $chunks = array_chunk($section['cards'] ?? [], \App\Support\FestIdCardTemplates::PASS_CARDS_PER_PAGE); @endphp
        @foreach($chunks as $pageIndex => $pageCards)
            @if($pageIndex > 0)<div class="page-break"></div>@endif
            @include('fest.id-cards.partials.pass-card-page', ['pageCards' => $pageCards, 'clusterName' => $clusterName, 'clusterLogoSrc' => $clusterLogoSrc ?? null, 'eventTitle' => $eventTitle, 'pageOffset' => $pageIndex * \App\Support\FestIdCardTemplates::PASS_CARDS_PER_PAGE])
        @endforeach
    @endforeach
@else
    @php $chunks = array_chunk($cards ?? [], \App\Support\FestIdCardTemplates::PASS_CARDS_PER_PAGE); @endphp
    @foreach($chunks as $pageIndex => $pageCards)
        @if($pageIndex > 0)<div class="page-break"></div>@endif
        @include('fest.id-cards.partials.pass-card-page', ['pageCards' => $pageCards, 'clusterName' => $clusterName, 'clusterLogoSrc' => $clusterLogoSrc ?? null, 'eventTitle' => $eventTitle, 'pageOffset' => $pageIndex * \App\Support\FestIdCardTemplates::PASS_CARDS_PER_PAGE])
    @endforeach
@endif

@if(empty($cards) && empty($sections))
<p style="text-align:center;padding:20mm;color:#94a3b8;">No cards match your filters.</p>
@endif
</body>
</html>
