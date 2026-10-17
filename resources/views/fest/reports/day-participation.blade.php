<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Day-wise participants</title>
<style>
@page { margin: 14mm 12mm 16mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #17324d; }
h1 { font-size: 20px; margin: 0 0 5px; } h2 { font-size: 14px; margin: 18px 0 7px; }
p { line-height: 1.5; } .muted { color: #536477; } .warning { background: #fff7dc; padding: 8px; }
table { border-collapse: collapse; width: 100%; margin: 7px 0 16px; table-layout: fixed; }
th { background: #17324d; color: white; text-align: left; font-size: 9px; }
th, td { border: 1px solid #d7e0e8; padding: 7px; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
thead { display: table-header-group; } tr { page-break-inside: avoid; } h2 { page-break-after: avoid; }
.school-register { page-break-before: always; }
.school { background: #edf3f8; color: #17324d; font-size: 11px; } .num { text-align: right; }
.footer { position: fixed; bottom: -10mm; font-size: 8px; color: #64748b; } .page:after { content: counter(page); }
</style></head><body>
<div class="footer">Day-wise participants | Generated {{ $generated }} | Page <span class="page"></span></div>
<h1>Day-wise participants</h1>
<p><strong>{{ $title }}</strong><br><span class="muted">{{ $basis }} | Generated {{ $generated }}</span></p>
<p>Group members count individually. A participant counts once per day and once in each overall total. Overall unique totals are calculated separately; do not add daily headcounts.</p>
@if($unidentified)<p class="warning">{{ $unidentified }} record(s) do not have an unambiguous student identity. Unique counts may overcount those students. Names alone are not merged.</p>@endif
@foreach($warnings as $warning)<p class="warning">{{ $warning }}</p>@endforeach
<table><thead><tr><th style="width:36%">Day / scope</th><th>Unique participants</th><th>Participant entries</th><th>Schools</th><th>Unverified records</th></tr></thead><tbody>
@foreach($summaryRows as $row)<tr>@foreach($row as $index => $value)<td class="{{ $index ? 'num' : '' }}">{{ $value }}</td>@endforeach</tr>@endforeach
</tbody></table>
@foreach($sections as $section)
@forelse($section['schools'] as $school)
<table class="school-register"><thead>
<tr><th colspan="5" class="school">{{ $school['name'] }} - {{ $school['count'] }} participant(s) | {{ $section['label'] }}</th></tr>
<tr><th style="width:6%">No.</th><th style="width:38%">Participant</th><th style="width:15%">Class</th><th style="width:21%">Student ID / roll</th><th style="width:20%">Stable ID available</th></tr>
</thead><tbody>
@foreach($school['students'] as $index => $student)<tr><td>{{ $index + 1 }}</td><td>{{ $student['name'] }}</td><td>{{ $student['class_name'] ?: '-' }}</td><td>{{ $student['identifier'] ?? '-' }}</td><td>{{ $student['identified'] ? 'Yes' : 'No' }}</td></tr>@endforeach
</tbody></table>
@empty<h2>{{ $section['label'] }}</h2><p class="muted">No participants in this selection.</p>@endforelse
@endforeach
</body></html>
