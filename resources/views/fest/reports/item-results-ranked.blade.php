<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Item Results</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11.5px}table{width:100%;border-collapse:collapse}th{background:#1d3557;color:#fff;padding:5px;font-size:11.5px}td{border:1px solid #ccc;padding:5px;font-size:11px}</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }} — {{ $item?->title ?? 'Item' }} (Full Results, Rank Order)</h2>
<table><thead><tr><th>Rank</th><th>Chest No</th><th>Participant</th><th>School</th><th>Grade</th><th>Score</th></tr></thead>
<tbody>
@forelse($rows as $row)
<tr>
<td>{{ $row['position'] ?? '—' }}</td>
<td>{{ $row['chest_no'] ?? '—' }}</td>
<td>{{ $row['name'] ?? '' }}</td>
<td>{{ strtoupper($row['school'] ?? '') }}</td>
<td>{{ $row['grade'] ?? '—' }}</td>
<td>{{ $row['score'] ?? '—' }}</td>
</tr>
@empty
<tr><td colspan="6" style="text-align:center">No participants for this item.</td></tr>
@endforelse
</tbody></table></body></html>
