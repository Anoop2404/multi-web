<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Item List</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:11.5px}
h2{text-align:center;margin:0 0 4px}
.meta{text-align:center;color:#555;margin-bottom:10px;font-size:11px}
table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:4px 5px;font-size:10.5px}th{background:#f3f4f6;font-size:11px}
</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2>{{ $event->title }} — Item registration counts</h2>
<p class="meta">All schools · submitted and approved registrations</p>
<table>
<thead><tr>
    <th>Item Name</th><th>Category</th><th>Type</th><th>Schools</th><th>Total Regs</th><th>Approved</th><th>Pending</th><th>Students / Teams</th>
</tr></thead>
<tbody>
@foreach($items as $item)
<tr>
    <td><strong>{{ $item->title }}</strong></td>
    <td>{{ strtoupper($item->class_group ?? '—') }}</td>
    <td>{{ ($item->participant_type ?? 'individual') === 'individual' ? 'Indiv' : 'Team' }}</td>
    <td>{{ $item->school_count ?? 0 }}</td>
    <td><strong>{{ $item->registered_count }}</strong></td>
    <td>{{ $item->approved }}</td>
    <td>{{ $item->pending }}</td>
    <td>
        @if(($item->participant_type ?? 'individual') === 'individual')
            {{ $item->participants }} students
        @else
            {{ $item->registered_count }} teams/groups
        @endif
    </td>
</tr>
@endforeach
</tbody></table>
</body></html>
