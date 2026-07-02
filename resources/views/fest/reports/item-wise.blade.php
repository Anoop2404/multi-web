<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Item Results</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}table{width:100%;border-collapse:collapse}th{background:#1d3557;color:#fff;padding:4px}td{border:1px solid #ccc;padding:4px}</style>
</head><body>
<h2 style="text-align:center">{{ $event->title }} — {{ $item?->title ?? 'Item' }} (Top {{ $topN }})</h2>
<table><thead><tr><th>Pos</th><th>Participant</th><th>School</th><th>Grade</th><th>Score</th></tr></thead>
<tbody>
@foreach($marks as $m)
<tr>
<td>{{ $m->position ?? '—' }}</td>
<td>{{ $m->participant?->student?->name ?? '' }}</td>
<td>{{ $m->participant?->registration?->school?->name ?? '' }}</td>
<td>{{ $m->grade ?? '—' }}</td><td>{{ $m->score ?? '' }}</td>
</tr>
@endforeach
</tbody></table></body></html>
