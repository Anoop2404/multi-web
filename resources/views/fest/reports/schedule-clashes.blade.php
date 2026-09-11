<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Schedule Clashes</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}table{width:100%;border-collapse:collapse;margin-bottom:18px}th,td{border:1px solid #ccc;padding:5px 6px;font-size:10.5px;vertical-align:top}th{background:#fef3c7;font-size:11px}h3{font-size:13px;margin:14px 0 6px}small{color:#64748b}</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }} — Schedule Clashes{{ $school ? ' — '.$school->name : '' }}</h2>
<p style="text-align:center;font-size:10px;color:#64748b;margin-top:2px">
    {{ count($participant) }} participant clash(es) · {{ count($stage) }} stage conflict(s)
    · Generated on {{ now()->format('d M Y, h:i A') }}
</p>

<h3>Participant clashes</h3>
<table>
<thead><tr><th>Student</th><th>School</th><th>Item 1</th><th>Item 2</th></tr></thead>
<tbody>
@forelse($participant as $c)
<tr>
    <td>{{ $c['student_name'] }}</td>
    <td>{{ $c['school_name'] }}</td>
    <td>{{ $c['event1'] }}<br><small>{{ implode(' · ', array_filter([$c['item1_category'] ?? null, $c['item1_gender'] ?? null, $c['item1_type'] ?? null, $c['item1_stage'] ?? null, $c['item1_time'] ?? null])) }}</small></td>
    <td>{{ $c['event2'] }}<br><small>{{ implode(' · ', array_filter([$c['item2_category'] ?? null, $c['item2_gender'] ?? null, $c['item2_type'] ?? null, $c['item2_stage'] ?? null, $c['item2_time'] ?? null])) }}</small></td>
</tr>
@empty
<tr><td colspan="4" style="text-align:center">No participant clashes detected.</td></tr>
@endforelse
</tbody></table>

<h3>Stage conflicts</h3>
<table>
<thead><tr><th>Stage</th><th>Item 1</th><th>Item 2</th></tr></thead>
<tbody>
@forelse($stage as $c)
<tr>
    <td>{{ $c['stage'] }}{{ $c['venue'] ? ' · '.$c['venue'] : '' }}<br><small>{{ $c['date'] ?? '' }}</small></td>
    <td>{{ $c['item1'] }}<br><small>{{ implode(' · ', array_filter([$c['item1_category'] ?? null, $c['item1_gender'] ?? null, $c['item1_type'] ?? null, $c['item1_time'] ?? null])) }}</small></td>
    <td>{{ $c['item2'] }}<br><small>{{ implode(' · ', array_filter([$c['item2_category'] ?? null, $c['item2_gender'] ?? null, $c['item2_type'] ?? null, $c['item2_time'] ?? null])) }}</small></td>
</tr>
@empty
<tr><td colspan="3" style="text-align:center">No stage conflicts detected.</td></tr>
@endforelse
</tbody></table>
</body></html>
