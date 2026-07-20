<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Cumulative Mark Sheet</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}h2{text-align:center;font-size:14px}table{width:100%;border-collapse:collapse;margin-top:10px}th,td{border:1px solid #999;padding:4px}th{background:#0f3d7a;color:#fff;font-size:9px}td.num{text-align:right}tfoot td{font-weight:bold}</style>
</head><body>
<h2>{{ $event->title }} — Cumulative Mark Sheet: {{ $item?->title }}</h2>
<table>
    <thead>
        <tr>
            <th>Sl No</th>
            <th>Chest No</th>
            <th>Reg ID</th>
            <th>Participant / Team</th>
            <th>School</th>
            @foreach($criteria as $c)
                <th>{{ $c->label }}<br><small>/ {{ rtrim(rtrim(number_format($c->max_score, 2), '0'), '.') }}</small></th>
            @endforeach
            <th>Total</th>
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
            <tr><td colspan="{{ 5 + count($criteria) + 1 }}" style="text-align:center;color:#888">No participants for this item.</td></tr>
        @endforelse
    </tbody>
</table>
</body></html>
