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
            width: {{ \App\Support\FestIdCardTemplates::CARD_WIDTH_MM }}mm;
            height: {{ \App\Support\FestIdCardTemplates::CARD_HEIGHT_MM }}mm;
            border-radius: 2.5mm;
            overflow: hidden;
            page-break-inside: avoid;
            background: #fff;
            border: 0.3mm solid #cbd5e1;
            box-shadow: 0 0.8mm 2mm rgba(10, 45, 92, 0.1);
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .pcard__accent {
            flex-shrink: 0;
            height: 1.2mm;
            background: linear-gradient(90deg, #8b6914 0%, #c9a227 25%, #f5e6b8 50%, #c9a227 75%, #8b6914 100%);
        }
        .pcard--event-pass .pcard__accent {
            background: linear-gradient(90deg, #065f46 0%, #10b981 50%, #065f46 100%);
        }

        /* Header */
        .pcard__header {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2mm;
            padding: 2mm 2.5mm 1.8mm;
            background: linear-gradient(180deg, #0a2d5c 0%, #0f3d7a 100%);
            color: #fff;
        }
        .pcard--volunteer .pcard__header { background: linear-gradient(180deg, #064e3b 0%, #047857 100%); }
        .pcard--staff .pcard__header { background: linear-gradient(180deg, #7c2d12 0%, #9a3412 100%); }
        .pcard__brand {
            display: flex;
            align-items: center;
            gap: 2mm;
            min-width: 0;
            flex: 1;
        }
        .pcard__logo,
        .pcard__logo-fallback {
            width: 12mm;
            height: 12mm;
            border-radius: 50%;
            flex-shrink: 0;
            border: 0.4mm solid rgba(201, 162, 39, 0.9);
            background: #fff;
            object-fit: cover;
        }
        .pcard__logo-fallback {
            display: flex;
            align-items: center;
            justify-content: center;
            color: #0f3d7a;
            font-size: 8px;
            font-weight: bold;
        }
        .pcard__brand-text { min-width: 0; flex: 1; }
        .pcard__cluster {
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            line-height: 1.2;
            opacity: 0.88;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcard__event {
            font-size: 10.5px;
            font-weight: bold;
            margin-top: 0.4mm;
            line-height: 1.15;
            letter-spacing: 0.02em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcard__qr {
            width: 13mm;
            height: 13mm;
            background: #fff;
            border-radius: 1mm;
            padding: 0.5mm;
            flex-shrink: 0;
        }

        /* Discipline strip */
        .pcard__discipline {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1mm 2.5mm;
            background: #f8fafc;
            border-bottom: 0.2mm solid #e2e8f0;
        }
        .pcard__discipline-text {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            text-align: center;
            color: #0f3d7a;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcard--event-pass .pcard__discipline-text { color: #047857; }

        /* Body */
        .pcard__body {
            flex: 1;
            min-height: 0;
            display: flex;
            align-items: stretch;
            gap: 2mm;
            padding: 2.5mm;
            background: #fff;
            position: relative;
        }
        .pcard__body--team {
            flex-direction: column;
            gap: 1.5mm;
        }
        .pcard__portrait { flex-shrink: 0; }
        .pcard__photo,
        .pcard__initials {
            width: 22mm;
            height: 27mm;
            border-radius: 1.5mm;
            border: 0.45mm solid #c9a227;
            display: block;
            background: #f1f5f9;
        }
        .pcard__photo { object-fit: cover; }
        .pcard__initials {
            color: #0f3d7a;
            font-size: 17px;
            font-weight: bold;
            text-align: center;
            line-height: 26mm;
        }
        .pcard__info {
            min-width: 0;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding-right: 1mm;
        }
        .pcard__name {
            font-size: 14px;
            font-weight: bold;
            line-height: 1.15;
            color: #0f172a;
            word-wrap: break-word;
        }
        .pcard__school {
            font-size: 9px;
            color: #64748b;
            margin-top: 0.8mm;
            line-height: 1.25;
        }
        .pcard__tag {
            display: inline-block;
            margin-top: 1.2mm;
            padding: 0.6mm 1.5mm;
            font-size: 8px;
            font-weight: bold;
            color: #0f3d7a;
            background: #eff6ff;
            border: 0.2mm solid #bfdbfe;
            border-radius: 1mm;
            line-height: 1.2;
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcard__items {
            margin: 1mm 0 0 0;
            padding: 0 0 0 3mm;
            font-size: 7.5px;
            color: #334155;
            line-height: 1.45;
        }
        .pcard__items-more {
            color: #94a3b8;
            list-style: none;
            margin-left: -3mm;
        }

        /* Secondary info badge (reg no, access, etc.) */
        .pcard__badge {
            flex-shrink: 0;
            align-self: center;
            width: 18mm;
            text-align: center;
            padding: 1.5mm 1mm;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 1.5mm;
            border: 0.3mm solid #cbd5e1;
            color: #0f3d7a;
        }
        .pcard__badge-label {
            display: block;
            font-size: 6.5px;
            font-weight: bold;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            opacity: 0.85;
        }
        .pcard__badge-value {
            display: block;
            font-size: 13px;
            font-weight: bold;
            font-family: DejaVu Sans Mono, monospace;
            line-height: 1;
            margin-top: 0.5mm;
            letter-spacing: -0.02em;
        }

        /* Team */
        .pcard__team-head { min-width: 0; }
        .pcard__members {
            flex: 1;
            min-height: 0;
            overflow: hidden;
            border-top: 0.2mm solid #e2e8f0;
            padding-top: 1mm;
        }
        .pcard__member {
            display: flex;
            justify-content: space-between;
            gap: 1mm;
            font-size: 7px;
            line-height: 1.4;
            padding: 0.3mm 0;
        }
        .pcard__member-name {
            font-weight: bold;
            color: #0f172a;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .pcard__member-meta {
            color: #64748b;
            font-family: DejaVu Sans Mono, monospace;
            flex-shrink: 0;
        }
        .pcard__member--more { color: #94a3b8; font-weight: normal; }

        /* Footer */
        .pcard__footer {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 2mm;
            padding: 1.4mm 2.5mm;
            background: #0a2d5c;
            color: #fff;
        }
        .pcard--volunteer .pcard__footer { background: #064e3b; }
        .pcard--staff .pcard__footer { background: #7c2d12; }
        .pcard__footer-id {
            flex: 1;
            min-width: 0;
            display: flex;
            align-items: baseline;
            gap: 1.5mm;
        }
        .pcard__footer-label {
            font-size: 6.5px;
            font-weight: bold;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            opacity: 0.75;
            flex-shrink: 0;
        }
        .pcard__footer-value {
            font-size: 10px;
            font-weight: bold;
            font-family: DejaVu Sans Mono, monospace;
            letter-spacing: 0.04em;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .pcard__role {
            flex-shrink: 0;
            font-size: 7px;
            font-weight: bold;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            padding: 0.6mm 1.8mm;
            background: rgba(201, 162, 39, 0.25);
            border: 0.2mm solid rgba(201, 162, 39, 0.6);
            border-radius: 1mm;
            color: #f5e6b8;
        }
        .pcard__footer-meta {
            flex-shrink: 0;
            font-size: 7px;
            opacity: 0.85;
        }
        .pcard__footer-schedule {
            flex-shrink: 0;
            font-size: 6.5px;
            opacity: 0.8;
            max-width: 28mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>
@if(!empty($isSample))
<p style="text-align:center;font-family:system-ui,sans-serif;font-size:11px;color:#b45309;background:#fffbeb;border:1px solid #fcd34d;padding:8px 12px;margin:0 0 3mm;border-radius:6px;">
    <strong>Sample ID card</strong> — catalog Event Head preview for client demo (not tied to a live event).
</p>
@endif
@if($showTitle ?? true)
<p class="sheet-title">{{ $clusterName }} · {{ $eventTitle }} · Premium ID cards</p>
@endif

@php
    $renderSections = ! empty($sections);
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

@if(empty($cards) && empty($sections))
<p style="text-align:center;padding:20mm;color:#94a3b8;">No cards match your filters.</p>
@endif
</body>
</html>
