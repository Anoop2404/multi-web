<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Medal Tally</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:12px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:5px 6px;text-align:center;font-size:11.5px}th{background:#f3f4f6;font-size:12px}td:first-child{text-align:left}</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }} — Medal Tally by School</h2>
<table><thead><tr><th>Sl No</th><th>School</th><th>Gold</th><th>Silver</th><th>Bronze</th></tr></thead>
<tbody>
@foreach($rows as $row)
<tr><td>{{ $loop->iteration }}</td><td>{{ strtoupper($row['school_name'] ?? '') }}</td><td>{{ $row['gold'] }}</td><td>{{ $row['silver'] }}</td><td>{{ $row['bronze'] }}</td></tr>
@endforeach
</tbody></table></body></html>
