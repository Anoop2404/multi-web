<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>School-wise Results</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}table{width:100%;border-collapse:collapse}th{background:#1d3557;color:#fff;padding:4px 6px}td{border:1px solid #ccc;padding:4px 6px}</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }} — School-wise Results</h2>
<table><thead><tr><th>Sl No</th><th>Item</th><th>School</th><th>Participant</th><th>Pos</th><th>Grade</th><th>Score</th></tr></thead>
<tbody>
@foreach($marks as $m)
<tr>
<td>{{ $loop->iteration }}</td>
<td>{{ $m->item?->title }}</td>
<td>{{ strtoupper($m->participant?->registration?->school?->name ?? '') }}</td>
<td>{{ $m->participant?->student?->name ?? $m->participant?->teacher?->name ?? '' }}</td>
<td>{{ $m->position ?? '—' }}</td><td>{{ $m->grade ?? '—' }}</td><td>{{ $m->score ?? '' }}</td>
</tr>
@endforeach
</tbody></table></body></html>
