<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>School-wise Results</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11.5px}table{width:100%;border-collapse:collapse}th{background:#1d3557;color:#fff;padding:5px 6px;font-size:11.5px}td{border:1px solid #000;padding:5px 6px;font-size:11px}</style>
</head><body>
    @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

@php $publishedOnly = $publishedOnly ?? false; @endphp
<h2 style="text-align:center">{{ $event->title }} — School-wise {{ $publishedOnly ? 'Published Results' : 'Results' }}</h2>
<p style="text-align:center;font-size:10px;color:#64748b;margin-top:2px">Generated on {{ now()->format('d M Y, h:i A') }}</p>
@if($publishedOnly)
@forelse($schoolResults ?? [] as $school)
<h3>{{ $school['school_name'] }}</h3>
<table><thead><tr><th>Student</th><th>Item</th><th>Rank</th><th>Grade</th></tr></thead><tbody>
@foreach($school['students'] as $student)
@foreach($student['results'] as $result)
<tr>
@if($loop->first)<td rowspan="{{ count($student['results']) }}" style="vertical-align:top">{{ $student['name'] }}</td>@endif
<td>{{ $result['item'] }}@if($result['category'])<br><small>{{ $result['category'] }}</small>@endif</td>
<td>{{ $result['rank'] ?? '—' }}</td><td>{{ $result['grade'] ?? '—' }}</td>
</tr>
@endforeach
@endforeach
</tbody></table>
@empty
<p>No published results for the selected school.</p>
@endforelse
@else
<table><thead><tr><th>Sl No</th><th>Item</th><th>School</th><th>Participant</th><th>Pos</th><th>Grade</th>@unless($publishedOnly)<th>Score</th>@endunless</tr></thead>
<tbody>
@foreach($marks as $m)
<tr>
<td>{{ $loop->iteration }}</td>
<td>{{ $m->item?->title }}</td>
<td>{{ strtoupper($m->participant?->registration?->school?->name ?? '') }}</td>
<td>{{ $m->participant?->student?->name ?? $m->participant?->teacher?->name ?? '' }}</td>
<td>{{ ! $publishedOnly || in_array((int) $m->position, [1, 2, 3], true) ? ($m->position ?? '—') : '—' }}</td><td>{{ $m->grade ?? '—' }}</td>@unless($publishedOnly)<td>{{ $m->score ?? '' }}</td>@endunless
</tr>
@endforeach
</tbody></table>
@endif
</body></html>
