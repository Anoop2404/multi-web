<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Head-wise Participants</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#111}
h2{text-align:center;margin:0 0 4px}
.meta{text-align:center;font-size:10px;color:#555;margin-bottom:12px}
table{width:100%;border-collapse:collapse;margin-bottom:14px}
th,td{border:1px solid #cbd5e1;padding:3px 4px}
th{background:#f1f5f9;font-size:8px;text-transform:uppercase}
.section{font-weight:bold;margin:10px 0 4px;font-size:10px}
</style>
</head><body>
<h2>{{ $event->title }}</h2>
<p class="meta">{{ strtoupper($school->name) }} — Head-wise participants</p>

<p class="section">Summary by head</p>
<table>
<thead><tr><th>Sl No</th><th>Head</th><th>Items</th><th>Regs</th><th>Approved</th><th>Pending</th><th>Participants</th><th>Max item</th></tr></thead>
<tbody>
@foreach($summary as $s)
<tr>
    <td>{{ $loop->iteration }}</td>
    <td>{{ $s['head_name'] }}</td>
    <td>{{ $s['item_count'] }}</td>
    <td>{{ $s['registration_count'] ?? 0 }}</td>
    <td>{{ $s['approved_count'] ?? 0 }}</td>
    <td>{{ $s['pending_count'] ?? 0 }}</td>
    <td>{{ $s['participant_count'] }}</td>
    <td>{{ $s['busiest_item_regs'] ?? $s['max_item_reg_count'] ?? 0 }}</td>
</tr>
@endforeach
</tbody>
</table>

<p class="section">Participant list</p>
<table>
<thead><tr><th>Sl No</th><th>Head</th><th>Participant</th><th>Reg no</th><th>Item</th><th>Fest ID</th><th>Item reg</th><th>Chest</th></tr></thead>
<tbody>
@forelse($rows as $row)
<tr>
    <td>{{ $loop->iteration }}</td>
    <td>{{ $row['head_name'] ?? '—' }}</td>
    <td>{{ $row['student'] }}</td>
    <td>{{ $row['reg_no'] }}</td>
    <td>{{ $row['item'] }}</td>
    <td>{{ $row['fest_id'] ?? '—' }}</td>
    <td>{{ $row['item_reg'] ?? '—' }}</td>
    <td>{{ $row['chest_no'] ?? '—' }}</td>
</tr>
@empty
<tr><td colspan="8" style="text-align:center;padding:16px">No participants.</td></tr>
@endforelse
</tbody></table>
</body></html>
