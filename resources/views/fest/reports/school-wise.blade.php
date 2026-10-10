<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>School-wise Results</title>
<style>
@page { size: A4 portrait; margin: 15mm 15mm 20mm; }
body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 11px; line-height: 1.4; color: #0f172a; }
h2 { margin: 10px 0 4px; font-size: 16px; line-height: 1.3; }
h3 { margin: 18px 0 8px; font-size: 13px; page-break-after: avoid; break-after: avoid; }
.results-table { width: 100%; border-collapse: collapse; margin: 0 0 16px; table-layout: fixed; }
.results-table thead { display: table-header-group; }
.results-table tr { page-break-inside: avoid; break-inside: avoid; }
.results-table th { background: #1d3557; color: #fff; padding: 8px; border: 1px solid #1d3557; font-size: 11px; text-align: left; }
.results-table td { border: 1px solid #cbd5e1; padding: 8px; font-size: 10.5px; vertical-align: top; overflow-wrap: anywhere; word-wrap: break-word; }
.results-table small { color: #64748b; font-size: 9px; }
.published-results th:nth-child(1) { width: 7%; }
.published-results th:nth-child(2) { width: 28%; }
.published-results th:nth-child(3) { width: 43%; }
.published-results th:nth-child(4), .published-results th:nth-child(5) { width: 11%; text-align: right; }
.result-rank, .result-grade { text-align: right; white-space: nowrap; }
</style>
</head><body>
    @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

@php $publishedOnly = $publishedOnly ?? false; @endphp
<h2 style="text-align:center">{{ $event->title }} — School-wise {{ $publishedOnly ? 'Published Results' : 'Results' }}</h2>
<p style="text-align:center;font-size:10px;color:#64748b;margin-top:2px">Generated on {{ now()->format('d M Y, h:i A') }}</p>
@if($publishedOnly)
@forelse($schoolResults ?? [] as $school)
<h3>{{ $school['school_name'] }}</h3>
<table class="results-table published-results"><thead><tr><th>Sl No</th><th>Student</th><th>Item</th><th>Rank</th><th>Grade</th></tr></thead><tbody>
@foreach($school['students'] as $student)
@php $studentNumber = $loop->iteration; @endphp
@foreach($student['results'] as $result)
<tr>
@if($loop->first)<td rowspan="{{ count($student['results']) }}">{{ $studentNumber }}</td><td rowspan="{{ count($student['results']) }}" style="vertical-align:top">{{ $student['name'] }}</td>@endif
<td>{{ $result['item'] }}@if($result['category'])<br><small>{{ $result['category'] }}</small>@endif</td>
<td class="result-rank">{{ $result['rank'] ?? '—' }}</td><td class="result-grade">{{ $result['grade'] ?? '—' }}</td>
</tr>
@endforeach
@endforeach
</tbody></table>
@empty
<p>No published results for the selected school.</p>
@endforelse
@else
<table class="results-table"><thead><tr><th>Sl No</th><th>Item</th><th>School</th><th>Participant</th><th>Pos</th><th>Grade</th>@unless($publishedOnly)<th>Score</th>@endunless</tr></thead>
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
