<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Attendance — {{ $event->title }}</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:10px;color:#111;margin-top:80px}
.running-header{position:fixed;top:-70px;left:0;right:0;text-align:center}
.running-header img{height:40px;margin-bottom:4px}
.running-header .sahodaya{font-size:15px;font-weight:bold}
.running-header .event{font-size:12px;margin-top:2px}
.running-footer{position:fixed;bottom:-30px;left:0;right:0;text-align:center;font-size:9px;color:#666}
.item-heading{font-size:12px;font-weight:bold;background:#eef2ff;padding:5px 6px;margin-top:16px;border:1px solid #c7d2fe}
.item-heading:first-of-type{margin-top:0}
table{width:100%;border-collapse:collapse;margin-top:0}
th,td{border:1px solid #ccc;padding:4px}
th{background:#f3f4f6;text-align:left}
</style>
</head><body>

<div class="running-header">
    @if($logo ?? null)
        <img src="{{ $logo }}" alt="">
    @endif
    <p class="sahodaya">{{ $sahodaya->name ?? 'Sahodaya' }}</p>
    <p class="event">{{ $event->title }} — Attendance Sheet</p>
</div>
<div class="running-footer">Page {PAGE_NUM} of {PAGE_COUNT}</div>

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
                <th style="width:110px">Attendance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['reference'] ?? '—' }}</td>
                    @if(($audience ?? 'staff') === 'staff')
                        <td>{{ $row['name'] ?? '' }}</td>
                        <td>{{ strtoupper($row['school'] ?? '') }}</td>
                    @endif
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>
@empty
    <p>No participants to list.</p>
@endforelse
</body></html>
