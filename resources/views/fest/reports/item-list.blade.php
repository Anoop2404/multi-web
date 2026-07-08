<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Item List</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px}
h2{text-align:center;margin:0 0 4px}
.meta{text-align:center;color:#555;margin-bottom:10px}
table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:3px 4px}th{background:#f3f4f6}
</style>
</head><body>
<h2>{{ $event->title }} — Item registration counts</h2>
<p class="meta">All schools · submitted and approved registrations</p>
<table>
<thead><tr>
    <th>Head</th><th>Item</th><th>Class</th><th>Schools</th><th>Approved</th><th>Pending</th><th>Participants</th><th>Item IDs</th><th>Fee/item</th>
</tr></thead>
<tbody>
@foreach($items as $item)
<tr>
    <td>{{ $item->head_name ?? '—' }}</td>
    <td>{{ $item->title }}</td>
    <td>{{ strtoupper($item->class_group ?? '') }}</td>
    <td>{{ $item->school_count ?? '—' }}</td>
    <td>{{ $item->approved }}</td>
    <td>{{ $item->pending }}</td>
    <td>{{ $item->participants }}</td>
    <td>{{ $item->item_reg_assigned ?? '—' }}</td>
    <td>@if($item->fee_per_item !== null) ₹{{ $item->fee_per_item }}@else — @endif</td>
</tr>
@endforeach
</tbody></table>
</body></html>
