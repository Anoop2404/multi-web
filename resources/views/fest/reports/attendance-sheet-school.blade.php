<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Attendance — {{ strtoupper($school->name) }}</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:9px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:3px}th{background:#f3f4f6}.photo{width:30px;text-align:center}.photo img,.initials{width:26px;height:26px;border-radius:50%;object-fit:cover;display:inline-block}.initials{background:#e2e8f0;color:#64748b;line-height:26px;font-weight:bold}</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }} — {{ strtoupper($school->name) }}</h2>
<table><thead><tr><th style="width: 35px; text-align: center;">Sl No</th><th class="photo"></th><th>Student</th>@if($showDob ?? false)<th style="width: 75px; text-align: center;">DOB</th>@elseif($showClass ?? false)<th style="width: 55px; text-align: center;">Class</th>@endif<th>Items (Chest No)</th><th style="width: 70px; text-align: center;">Present</th></tr></thead>
<tbody>
@foreach($studentRows as $row)
<tr>
<td style="text-align: center;">{{ $loop->iteration }}</td>
<td class="photo">@if(!empty($row['photo_url']))<img src="{{ $row['photo_url'] }}" alt="">@else<span class="initials">{{ strtoupper(substr($row['student']->name ?? '?', 0, 1)) }}</span>@endif</td>
<td>{{ $row['student']->name }}</td>
@if($showDob ?? false)
<td style="text-align: center;">{{ $row['dob'] ?? '—' }}</td>
@elseif($showClass ?? false)
<td style="text-align: center;">{{ $row['class'] ?? '—' }}</td>
@endif
<td>@foreach($row['events'] as $e){{ $e['event_name'] }} ({{ $e['chest_number'] }})@if(!$loop->last), @endif @endforeach</td>
<td style="text-align: center;"></td>
</tr>
@endforeach
</tbody></table></body></html>
