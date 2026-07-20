<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Item Order</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px}th{background:#fef3c7}</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }}</h2>
<h3 style="text-align:center">{{ $item->title }} — Performance Order</h3>
<p style="text-align:center;color:#666;font-size:10px">Public copy — chest / level registration numbers only</p>
<table><thead><tr><th>Order</th><th>Time</th><th>Ref</th><th>Stage</th></tr></thead>
<tbody>
@foreach($rows as $row)
<tr>
<td>{{ $row['order'] ?? '—' }}</td>
<td>{{ $row['time'] ?? '—' }}</td>
<td>{{ $row['reference'] ?? '—' }}</td>
<td>{{ $row['stage'] ?? '—' }}</td>
</tr>
@endforeach
</tbody></table></body></html>
