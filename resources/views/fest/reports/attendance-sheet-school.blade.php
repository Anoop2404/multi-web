<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Attendance — {{ strtoupper($school->name) }}</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:9px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:3px}th{background:#f3f4f6}</style>
</head><body>
<h2 style="text-align:center">{{ $event->title }} — {{ strtoupper($school->name) }}</h2>
<table><thead><tr><th>Sl No</th><th>Student</th><th>Items (Chest No)</th><th>Present</th></tr></thead>
<tbody>
@foreach($studentRows as $row)
<tr>
<td>{{ $loop->iteration }}</td>
<td>{{ $row['student']->name }}</td>
<td>@foreach($row['events'] as $e){{ $e['event_name'] }} ({{ $e['chest_number'] }})@if(!$loop->last), @endif @endforeach</td>
<td></td>
</tr>
@endforeach
</tbody></table></body></html>
