<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Digital Sum Sheet</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}h2{text-align:center;font-size:14px}table{width:100%;border-collapse:collapse;margin-top:10px}th,td{border:1px solid #999;padding:4px}th{background:#0f3d7a;color:#fff;font-size:9px}td.num{text-align:right}tfoot td{font-weight:bold}</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2>{{ $event->title }} — Digital Sum Sheet: {{ $item?->title }}</h2>
<table>
    <thead>
        <tr>
            <th>Sl No</th>
            <th>Chest No</th>
            <th>Reg ID</th>
            <th>Participant / Team</th>
            <th>School</th>
            @for($j = 1; $j <= $judgeCount; $j++)
                <th>Judge {{ $j }}</th>
            @endfor
            <th>Grand Total</th>
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
                @foreach($row['scores'] as $s)
                    <td class="num">{{ $s === null ? '—' : rtrim(rtrim(number_format($s, 2), '0'), '.') }}</td>
                @endforeach
                <td class="num">{{ rtrim(rtrim(number_format($row['total'], 2), '0'), '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="{{ 5 + $judgeCount + 1 }}" style="text-align:center;color:#888">No participants for this item.</td></tr>
        @endforelse
    </tbody>
</table>
</body></html>
