<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Overall Ranking</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}table{width:100%;border-collapse:collapse;margin-top:10px}th{background:#1d3557;color:#fff;padding:5px 8px}td{border-bottom:1px solid #ccc;padding:5px 8px}</style>
</head><body>
<h2 style="text-align:center">{{ $event->title }} — Overall School Ranking</h2>
<table><thead><tr><th>#</th><th>School</th><th>Gold</th><th>Silver</th><th>Bronze</th><th>Total Pts</th></tr></thead>
<tbody>
@foreach($schools as $i => $s)
<tr><td>{{ $s->rank ?? ($i+1) }}</td><td>{{ strtoupper($s->name) }}</td><td>{{ $s->gold }}</td><td>{{ $s->silver }}</td><td>{{ $s->bronze }}</td><td>{{ $s->total_points }}</td></tr>
@endforeach
</tbody></table></body></html>
