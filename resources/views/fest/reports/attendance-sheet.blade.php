<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Sheet — {{ $event->title }}</title>
    <style>
        @page {
            margin-top: 35mm;
            margin-bottom: 16mm;
            margin-left: 10mm;
            margin-right: 10mm;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #0f172a;
            margin: 0;
            padding: 0;
        }

        header {
            position: fixed;
            top: -28mm;
            left: 0px;
            right: 0px;
            height: 25mm;
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

        main {
            width: 100%;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }
        thead {
            display: table-header-group;
        }
        th {
            background: #f1f5f9;
            color: #334155;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }
        td {
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
            font-size: 9px;
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
            font-size: 10px;
        }
        .school-name {
            text-transform: uppercase;
            color: #334155;
            font-size: 8px;
        }
        .team-tag {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            font-size: 7px;
            font-weight: bold;
            padding: 1px 4px;
            border-radius: 3px;
            margin-top: 1px;
        }
        .member-badge {
            display: inline-block;
            background: #f0fdf4;
            color: #166534;
            font-size: 7px;
            font-weight: bold;
            padding: 1px 5px;
            border-radius: 3px;
            margin-left: 4px;
        }
        .photo-cell {
            width: 32px;
        }
        .photo-cell img {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #e2e8f0;
        }
        .photo-cell .initials {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            font-size: 8px;
            font-weight: bold;
            display: inline-block;
            text-align: center;
            line-height: 28px;
        }
        .item-heading-bar {
            background: #0f172a;
            color: #ffffff;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: bold;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 0;
            page-break-after: avoid;
        }
        .item-heading-bar .count-badge {
            float: right;
            background: #334155;
            color: #f8fafc;
            font-size: 9px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: normal;
        }
    </style>
</head>
<body>

<header>
    @include('partials.pdf-branding-header', [
        'orgName' => $sahodaya->name ?? 'SAHODAYA',
        'logoSrc' => $logo ?? null,
        'docTitle' => 'ATTENDANCE SHEET',
    ])
</header>

<div class="footer-container">
    Generated on {{ now()->format('d M Y, h:i A') }} &bull; Page {PAGE_NUM} of {PAGE_COUNT}
</div>

<main>
@forelse($rowsByItem as $itemName => $rows)
    @php
        $cleanTitle = str_replace('_', ' ', $itemName);
    @endphp
    <div style="margin-bottom: 16px;">
        <div class="item-heading-bar">
            {{ $cleanTitle }}
            <span class="count-badge">{{ count($rows) }} {{ count($rows) === 1 ? 'Entry' : 'Entries' }}</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width: 28px;" class="text-center">Sl</th>
                    <th class="photo-cell"></th>
                    <th style="width: 50px;" class="text-center">Chest</th>
                    <th>Participant / Team</th>
                    @if($event->event_type === 'sports')
                        <th style="width: 70px;" class="text-center">DOB</th>
                    @endif
                    <th style="width: 28%;">School</th>
                    <th style="width: 70px;" class="text-center">Attendance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $i => $row)
                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td class="photo-cell text-center">
                            @if(!empty($row['photo_url']) && !empty($isPreview))
                                <img src="{{ $row['photo_url'] }}" alt="">
                            @elseif(!empty($isPreview))
                                <span class="initials">{{ strtoupper(substr($row['name'] ?? '?', 0, 1)) }}</span>
                            @else
                                <span class="initials">{{ strtoupper(substr($row['name'] ?? '?', 0, 1)) }}</span>
                            @endif
                        </td>
                        <td class="text-center chest-no">{{ $row['reference'] ?? '—' }}</td>
                        <td>
                            <strong style="font-size: 9px;">{{ $row['name'] ?? '' }}</strong>
                            @if(!empty($row['member_count']))
                                <span class="member-badge">Team · {{ $row['member_count'] }} members</span>
                            @endif
                        </td>
                        @if($event->event_type === 'sports')
                            <td class="text-center" style="font-size: 8px; color: #475569;">{{ $row['dob'] ?? '—' }}</td>
                        @endif
                        <td class="school-name">{{ strtoupper($row['school'] ?? '') }}</td>
                        <td class="text-center"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@empty
    <p style="text-align: center; margin-top: 40px; color: #64748b;">No participants to display.</p>
@endforelse
</main>

</body>
</html>
