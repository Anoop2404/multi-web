<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Medal Tally</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:4px;text-align:center}th{background:#f3f4f6}td:first-child{text-align:left}</style>
</head><body>
<h2 style="text-align:center">{{ $event->title }} — Medal Tally by School</h2>
<table><thead><tr><th>School</th><th>Gold</th><th>Silver</th><th>Bronze</th></tr></thead>
<tbody>
@foreach($rows as $row)
<tr><td>{{ $row['school_name'] }}</td><td>{{ $row['gold'] }}</td><td>{{ $row['silver'] }}</td><td>{{ $row['bronze'] }}</td></tr>
@endforeach
</tbody></table></body></html>
