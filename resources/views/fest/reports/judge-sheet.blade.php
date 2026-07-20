<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Judge Sheet</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}h1{font-size:14px;text-align:center}table{width:100%;border-collapse:collapse;margin-top:8px}th{background:#023e8a;color:#fff;padding:5px 4px;font-size:9px}td{border:1px solid #aaa;padding:5px 4px;min-height:30px}.score-cell{min-height:30px}</style>
</head><body>
<h1>Judge Sheet — {{ $item->title }}</h1>
<p style="text-align:center;font-size:10px;color:#444">
@if($schedule) {{ $schedule->scheduled_at?->format('d M Y H:i') }} · Stage: {{ $schedule->stage ?? '—' }} @else Schedule TBA @endif
@if(($audience ?? 'staff') === 'public') · Public copy (chest/reg only) @endif
</p>
<table><thead><tr><th>Sl No</th><th>Order</th><th>Ref</th>
@if(($audience ?? 'staff') === 'staff')<th>School</th><th>Participant</th>@endif
@foreach($criteria as $c)<th>{{ $c->name }}<br><small>/ {{ $c->max_marks }}</small></th>@endforeach
<th>Total</th><th>Remarks</th></tr></thead>
<tbody>
@foreach($rows as $row)
<tr>
<td>{{ $loop->iteration }}</td>
<td>{{ $row['order'] ?? '—' }}</td>
<td>{{ $row['reference'] ?? '—' }}</td>
@if(($audience ?? 'staff') === 'staff')
<td>{{ strtoupper($row['school'] ?? '—') }}</td>
<td>{{ $row['name'] ?? '—' }}</td>
@endif
@foreach($criteria as $c)<td class="score-cell"></td>@endforeach
<td class="score-cell"></td><td class="score-cell"></td>
</tr>
@endforeach
</tbody></table>
<p style="margin-top:24px;font-size:10px">Judge: _________________ Signature: _________________ Date: _________</p>
</body></html>
