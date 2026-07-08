<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>ID Cards — {{ $eventTitle }}</title>
    <style>
        @page { size: A4 portrait; margin: 6mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8px; color: #0f172a; }
        .sheet-title { text-align: center; font-size: 11px; font-weight: bold; color: #475569; margin-bottom: 4mm; }
        .grid { width: 100%; border-collapse: separate; border-spacing: 3mm 3mm; }
        .grid td { width: 50%; vertical-align: top; padding: 0; }
        .card {
            width: 96mm;
            height: 72mm;
            aspect-ratio: 4 / 3;
            border: 1px solid #cbd5e1;
            border-radius: 2.5mm;
            overflow: hidden;
            page-break-inside: avoid;
            background: #fff;
            position: relative;
        }
        .card__head {
            display: table;
            width: 100%;
            padding: 2mm 2.5mm;
            color: #fff;
        }
        .card__head--student { background: #0f3d7a; }
        .card__head--volunteer { background: #047857; }
        .card__head--staff { background: #7c2d12; }
        .card__org { display: table-cell; font-size: 6.5px; font-weight: bold; letter-spacing: 0.04em; text-transform: uppercase; vertical-align: middle; width: 58%; }
        .card__role { display: table-cell; font-size: 7px; font-weight: bold; text-align: right; vertical-align: middle; letter-spacing: 0.08em; }
        .card__qr-head { display: table-cell; width: 12mm; vertical-align: middle; text-align: right; }
        .card__qr-head img { width: 10mm; height: 10mm; }
        .card__body { display: table; width: 100%; padding: 2.5mm 2.5mm 1.5mm; min-height: 28mm; }
        .card__avatar {
            display: table-cell;
            width: 18mm;
            height: 18mm;
            vertical-align: top;
        }
        .card__avatar-inner {
            width: 18mm;
            height: 18mm;
            border-radius: 50%;
            background: #e2e8f0;
            color: #0f3d7a;
            font-size: 11px;
            font-weight: bold;
            text-align: center;
            line-height: 18mm;
        }
        .card__photo {
            width: 18mm;
            height: 18mm;
            border-radius: 50%;
            object-fit: cover;
        }
        .card__info { display: table-cell; vertical-align: top; padding-left: 2.5mm; padding-right: 1mm; }
        .card__name { font-size: 10px; font-weight: bold; line-height: 1.2; margin-bottom: 0.8mm; }
        .card__sub { font-size: 7px; color: #475569; line-height: 1.25; }
        .card__detail { font-size: 6.5px; color: #64748b; margin-top: 0.5mm; }
        .card__members { margin-top: 1mm; }
        .card__member { font-size: 5.8px; color: #334155; line-height: 1.35; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .card__member-role { color: #94a3b8; font-size: 5.5px; }
        .card__ids {
            display: table;
            width: 100%;
            border-top: 0.4mm solid #e2e8f0;
            padding: 1.2mm 2.5mm;
            background: #f8fafc;
        }
        .card__id-block { display: table-cell; width: 50%; vertical-align: top; }
        .card__id-label { font-size: 5.5px; text-transform: uppercase; letter-spacing: 0.06em; color: #94a3b8; }
        .card__id-value { font-size: 9px; font-weight: bold; font-family: DejaVu Sans Mono, monospace; color: #0f3d7a; }
        .card__foot {
            font-size: 5.5px;
            text-align: center;
            color: #64748b;
            padding: 0.8mm 2mm 1.5mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
@if($showTitle ?? true)
<p class="sheet-title">{{ $clusterName }} · {{ $eventTitle }} · {{ ucfirst($audience ?? 'participant') }} ID cards</p>
@endif

@php
    $chunks = array_chunk($cards, 6);
@endphp

@foreach($chunks as $pageIndex => $pageCards)
    @if($pageIndex > 0)<div class="page-break"></div>@endif
    <table class="grid">
        @foreach(array_chunk($pageCards, 2) as $row)
        <tr>
            @foreach($row as $card)
            <td>
                <div class="card">
                    <div class="card__head card__head--{{ $card['role_class'] }}">
                        <span class="card__org">{{ $clusterName }}</span>
                        <span class="card__role">{{ $card['role_label'] }}</span>
                        @if(!empty($card['qr_src']))
                        <span class="card__qr-head"><img src="{{ $card['qr_src'] }}" alt=""></span>
                        @endif
                    </div>
                    @if(($card['card_type'] ?? 'individual') === 'team')
                    <div class="card__body">
                        <div class="card__info" style="display:block;padding-left:2.5mm;">
                            <div class="card__name">{{ $card['name'] }}</div>
                            <div class="card__sub">{{ $card['subtitle'] }}</div>
                            <div class="card__detail">{{ $card['detail'] }}</div>
                            @if(!empty($card['schedule']))
                            <div class="card__detail" style="margin-top:0.8mm;">{{ $card['schedule'] }}</div>
                            @endif
                            <div class="card__members">
                                @foreach(array_slice($card['members'] ?? [], 0, 7) as $member)
                                <div class="card__member">
                                    {{ $member['name'] }}
                                    · {{ $member['fest_id'] }}
                                    @if(!empty($member['chest'])) · {{ $member['chest'] }} @endif
                                </div>
                                @endforeach
                                @if(($card['member_count'] ?? 0) > 7)
                                <div class="card__member card__member-role">+ {{ ($card['member_count'] ?? 0) - 7 }} more member(s)</div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @else
                    <div class="card__body">
                        <div class="card__avatar">
                            @if(!empty($card['photo_src']))
                                <img src="{{ $card['photo_src'] }}" alt="" class="card__photo">
                            @else
                                <div class="card__avatar-inner">{{ $card['initials'] }}</div>
                            @endif
                        </div>
                        <div class="card__info">
                            <div class="card__name">{{ $card['name'] }}</div>
                            <div class="card__sub">{{ $card['subtitle'] }}</div>
                            <div class="card__detail">{{ $card['detail'] }}</div>
                            @if(!empty($card['schedule']))
                            <div class="card__detail" style="margin-top:0.8mm;">{{ $card['schedule'] }}</div>
                            @endif
                        </div>
                    </div>
                    @endif
                    <div class="card__ids">
                        <div class="card__id-block">
                            <div class="card__id-label">{{ $card['id_label'] }}</div>
                            <div class="card__id-value">{{ $card['id_number'] }}</div>
                        </div>
                        <div class="card__id-block">
                            <div class="card__id-label">{{ $card['secondary_label'] }}</div>
                            <div class="card__id-value" style="font-size:8px;">{{ $card['secondary_value'] }}</div>
                        </div>
                    </div>
                    <div class="card__foot">{{ $card['footer'] }}</div>
                </div>
            </td>
            @endforeach
            @if(count($row) === 1)<td></td>@endif
        </tr>
        @endforeach
    </table>
@endforeach

@if(empty($cards))
<p style="text-align:center;padding:20mm;color:#94a3b8;">No cards match your filters.</p>
@endif
</body>
</html>
