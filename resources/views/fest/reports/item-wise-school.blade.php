<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Item Participants</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#111}
h2{text-align:center;margin:0 0 4px}
.meta{text-align:center;font-size:10px;color:#555;margin-bottom:12px}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #cbd5e1;padding:3px 4px}
th{background:#f1f5f9;font-size:8px;text-transform:uppercase}
</style>
</head><body>
<h2>{{ $event->title }}</h2>
<p class="meta">{{ strtoupper($school->name) }} — {{ $item->title }}</p>

<table>
<thead><tr><th>Sl No</th><th>Participant</th><th>Reg no</th><th>Class</th><th>Fest ID</th><th>Item reg</th><th>Chest</th><th>Status</th></tr></thead>
<tbody>
@forelse($rows as $row)
<tr>
    <td>{{ $loop->iteration }}</td>
    <td>{{ $row['name'] ?? $row['participant'] ?? '—' }}</td>
    <td>{{ $row['reg_no'] ?? '—' }}</td>
    <td>{{ $row['class'] ?? '—' }}</td>
    <td>{{ $row['fest_id'] ?? '—' }}</td>
    <td>{{ $row['item_reg'] ?? '—' }}</td>
    <td>{{ $row['chest_no'] ?? '—' }}</td>
    <td>{{ ucfirst($row['status'] ?? '—') }}</td>
</tr>
@empty
<tr><td colspan="8" style="text-align:center;padding:16px">No participants.</td></tr>
@endforelse
</tbody></table>
</body></html>
