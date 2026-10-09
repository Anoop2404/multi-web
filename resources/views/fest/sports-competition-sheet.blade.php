<!DOCTYPE html>
<html><head><meta charset="utf-8">
<style>
@page { size: A4 landscape; margin: 15mm; }
body { margin: 0; color: #000; font-family: Arial, sans-serif; }
.sheet { page-break-after: always; }
.sheet:last-child { page-break-after: auto; }
table { width: 100%; border-collapse: collapse; table-layout: auto; }
th, td { border: 1px solid #222; text-align: center; padding: 2px 4px; }
.title { font-family: "Times New Roman", serif; font-size: 20px; font-weight: bold; height: 30px; }
.columns th { font-family: "Times New Roman", serif; font-size: 12px; height: 20px; }
td { font-size: 13px; height: 23px; }
.name { overflow-wrap: anywhere; font-size: 12px; }
.fest-meta { font-size: 11px; margin-bottom: 8px; }
</style></head><body>
@foreach($sheets as $sheet)
<div class="sheet">
<div class="fest-meta">{{ $event->title }}</div>
<table>
<colgroup><col style="width:6%"><col style="width:8%"><col style="width:9%"><col style="width:25%">
@for($i=0;$i<6;$i++)<col style="width:8.666%">@endfor</colgroup>
<thead><tr><th class="title" colspan="10">{{ mb_strtoupper($sheet['title']) }}</th></tr>
<tr class="columns"><th style="width:6%">SL NO</th><th style="width:8%">CHEST NO</th><th style="width:9%">FEST ID</th><th style="width:25%">NAME</th><th>HEATS</th><th>TIME</th><th>SEMI-FINAL</th><th>TIME</th><th>FINAL</th><th>RESULT</th></tr></thead>
<tbody>
@for($i=0;$i<16;$i++)
@php($row = $sheet['rows'][$i] ?? null)
<tr><td style="width:6%">{{ $row ? $sheet['offset'] + $i + 1 : '' }}</td><td style="width:8%">{{ $row['chest_no'] ?? '' }}</td><td style="width:9%">{{ $row['fest_id'] ?? '' }}</td><td class="name" style="width:25%">{{ mb_strtoupper($row['name'] ?? '') }}</td>
@for($j=0;$j<6;$j++)<td style="width:8.666%">&nbsp;</td>@endfor</tr>
@endfor
</tbody></table></div>
@endforeach
</body></html>
