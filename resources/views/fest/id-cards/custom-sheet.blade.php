<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ID Cards — {{ $eventTitle }}</title>
    <style>
        @page { size: A4 portrait; margin: 6mm; }
        * { box-sizing: border-box; }
        body { font-family: Arial, DejaVu Sans, sans-serif; color: #1e293b; margin: 0; }
        .sheet-title { text-align: center; font-size: 11px; font-weight: bold; color: #475569; margin-bottom: 4mm; }
        .section-title { font-size: 10px; font-weight: bold; color: #475569; margin: 3mm 0 2mm; }
        .grid { width: 100%; border-collapse: separate; border-spacing: 3mm 3mm; }
        .grid td { width: 50%; vertical-align: top; padding: 0; }
        .card {
            border: 1px solid #cbd5e1;
            border-radius: 2.5mm;
            overflow: hidden;
            page-break-inside: avoid;
            background: #fff;
            position: relative;
        }
        .card__bg {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            object-fit: cover;
        }
        .card__field {
            position: absolute;
            line-height: 1.25;
            overflow: hidden;
            word-wrap: break-word;
        }
        .card__photo, .card__qr {
            position: absolute;
            object-fit: cover;
        }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
@if($showTitle ?? true)
<p class="sheet-title">{{ $clusterName }} · {{ $eventTitle }} · {{ ucfirst($audience ?? 'participant') }} ID cards</p>
@endif

@php
    $perPage = max(1, $cardsPerPage ?? 4);
    $renderSections = ! empty($sections);
@endphp

@if($renderSections)
    @foreach($sections as $sectionIndex => $section)
        @if($sectionIndex > 0)<div class="page-break"></div>@endif
        <p class="section-title">{{ $section['item_title'] ?? 'Item' }}</p>
        @php $chunks = array_chunk($section['cards'] ?? [], $perPage); @endphp
        @foreach($chunks as $pageIndex => $pageCards)
            @if($pageIndex > 0)<div class="page-break"></div>@endif
            <table class="grid">
                @foreach(array_chunk($pageCards, 2) as $row)
                <tr>
                    @foreach($row as $card)
                    <td>@include('fest.id-cards.partials.custom-card', ['card' => $card, 'backgroundUrl' => $backgroundUrl ?? null, 'fields' => $fields ?? [], 'cardWidthMm' => $cardWidthMm ?? 96, 'cardHeightMm' => $cardHeightMm ?? 72])</td>
                    @endforeach
                    @if(count($row) === 1)<td></td>@endif
                </tr>
                @endforeach
            </table>
        @endforeach
    @endforeach
@else
    @php $chunks = array_chunk($cards ?? [], $perPage); @endphp
    @foreach($chunks as $pageIndex => $pageCards)
        @if($pageIndex > 0)<div class="page-break"></div>@endif
        <table class="grid">
            @foreach(array_chunk($pageCards, 2) as $row)
            <tr>
                @foreach($row as $card)
                <td>@include('fest.id-cards.partials.custom-card', ['card' => $card, 'backgroundUrl' => $backgroundUrl ?? null, 'fields' => $fields ?? [], 'cardWidthMm' => $cardWidthMm ?? 96, 'cardHeightMm' => $cardHeightMm ?? 72])</td>
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
