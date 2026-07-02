<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Green Room</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:4px}th{background:#ecfdf5}</style>
</head><body>
<h2 style="text-align:center">{{ $event->title }} — Green Room List</h2>
<p style="text-align:center;color:#666">Staff only — includes participant names and schools</p>
<table><thead><tr><th>Item</th><th>Level Reg</th><th>Chest</th><th>Name</th><th>School</th><th>Revealed</th></tr></thead>
<tbody>
@foreach($rows as $row)
<tr>
<td>{{ $row['item'] ?? '—' }}</td>
<td>{{ $row['level_reg'] ?? '—' }}</td>
<td>{{ $row['reference'] ?? '—' }}</td>
<td>{{ $row['name'] ?? '—' }}</td>
<td>{{ $row['school'] ?? '—' }}</td>
<td>{{ ($row['revealed'] ?? false) ? 'Yes' : 'No' }}</td>
</tr>
@endforeach
</tbody></table></body></html>
