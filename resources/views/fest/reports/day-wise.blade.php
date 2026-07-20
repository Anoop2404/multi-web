<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Day Schedule</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:4px}th{background:#f3f4f6}</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }} — Schedule for {{ $date }}</h2>
@if(($audience ?? 'staff') === 'public')
<p style="text-align:center;color:#666">Public schedule — participant identity hidden until results published.</p>
@endif
<table><thead><tr><th>Sl No</th><th>Order</th><th>Time</th><th>Item</th><th>Stage</th><th>Ref</th>
@if(($audience ?? 'staff') === 'staff')<th>Participant</th><th>School</th>@endif
</tr></thead>
<tbody>
@foreach($rows as $row)
<tr>
<td>{{ $loop->iteration }}</td>
<td>{{ $row['order'] ?? '—' }}</td>
<td>{{ $row['time'] ?? '—' }}</td>
<td>{{ $row['item'] ?? '—' }}</td>
<td>{{ $row['stage'] ?? '—' }}</td>
<td>{{ $row['reference'] ?? '—' }}</td>
@if(($audience ?? 'staff') === 'staff')
<td>{{ $row['name'] ?? '—' }}</td>
<td>{{ strtoupper($row['school'] ?? '—') }}</td>
@endif
</tr>
@endforeach
</tbody></table></body></html>
