<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Item Schedule</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:4px}th{background:#f3f4f6}.meta{margin-bottom:10px;color:#444}</style>
</head><body>
<h2 style="text-align:center">{{ $event->title }} — Item schedule</h2>
<p class="meta" style="text-align:center">
    {{ $summary['scheduled'] }} scheduled · {{ $summary['unscheduled'] }} not scheduled · {{ $summary['total'] }} items
    @if($date) · Date filter: {{ $date }} @endif
</p>
<table>
<thead><tr><th>Item</th><th>Age</th><th>Date</th><th>Time</th><th>Venue</th><th>Stage</th></tr></thead>
<tbody>
@foreach($rows as $row)
<tr>
    <td>{{ $row['title'] }}</td>
    <td>{{ strtoupper($row['age_group'] ?? '—') }}</td>
    <td>{{ $row['scheduled_date'] ?? '—' }}</td>
    <td>{{ $row['scheduled_time'] ?? '—' }}</td>
    <td>{{ $row['venue'] ?? '—' }}</td>
    <td>{{ $row['stage'] ?? '—' }}</td>
</tr>
@endforeach
</tbody>
</table>
</body></html>
