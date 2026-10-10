<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">
<title>Individual Championship</title>
<style>
@page { size:A4 portrait; margin:15mm; }
body { font-family:DejaVu Sans,sans-serif; font-size:11px; color:#172033; }
h1 { font-size:17px; margin:0 0 6px; } h2 { font-size:13px; margin:18px 0 8px; }
table { width:100%; border-collapse:collapse; margin-bottom:16px; }
th,td { border:1px solid #8792a3; padding:8px; text-align:left; }
th { background:#eef2f7; } thead { display:table-header-group; } tr { page-break-inside:avoid; }
.note { color:#7c5319; } .muted { color:#64748b; }
</style></head><body>
<h1>{{ $orgName }}</h1><p>{{ $event->title }} — Individual Championship</p>
@if($preview)<p class="note">Admin preview — includes categories hidden from the public portal.</p>@endif
@forelse($rows->groupBy('category') as $category => $students)
<h2>{{ $labels[$category] ?? $category }}</h2>
<table><thead><tr><th>Rank</th><th>Student</th><th>School</th><th>Gender</th><th>Points</th></tr></thead><tbody>
@foreach($students->sortBy('rank') as $row)
<tr><td>{{ $row['rank'] }}</td><td>{{ $row['student']['name'] }}</td><td>{{ $row['school'] ?? '' }}</td><td>{{ ucfirst($row['gender'] ?? '') }}</td><td>{{ $row['points'] }}</td></tr>
@endforeach
</tbody></table>
@empty<p class="muted">No qualifying winners for the selected categories.</p>@endforelse
</body></html>
