<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Judge Sheet</title>
@php
    $critCount = count($criteria ?? []);
    $isStaff = ($audience ?? 'staff') === 'staff';
    $colCount = 3 + ($isStaff ? 2 : 0) + $critCount + 2; // Sl, Order, Ref, [School, Part], Criteria..., Total, Remarks
    $autoOrientation = $orientation ?? ($colCount > 5 ? 'landscape' : 'portrait');

    if ($colCount <= 6) {
        $thFont = '10.5px';
        $tdFont = '11px';
        $padding = '5px 6px';
    } elseif ($colCount <= 8) {
        $thFont = '9.5px';
        $tdFont = '10px';
        $padding = '4px 5px';
    } else {
        $thFont = '8.5px';
        $tdFont = '9.5px';
        $padding = '4px 3px';
    }
@endphp
<style>
@page { margin: 16px 20px; size: {{ $autoOrientation }}; margin-bottom: 24px; }
body{font-family:DejaVu Sans,sans-serif;font-size:{{ $tdFont }}}
h1{font-size:16px;text-align:center}
table{width:100%;border-collapse:collapse;margin-top:8px;table-layout:fixed}
th{background:#023e8a;color:#fff;padding:{{ $padding }};font-size:{{ $thFont }};word-wrap:break-word;overflow-wrap:break-word;word-break:break-word;vertical-align:bottom}
td{border:1px solid #000;padding:{{ $padding }};font-size:{{ $tdFont }};min-height:30px;word-wrap:break-word;overflow-wrap:break-word}
.score-cell{min-height:30px}
</style>
</head><body>
    @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h1>Judge Sheet — {{ $item->title }}</h1>
<p style="text-align:center;font-size:11px;color:#444">
@if($schedule) {{ $schedule->scheduled_at?->format('d M Y H:i') }} · Stage: {{ $schedule->stage ?? '—' }} @else Schedule TBA @endif
@if(($audience ?? 'staff') === 'public') · Public copy (chest/reg only) @endif
· Generated on {{ now()->format('d M Y, h:i A') }}
</p>
<table>
<thead><tr><th style="width: 28px;">Sl No</th><th style="width: 45px;">Order</th><th style="width: 50px;">Ref</th>
@if(($audience ?? 'staff') === 'staff')<th style="width: 18%;">School</th><th style="width: 18%;">Participant</th>@endif
@foreach($criteria as $c)<th>{{ $c->name }}<br><small>/ {{ $c->max_marks }}</small></th>@endforeach
<th style="width: 55px;">Total</th><th style="width: 15%;">Remarks</th></tr></thead>
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
<p style="margin-top:24px;font-size:11.5px">Judge: _________________ Signature: _________________ Date: _________</p>
</body></html>
