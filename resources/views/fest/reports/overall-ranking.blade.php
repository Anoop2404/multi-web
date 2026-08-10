<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Overall Ranking</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:12px}table{width:100%;border-collapse:collapse;margin-top:10px}th{background:#1d3557;color:#fff;padding:6px 8px;font-size:11.5px}td{border-bottom:1px solid #ccc;padding:6px 8px;font-size:11.5px}</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }} — Overall School Ranking</h2>
<table><thead><tr><th>#</th><th>School</th><th>Gold</th><th>Silver</th><th>Bronze</th><th>Total Pts</th></tr></thead>
<tbody>
@foreach($schools as $i => $s)
<tr><td>{{ $s->rank ?? ($i+1) }}</td><td>{{ strtoupper($s->name) }}</td><td>{{ $s->gold }}</td><td>{{ $s->silver }}</td><td>{{ $s->bronze }}</td><td>{{ $s->total_points }}</td></tr>
@endforeach
</tbody></table></body></html>
