<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Registration Register</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px;color:#111}
h2{text-align:center;margin:0 0 4px}
.meta{text-align:center;font-size:10px;color:#555;margin-bottom:12px}
.summary{margin-bottom:10px;padding:8px;background:#f8fafc;border:1px solid #e2e8f0}
table{width:100%;border-collapse:collapse}
th,td{border:1px solid #cbd5e1;padding:3px 4px;vertical-align:top}
th{background:#f1f5f9;font-size:8px;text-transform:uppercase}
</style>
</head><body>
<h2>{{ $event->title }}</h2>
<p class="meta">{{ strtoupper($school->name) }} — Registration &amp; fees register</p>
@if($summary)
<div class="summary">
    Items: <strong>{{ $summary['item_count'] ?? '—' }}</strong> &nbsp;|&nbsp;
    Total due: <strong>₹{{ $summary['total_due'] ?? '—' }}</strong> &nbsp;|&nbsp;
    Fee status: <strong>{{ ucfirst($summary['fee_status'] ?? '—') }}</strong>
    @if(!empty($summary['receipt_no'])) &nbsp;|&nbsp; Receipt: <strong>{{ $summary['receipt_no'] }}</strong>@endif
</div>
@endif
<table>
<thead><tr>
    <th>Sl No</th><th>Head</th><th>Participant</th><th>School reg</th><th>Fest ID</th><th>Item</th><th>Item reg</th><th>Status</th><th>Chest</th><th>Fee</th>
</tr></thead>
<tbody>
@forelse($rows as $row)
<tr>
    <td>{{ $loop->iteration }}</td>
    <td>{{ $row['head_name'] ?? '—' }}</td>
    <td>{{ $row['participant_name'] }}</td>
    <td>{{ $row['participant_reg_no'] }}</td>
    <td>{{ $row['level_reg'] }}</td>
    <td>{{ $row['item_title'] }}</td>
    <td>{{ $row['item_reg'] }}</td>
    <td>{{ $row['registration_status'] }}</td>
    <td>{{ $row['chest_no'] }}</td>
    <td>@if($row['item_fee'] !== null) ₹{{ $row['item_fee'] }}@else — @endif</td>
</tr>
@empty
<tr><td colspan="10" style="text-align:center;padding:16px">No registrations.</td></tr>
@endforelse
</tbody></table>
</body></html>
