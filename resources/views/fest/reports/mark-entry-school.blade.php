<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Mark Entry Status</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}h2{text-align:center}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:4px}th{background:#f3f4f6}</style>
</head><body>
<h2>{{ $event->title }}</h2>
<p style="text-align:center;color:#555">{{ $school->name }} — Mark entry status</p>
<p style="text-align:center;font-size:10px">
    Items: {{ $summary['items'] ?? 0 }} · Participants: {{ $summary['participants'] ?? 0 }} · Marked: {{ $summary['marked'] ?? 0 }}
</p>
<table><thead><tr><th>Item</th><th>Participants</th><th>Marked</th><th>Pending</th><th>Status</th></tr></thead>
<tbody>
@foreach($rows as $row)
<tr>
    <td>{{ $row['title'] }}</td>
    <td>{{ $row['participants'] }}</td>
    <td>{{ $row['marked'] }}</td>
    <td>{{ $row['pending'] }}</td>
    <td>{{ ($row['complete'] ?? false) ? 'Complete' : 'Pending' }}</td>
</tr>
@endforeach
</tbody></table></body></html>
