<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — {{ $event->title }}@if(!empty($item)) — {{ $item->title }}@endif — Winners</title>
    <style>
        @page { margin: 22px 28px; margin-bottom: 24px;}
        body { font-family: 'DejaVu Sans', system-ui, sans-serif; font-size: 11px; color: #0f172a; margin: 0; }
        h1 { font-size: 15px; font-weight: 800; color: #0f172a; margin: 4px 0 2px; text-align: center; }
        .subtitle { text-align: center; font-size: 10px; color: #64748b; margin: 0 0 12px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #b45309; color: #ffffff; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; text-align: left; padding: 7px 8px; border: 1px solid #b45309; }
        table.data td { border: 1px solid #fde68a; padding: 9px 8px; font-size: 11px; vertical-align: middle; }
        table.data tr:nth-child(even) td { background: #fffbeb; }
        .medal-cell { text-align: center; width: 54px; }
        .medal-cell img { width: 32px; height: 32px; }
        .rank { font-weight: 800; font-size: 12px; color: #92400e; }
        .name { font-weight: 700; }
        .footer { margin-top: 14px; padding-top: 6px; border-top: 1px solid #cbd5e1; font-size: 8.5px; color: #64748b; }
        .empty { text-align: center; padding: 20px; color: #64748b; }
        .blank-line { display: inline-block; width: 100%; border-bottom: 1px solid #cbd5e1; height: 16px; }
    </style>
</head>
<body>
    @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
    @include('partials.pdf-branding-header', [
        'orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'),
        'logoSrc' => $logoSrc ?? null,
        'docTitle' => 'Winner Sheet',
    ])
    <h1>{{ $event->title }}@if(!empty($item)) — {{ $item->title }}@endif</h1>
    <div class="subtitle">🏆 Winners — Top 3 @if(!empty($blank))(Blank — to fill in by hand)@endif</div>

    @include('partials.pdf-item-info-bar', ['item' => $item ?? null, 'category' => $itemCategory ?? null])

    @if(!empty($blank))
        <table class="data">
            <thead>
                <tr>
                    <th class="medal-cell">Medal</th>
                    <th style="width: 50px;">Rank</th>
                    <th style="width: 90px;">Chest No</th>
                    <th>Participant</th>
                    <th>School</th>
                    <th style="width: 70px;">Grade</th>
                </tr>
            </thead>
            <tbody>
                @foreach([1, 2, 3] as $rank)
                <tr style="height: 34px;">
                    <td class="medal-cell">
                        @if(!empty($medalSrcs[$rank] ?? null))
                            <img src="{{ $medalSrcs[$rank] }}" alt="Rank {{ $rank }}">
                        @endif
                    </td>
                    <td class="rank">{{ $rank }}</td>
                    <td><span class="blank-line"></span></td>
                    <td><span class="blank-line"></span></td>
                    <td><span class="blank-line"></span></td>
                    <td><span class="blank-line"></span></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @elseif(count($rows))
        <table class="data">
            <thead>
                <tr>
                    <th class="medal-cell">Medal</th>
                    <th style="width: 50px;">Rank</th>
                    <th style="width: 70px;">Chest No</th>
                    <th>Participant</th>
                    <th>School</th>
                    <th style="width: 70px;">Grade</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                <tr>
                    <td class="medal-cell">
                        @if(!empty($medalSrcs[$row['position']] ?? null))
                            <img src="{{ $medalSrcs[$row['position']] }}" alt="Rank {{ $row['position'] }}">
                        @endif
                    </td>
                    <td class="rank">{{ $row['position'] }}</td>
                    <td>{{ $row['chest_no'] ?? '—' }}</td>
                    <td class="name">{{ $row['name'] ?? '' }}</td>
                    <td>{{ strtoupper($row['school'] ?? '') }}</td>
                    <td>{{ $row['grade'] ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">No 1st / 2nd / 3rd rank recorded for this item yet. Use the blank winner sheet to record results on paper first.</div>
    @endif

    <div class="footer">{{ $orgName ?? ($sahodaya->name ?? 'Sahodaya') }} &bull; {{ $event->title }} &bull; Generated {{ now()->format('d M Y, h:i A') }}</div>
</body>
</html>
