<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $sheetTitle ?? 'Digital Sum Sheet' }}</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11.5px}h2{text-align:center;font-size:15px}table{width:100%;border-collapse:collapse;margin-top:10px}th,td{border:1px solid #999;padding:5px 6px;font-size:11px}th{background:#0f3d7a;color:#fff;font-size:10.5px}td.num{text-align:right}tfoot td{font-weight:bold}</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2>
    {{ $event->title }} — {{ $sheetTitle ?? 'Digital Sum Sheet' }}: {{ $item?->title }}
    @if($item?->category && $item->category !== 'general')
        <small>({{ ucwords(str_replace(['_', '-'], ' ', $item->category)) }})</small>
    @endif
</h2>
<table>
    <thead>
        <tr>
            <th>Sl No</th>
            <th>Chest No</th>
            <th>Reg ID</th>
            <th>Participant / Team</th>
            <th>School</th>
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
                <td>{{ $loop->iteration }}</td>
                <td>{{ $row['chest_no'] ?? '—' }}</td>
                <td>{{ $row['reg_no'] ?? '—' }}</td>
                <td>{{ $row['name'] ?? '—' }}</td>
                <td>{{ $row['school'] ?? '—' }}</td>
                @if($judgeCount > 1)
                    @foreach($row['scores'] as $s)
                        <td class="num">{{ $s === null ? '—' : rtrim(rtrim(number_format($s, 2), '0'), '.') }}</td>
                    @endforeach
                    <td class="num">{{ rtrim(rtrim(number_format($row['total'], 2), '0'), '.') }}</td>
                @else
                    <td class="num">{{ $row['scores'][0] === null ? '—' : rtrim(rtrim(number_format($row['scores'][0], 2), '0'), '.') }}</td>
                @endif
            </tr>
        @empty
            <tr><td colspan="{{ $judgeCount > 1 ? 5 + $judgeCount + 1 : 6 }}" style="text-align:center;color:#888">No participants for this item.</td></tr>
        @endforelse
    </tbody>
</table>
</body></html>
