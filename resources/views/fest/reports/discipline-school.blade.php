<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Discipline Participation</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}h2{text-align:center}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:5px}th{background:#f3f4f6}</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2>{{ $event->title }}</h2>
<p style="text-align:center;color:#555">{{ $school->name }} — Discipline breakdown</p>
<table><thead><tr><th>Discipline</th><th>Items</th><th>Approved</th><th>Pending</th></tr></thead>
<tbody>
@forelse($rows as $row)
<tr>
    <td>{{ $row['discipline_label'] ?? $row['discipline'] }}</td>
    <td>{{ $row['item_count'] }}</td>
    <td>{{ $row['approved'] }}</td>
    <td>{{ $row['pending'] }}</td>
</tr>
@empty
<tr><td colspan="4" style="text-align:center">No data.</td></tr>
@endforelse
</tbody></table></body></html>
