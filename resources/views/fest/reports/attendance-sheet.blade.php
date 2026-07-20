<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Attendance — {{ $event->title }}</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#111}
.header{text-align:center;margin-bottom:14px}
.header .sahodaya{font-size:15px;font-weight:bold}
.header .event{font-size:13px;margin-top:2px}
.item-heading{font-size:12px;font-weight:bold;background:#eef2ff;padding:5px 6px;margin-top:16px;border:1px solid #c7d2fe}
.item-heading:first-of-type{margin-top:0}
table{width:100%;border-collapse:collapse;margin-top:0}
th,td{border:1px solid #ccc;padding:4px}
th{background:#f3f4f6;text-align:left}
</style>
</head><body>
<div class="header">
    <p class="sahodaya">{{ $sahodaya->name ?? 'Sahodaya' }}</p>
    <p class="event">{{ $event->title }} — Attendance Sheet</p>
</div>

@forelse($rowsByItem as $itemName => $rows)
    <div class="item-heading">{{ $itemName }} ({{ count($rows) }} participant{{ count($rows) === 1 ? '' : 's' }})</div>
    <table>
        <thead>
            <tr>
                <th style="width:40px">Sl. No.</th>
                <th style="width:70px">Chest No</th>
                @if(($audience ?? 'staff') === 'staff')
                    <th>Name</th>
                    <th>School</th>
                @endif
                <th style="width:70px">Present</th>
                <th style="width:70px">Absent</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['reference'] ?? '—' }}</td>
                    @if(($audience ?? 'staff') === 'staff')
                        <td>{{ $row['name'] ?? '' }}</td>
                        <td>{{ $row['school'] ?? '' }}</td>
                    @endif
                    <td></td>
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@empty
    <p>No participants to list.</p>
@endforelse
</body></html>
