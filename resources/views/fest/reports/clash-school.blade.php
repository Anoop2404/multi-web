<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Clashes</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:5px}th{background:#fef3c7}</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }} — Schedule Clashes — {{ $school->name }}</h2>
<table><thead><tr><th>Student</th><th>Item 1</th><th>Item 2</th><th>Time</th></tr></thead>
<tbody>
@forelse($conflicts as $c)
<tr><td>{{ $c['student_name'] }}</td><td>{{ $c['event1'] }}</td><td>{{ $c['event2'] }}</td><td>{{ $c['time'] }}</td></tr>
@empty
<tr><td colspan="4" style="text-align:center">No clashes detected.</td></tr>
@endforelse
</tbody></table></body></html>
