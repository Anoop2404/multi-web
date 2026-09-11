<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Clashes</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:12px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:6px;font-size:11.5px}th{background:#fef3c7;font-size:12px}</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }} — Schedule Clashes — {{ $school->name }}</h2>
<p style="text-align:center;font-size:10px;color:#64748b;margin-top:2px">Generated on {{ now()->format('d M Y, h:i A') }}</p>
<table><thead><tr><th>Student</th><th>Item 1</th><th>Item 2</th></tr></thead>
<tbody>
@forelse($conflicts as $c)
<tr>
    <td>{{ $c['student_name'] }}</td>
    <td>{{ $c['event1'] }}<br><small>{{ implode(' · ', array_filter([$c['item1_category'] ?? null, $c['item1_gender'] ?? null, $c['item1_type'] ?? null, $c['item1_stage'] ?? null, $c['item1_time'] ?? null])) }}</small></td>
    <td>{{ $c['event2'] }}<br><small>{{ implode(' · ', array_filter([$c['item2_category'] ?? null, $c['item2_gender'] ?? null, $c['item2_type'] ?? null, $c['item2_stage'] ?? null, $c['item2_time'] ?? null])) }}</small></td>
</tr>
@empty
<tr><td colspan="3" style="text-align:center">No clashes detected.</td></tr>
@endforelse
</tbody></table></body></html>
