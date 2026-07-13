<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Item Registration Counts</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px}
h2{text-align:center;margin:0} .meta{text-align:center;color:#555;margin-bottom:10px}
table{width:100%;border-collapse:collapse;margin-bottom:12px}
th,td{border:1px solid #ccc;padding:3px 4px} th{background:#f3f4f6}
.section{font-weight:bold;margin:8px 0 4px}
</style></head><body>
<h2>{{ $event->title }}</h2>
<p class="meta">{{ $school->name }} — Item registration &amp; fees</p>

<p class="section">Summary by {{ ($event->event_type ?? null) === 'sports' ? 'Event Head' : 'item head' }}</p>
<table><thead><tr>
    <th>Head</th><th>Items</th><th>Regs</th><th>Approved</th><th>Pending</th><th>Participants</th><th>Max item regs</th><th>Est. fee</th>
</tr></thead><tbody>
@foreach($headSummary as $h)
<tr>
    <td>{{ $h['head_name'] }}</td>
    <td>{{ $h['item_count'] }}</td>
    <td>{{ $h['registration_count'] ?? 0 }}</td>
    <td>{{ $h['approved_count'] ?? 0 }}</td>
    <td>{{ $h['pending_count'] ?? 0 }}</td>
    <td>{{ $h['participant_count'] }}</td>
    <td>{{ $h['busiest_item_regs'] ?? $h['max_item_reg_count'] ?? 0 }}</td>
    <td>@if(($h['estimated_fee'] ?? 0) > 0) ₹{{ $h['estimated_fee'] }}@else — @endif</td>
</tr>
@endforeach
</tbody></table>

<p class="section">By competition item</p>
<table><thead><tr>
    <th>Head</th><th>Item</th><th>Approved</th><th>Pending</th><th>Participants</th><th>Item IDs</th><th>Max</th><th>Fee/item</th><th>Line fee</th>
</tr></thead><tbody>
@foreach($rows as $r)
<tr>
    <td>{{ $r['head_name'] ?? '—' }}</td>
    <td>{{ $r['title'] }}</td>
    <td>{{ $r['approved'] }}</td>
    <td>{{ $r['pending'] }}</td>
    <td>{{ $r['participant_count'] }}</td>
    <td>{{ $r['item_reg_assigned'] }}</td>
    <td>{{ $r['max_per_school'] ?? '—' }}</td>
    <td>@if($r['fee_per_item'] !== null) ₹{{ $r['fee_per_item'] }}@else — @endif</td>
    <td>@if($r['line_fee'] !== null) ₹{{ $r['line_fee'] }}@else — @endif</td>
</tr>
@endforeach
</tbody></table>
<p style="text-align:right;font-size:9px">
    Total registrations: {{ $totals['registrations'] ?? 0 }} · Estimated fee: ₹{{ $totals['estimated_fee'] ?? 0 }}
</p>
</body></html>
