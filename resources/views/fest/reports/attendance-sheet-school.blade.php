<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Attendance — {{ strtoupper($school->name) }}</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:9px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:3px}th{background:#f3f4f6}</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }} — {{ strtoupper($school->name) }}</h2>
<table><thead><tr><th style="width: 35px; text-align: center;">Sl No</th><th>Student</th>@if($event->event_type === 'sports')<th style="width: 75px; text-align: center;">DOB</th>@endif<th>Items (Chest No)</th><th style="width: 70px; text-align: center;">Present</th></tr></thead>
<tbody>
@foreach($studentRows as $row)
<tr>
<td style="text-align: center;">{{ $loop->iteration }}</td>
<td>{{ $row['student']->name }}</td>
@if($event->event_type === 'sports')
<td style="text-align: center;">{{ $row['dob'] ?? '—' }}</td>
@endif
<td>@foreach($row['events'] as $e){{ $e['event_name'] }} ({{ $e['chest_number'] }})@if(!$loop->last), @endif @endforeach</td>
<td style="text-align: center;"></td>
</tr>
@endforeach
</tbody></table></body></html>
