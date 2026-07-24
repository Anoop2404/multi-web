<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Sheet — {{ $event->title }}</title>
    <style>
        @page {
            margin: 26mm 10mm 16mm 10mm;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #0f172a;
            margin: 0;
            padding: 0;
        }
        .header-container {
            position: fixed;
            top: -22mm;
            left: 0;
            right: 0;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 6px;
            text-align: center;
        }
        .logo-img {
            max-height: 40px;
            margin-bottom: 4px;
        }
        .sahodaya-title {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 0;
            line-height: 1.2;
        }
        .event-subtitle {
            font-size: 11px;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-top: 3px;
        }
        .footer-container {
            position: fixed;
            bottom: -10mm;
            left: 0;
            right: 0;
            border-top: 1px solid #cbd5e1;
            padding-top: 4px;
            font-size: 8px;
            color: #64748b;
            text-align: center;
        }
        .item-block {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .item-heading {
            background: #0f172a;
            color: #ffffff;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: bold;
            border-radius: 4px 4px 0 0;
            display: block;
            box-sizing: border-box;
        }
        .item-title {
            float: left;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .item-count {
            float: right;
            background: #334155;
            color: #f8fafc;
            font-size: 9px;
            padding: 1px 8px;
            border-radius: 10px;
            font-weight: normal;
        }
        .clear {
            clear: both;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }
        th {
            background: #f1f5f9;
            color: #334155;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }
        td {
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            font-size: 10px;
            color: #1e293b;
            vertical-align: middle;
        }
        tr:nth-child(even) td {
            background: #f8fafc;
        }
        .text-center {
            text-align: center;
        }
        .chest-no {
            font-weight: bold;
            color: #0f172a;
            font-size: 11px;
        }
        .school-name {
            text-transform: uppercase;
            color: #334155;
            font-size: 9px;
        }
    </style>
</head>
<body>

<div class="header-container">
    @if(!empty($logo))
        <img src="{{ $logo }}" class="logo-img" alt="">
    @endif
    <h1 class="sahodaya-title">{{ $sahodaya->name ?? 'Sahodaya' }}</h1>
    <div class="event-subtitle">{{ $event->title }} &bull; ATTENDANCE SHEET</div>
</div>

<div class="footer-container">
    Generated on {{ now()->format('d M Y, h:i A') }} &bull; Page {PAGE_NUM} of {PAGE_COUNT}
</div>

@forelse($rowsByItem as $itemName => $rows)
    @php
        $cleanTitle = str_replace('_', ' ', $itemName);
    @endphp
    <div class="item-block">
        <div class="item-heading">
            <span class="item-title">{{ $cleanTitle }}</span>
            <span class="item-count">{{ count($rows) }} {{ count($rows) === 1 ? 'Participant' : 'Participants' }}</span>
            <div class="clear"></div>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width: 35px;" class="text-center">Sl</th>
                    <th style="width: 70px;" class="text-center">Chest No</th>
                    @if(($audience ?? 'staff') === 'staff')
                        <th>Participant / Team Name</th>
                        <th style="width: 42%;">School</th>
                    @endif
                    <th style="width: 100px;" class="text-center">Attendance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $i => $row)
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td class="text-center chest-no">{{ $row['reference'] ?? '—' }}</td>
                        @if(($audience ?? 'staff') === 'staff')
                            <td><strong>{{ $row['name'] ?? '' }}</strong></td>
                            <td class="school-name">{{ strtoupper($row['school'] ?? '') }}</td>
                        @endif
                        <td class="text-center"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@empty
    <p style="text-align: center; margin-top: 40px; color: #64748b;">No participants to display.</p>
@endforelse

</body>
</html>
