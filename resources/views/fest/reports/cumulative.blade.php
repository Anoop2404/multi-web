<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Cumulative</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}table{width:100%;border-collapse:collapse}th{background:#1d3557;color:#fff;padding:5px}td{border:1px solid #ccc;padding:5px}</style>
</head><body>
<h2 style="text-align:center">{{ $event->title }} — Cumulative Points</h2>
<table><thead><tr><th>Sl No</th><th>Rank</th><th>School</th><th>Total Points</th></tr></thead>
<tbody>
@foreach($schools as $s)
<tr><td>{{ $loop->iteration }}</td><td>{{ $s->rank }}</td><td>{{ strtoupper($s->name) }}</td><td>{{ $s->total_points }}</td></tr>
@endforeach
</tbody></table></body></html>
