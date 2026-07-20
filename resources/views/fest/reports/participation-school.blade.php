<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Participation Limits</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}h2{text-align:center}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px}th{background:#f3f4f6}</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2>{{ $event->title }}</h2>
<p style="text-align:center;color:#555">{{ $school->name }} — Participation limits</p>
<table><thead><tr><th>Limit type</th><th>Used</th><th>Limit</th></tr></thead>
<tbody>
@foreach($used as $type => $count)
<tr>
    <td style="text-transform:capitalize">{{ str_replace('_', ' ', $type) }}</td>
    <td>{{ $count }}</td>
    <td>{{ $limits[$type] ?? '—' }}</td>
</tr>
@endforeach
</tbody></table></body></html>
