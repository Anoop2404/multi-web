<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Promotions</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:4px}th{background:#f3f4f6}</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }} — Promoted Qualifiers</h2>
<table><thead><tr><th>Sl No</th><th>Item</th><th>Student</th><th>School</th><th>Next Event</th><th>Date</th></tr></thead>
<tbody>
@foreach($quals as $q)
<tr>
<td>{{ $loop->iteration }}</td>
<td>{{ $q->participant?->registration?->item?->title }}</td>
<td>{{ $q->participant?->student?->name }}</td>
<td>{{ strtoupper($q->participant?->registration?->school?->name ?? '') }}</td>
<td>{{ $q->nextLevelEvent?->title }}</td>
<td>{{ $q->promoted_at?->format('d M Y') }}</td>
</tr>
@endforeach
</tbody></table></body></html>
