<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Registration List</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:3px 5px}th{background:#f3f4f6}</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }} — Registration Master List</h2>
<table><thead><tr><th>Sl No</th><th>School</th><th>Item</th><th>Class</th><th>Students</th><th>Chest</th><th>Status</th></tr></thead>
<tbody>
@php $slNo = 0; @endphp
@foreach($rows as $r)
@foreach($r->participants as $p)
@php $slNo++; @endphp
<tr>
<td>{{ $slNo }}</td>
<td>{{ strtoupper($r->school?->name ?? '') }}</td>
<td>{{ $r->item?->title }}</td>
<td>{{ strtoupper($r->item?->class_group ?? '') }}</td>
<td>{{ $p->student?->name ?? $p->teacher?->name ?? '—' }}</td>
<td>{{ $p->chest_no ?? '—' }}</td>
<td>{{ $r->status }}</td>
</tr>
@endforeach
@endforeach
</tbody></table></body></html>
