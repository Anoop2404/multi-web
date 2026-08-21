<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $sheetTitle ?? 'Digital Sum Sheet' }} — {{ $event->title }}</title>
    <style>
        @page { margin: 16px 20px; size: portrait; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11.5px; color: #1e293b; line-height: 1.4; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 12px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th { background: #0f3d7a; color: #ffffff; font-size: 10.5px; font-weight: bold; text-transform: uppercase; text-align: left; padding: 6px 8px; border: 1px solid #0f3d7a; }
        .table td { border: 1px solid #cbd5e1; padding: 6px 8px; font-size: 11px; }
        .table tr:nth-child(even) { background-color: #f8fafc; }
        .num { text-align: right; }
        .center { text-align: center; }
    </style>
</head>
<body>
    <div class="header" style="margin-bottom: 12px;">
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px;">
            <tr>
                @if(!empty($logoSrc))
                    <td style="width: 55px; vertical-align: middle; padding-right: 12px;">
                        <img src="{{ $logoSrc }}" alt="Logo" style="width: 48px; height: 48px; object-fit: contain;">
                    </td>
                @endif
                <td style="vertical-align: middle;">
                    <div style="font-size: 17px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; line-height: 1.1;">
                        {{ $orgName ?? ($sahodaya->name ?? 'SAHODAYA SCHOOLS COMPLEX') }}
                    </div>
                    <div style="font-size: 11px; font-weight: 600; color: #475569; margin-top: 3px;">
                        CBSE Sahodaya Inter-School Competitions & Events
                    </div>
                </td>
                <td style="text-align: right; vertical-align: middle;">
                    <div style="display: inline-block; background: #0f172a; color: #ffffff; padding: 4px 10px; border-radius: 4px; font-size: 10px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase;">
                        Online Tabulation
                    </div>
                </td>
            </tr>
        </table>

        <div style="border-bottom: 2px solid #0f172a; margin-bottom: 8px;"></div>

        <div style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px; padding: 6px 10px;">
            <table style="width: 100%; border-collapse: collapse; font-size: 10px; color: #1e293b;">
                <tr>
                    <td style="padding: 2px 0;"><strong>EVENT:</strong> {{ strtoupper($event->title) }}</td>
                    <td style="padding: 2px 0; text-align: right;"><strong>SHEET:</strong> {{ strtoupper($sheetTitle ?? 'DIGITAL SUM SHEET') }}</td>
                </tr>
                <tr>
                    <td style="padding: 2px 0;">
                        <strong>ITEM:</strong> {{ $item?->item_code ? "[{$item->item_code}] " : '' }}{{ $item?->title }}
                        @if(!empty($categoryLabel))
                            <strong>&middot; CATEGORY:</strong> {{ $categoryLabel }}
                        @endif
                    </td>
                    <td style="padding: 2px 0; text-align: right;"><strong>TOTAL PARTICIPANTS:</strong> {{ count($rows) }}</td>
                </tr>
            </table>
        </div>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th class="center" style="width: 40px;">Sl No</th>
                <th style="width: 90px;">Chest No</th>
                @if($judgeCount > 1)
                    @for($j = 1; $j <= $judgeCount; $j++)
                        <th>Judge {{ $j }}</th>
                    @endfor
                    <th>Grand Total</th>
                @else
                    <th>Score</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td style="font-weight: bold; font-family: monospace;">{{ $row['chest_no'] ? '#'.$row['chest_no'] : '—' }}</td>
                    @if($judgeCount > 1)
                        @foreach($row['scores'] as $s)
                            <td class="num">{{ $s === null ? '' : rtrim(rtrim(number_format($s, 2), '0'), '.') }}</td>
                        @endforeach
                        <td class="num">{{ $row['total'] === null ? '' : rtrim(rtrim(number_format($row['total'], 2), '0'), '.') }}</td>
                    @else
                        <td class="num">{{ $row['scores'][0] === null ? '' : rtrim(rtrim(number_format($row['scores'][0], 2), '0'), '.') }}</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $judgeCount > 1 ? 2 + $judgeCount + 1 : 3 }}" class="center" style="padding: 16px; color: #64748b;">No participants for this item.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
