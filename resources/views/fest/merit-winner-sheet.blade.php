<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
@page { size: A4 landscape; margin: 15mm; }
body { font-family: Arial, sans-serif; font-size: 12px; color: #111; }
h1 { font-size: 19px; margin: 0 0 6px; }
.subtitle { margin-bottom: 14px; }
table { width: 100%; border-collapse: collapse; }
thead { display: table-header-group; }
tr { page-break-inside: avoid; }
th, td { border: 1px solid #555; padding: 8px 6px; text-align: left; }
th { background: #eee; }
.number { text-align: center; width: 5%; }
</style></head><body>
<h1>{{ $event->title }} — Merit winner verification sheet</h1>
<div class="subtitle">{{ $item->item_code ? '['.$item->item_code.'] ' : '' }}{{ $item->title }} · {{ $rows->count() }} winners</div>
<table><thead><tr><th class="number">Sl No</th><th class="number">Rank</th><th>Student name</th><th>School</th><th>Fest ID</th><th>Chest No</th><th>Grade</th><th>Verified / remarks</th></tr></thead>
<tbody>@foreach($rows as $row)
<tr><td class="number">{{ $loop->iteration }}</td><td class="number">{{ $row['rank'] ?? '—' }}</td><td>{{ $row['name'] }}</td><td>{{ $row['school'] }}</td><td>{{ $row['fest_id'] ?? '—' }}</td><td>{{ $row['chest_no'] ?? '—' }}</td><td>{{ $row['grade'] ?? '—' }}</td><td style="width:15%"></td></tr>
@endforeach</tbody></table>
</body></html>
