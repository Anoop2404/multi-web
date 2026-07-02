<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Attendance</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:4px}th{background:#f3f4f6}</style>
</head><body>
<h2 style="text-align:center">{{ $event->title }} — Attendance</h2>
<table><thead><tr><th>Order</th><th>Ref</th>
@if(($audience ?? 'staff') === 'staff')<th>Name</th><th>School</th>@endif
<th>Present</th><th>Absent</th></tr></thead>
<tbody>
@foreach($rows as $row)
<tr>
<td>{{ $row['order'] ?? '—' }}</td>
<td>{{ $row['reference'] ?? '—' }}</td>
@if(($audience ?? 'staff') === 'staff')
<td>{{ $row['name'] ?? '' }}</td>
<td>{{ $row['school'] ?? '' }}</td>
@endif
<td></td><td></td>
</tr>
@endforeach
</tbody></table></body></html>
