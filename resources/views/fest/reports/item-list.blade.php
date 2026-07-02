<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Item List</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:4px}th{background:#f3f4f6}</style>
</head><body>
<h2 style="text-align:center">{{ $event->title }} — Item List</h2>
<table><thead><tr><th>Item</th><th>Class</th><th>Type</th><th>Registered</th></tr></thead>
<tbody>
@foreach($items as $item)
<tr><td>{{ $item->title }}</td><td>{{ strtoupper($item->class_group ?? '') }}</td><td>{{ $item->participant_type }}</td><td>{{ $item->registered_count }}</td></tr>
@endforeach
</tbody></table></body></html>
