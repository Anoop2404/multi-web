<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Day-wise participants</title>
<style>
@page { size: A4 landscape; margin: 14mm 12mm 16mm; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #17324d; }
h1 { font-size: 20px; margin: 0 0 5px; } h2 { font-size: 14px; margin: 18px 0 7px; }
p { line-height: 1.5; } .muted { color: #536477; } .warning { background: #fff7dc; padding: 8px; }
table { border-collapse: collapse; width: 100%; margin: 7px 0 16px; table-layout: fixed; }
th { background: #17324d; color: white; text-align: left; font-size: 9px; }
th, td { border: 1px solid #d7e0e8; padding: 7px; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
thead { display: table-header-group; } tr { page-break-inside: avoid; } h2 { page-break-after: avoid; }
.center { text-align: center; }
.school { background: #edf3f8; color: #17324d; font-size: 11px; } .num { text-align: right; }
.footer { position: fixed; bottom: -10mm; font-size: 8px; color: #64748b; } .page:after { content: counter(page); }
</style></head><body>
<div class="footer">Day-wise participants | Generated {{ $generated }} | Page <span class="page"></span></div>
<h1>Day-wise participants</h1>
<p><strong>{{ $title }}</strong><br><span class="muted">{{ $basis }} | Generated {{ $generated }}</span></p>
<p>Group members count individually. A participant counts once per day and once in each overall total. Overall unique totals are calculated separately; do not add daily headcounts.</p>
@if($unidentified)<p class="warning">{{ $unidentified }} record(s) do not have an unambiguous student identity. Unique counts may overcount those students. Names alone are not merged.</p>@endif
@foreach($warnings as $warning)<p class="warning">{{ $warning }}</p>@endforeach
<p class="muted">Unspecified includes missing, unrecognised or conflicting gender records. Extra gender columns appear only when needed. Overall unique is for the selected scope.</p>
@php
    $datesPerTable = count($matrix['genders']) > 2 ? 2 : 4;
    $blocks = array_chunk(array_keys($matrix['dates']), $datesPerTable);
    if (!$blocks) $blocks = [[]];
@endphp
@foreach($blocks as $block)
@if(count($blocks) > 1)<h2>Dates {{ $loop->iteration }} of {{ count($blocks) }} — overall unique repeated for reference</h2>@endif
<table><thead>
<tr><th rowspan="2" style="width:30%">School name</th>
@foreach($block as $index)<th colspan="{{ count($matrix['genders']) }}" class="center">{{ $matrix['dates'][$index] }}</th>@endforeach
<th rowspan="2" style="width:10%" class="num">Overall unique</th></tr>
<tr>@foreach($block as $index)@foreach($matrix['genders'] as $label)<th class="num">{{ $label }}</th>@endforeach
@endforeach</tr>
</thead><tbody>
@forelse($matrix['rows'] as $row)
<tr><td>{{ $row['school'] }}</td>@foreach($block as $index)@foreach($row['days'][$index] as $count)<td class="num">{{ $count }}</td>@endforeach
@endforeach<td class="num"><strong>{{ $row['overall'] }}</strong></td></tr>
@empty<tr><td colspan="{{ 2 + count($block) * count($matrix['genders']) }}">No participants in this selection.</td></tr>@endforelse
<tr class="school"><td><strong>All schools</strong></td>@foreach($block as $index)@foreach($matrix['totals']['days'][$index] as $count)<td class="num">{{ $count }}</td>@endforeach
@endforeach<td class="num"><strong>{{ $matrix['totals']['overall'] }}</strong></td></tr>
</tbody></table>
@endforeach
</body></html>
