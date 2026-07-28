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
        .report-header {
            border-bottom: 2px solid #0f172a;
            margin-bottom: 12px;
            padding-bottom: 8px;
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
        .team-divider td {
            background: #f1f5f9;
            color: #0f172a;
            font-weight: bold;
            font-size: 9px;
            padding: 4px 6px;
            border-top: 2px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .team-divider {
            page-break-inside: avoid;
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

<div class="report-header">
    @include('partials.pdf-branding-header', [
        'orgName' => $sahodaya->name ?? 'SAHODAYA',
        'logoSrc' => $logo ?? null,
        'docTitle' => 'ATTENDANCE SHEET',
    ])
</div>

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
            <span class="count-badge">{{ count($rows) }} {{ count($rows) === 1 ? 'Participant' : 'Participants' }}</span>
        </div>
        <table>
            <thead>
                <tr>
                    <th style="width: 28px;" class="text-center">Sl</th>
                    <th class="photo-cell"></th>
                    <th style="width: 55px;" class="text-center">Chest No</th>
                    <th>Participant / Team Name</th>
                    @if($event->event_type === 'sports')
                        <th style="width: 75px;" class="text-center">DOB</th>
                    @endif
                    <th style="width: 28%;">School</th>
                    <th style="width: 80px;" class="text-center">Attendance</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $lastTeamKey = null;
                @endphp
                @foreach($rows as $i => $row)
                    @php
                        $teamName = $row['team_name'] ?? null;
                        $school = $row['school'] ?? '';
                        $groupId = $row['group_id'] ?? null;
                        $currentTeamKey = $groupId
                            ? 'g_'.$groupId
                            : ($teamName ? 't_'.$teamName.'_'.$school : null);
                    @endphp

                    @if($currentTeamKey && $currentTeamKey !== $lastTeamKey)
                        @php $lastTeamKey = $currentTeamKey; @endphp
                        <tr class="team-divider">
                            <td colspan="{{ $event->event_type === 'sports' ? 7 : 6 }}">
                                <strong>TEAM: {{ strtoupper($teamName ?? 'Team Entry') }}</strong>
                                @if(!empty($school))
                                    &bull; <span style="color: #475569;">{{ strtoupper($school) }}</span>
                                @endif
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        <td class="photo-cell text-center">
                            @if(!empty($row['photo_url']))
                                <img src="{{ $row['photo_url'] }}" alt="">
                            @else
                                <span class="initials">{{ strtoupper(substr($row['name'] ?? '?', 0, 1)) }}</span>
                            @endif
                        </td>
                        <td class="text-center chest-no">{{ $row['reference'] ?? '—' }}</td>
                        <td>
                            <strong style="font-size: 9px;">{{ $row['name'] ?? '' }}</strong>
                            @if(!empty($row['team_name']))
                                <div><span class="team-tag">Team: {{ $row['team_name'] }}</span></div>
                            @endif
                        </td>
                        @if($event->event_type === 'sports')
                            <td class="text-center" style="font-size: 8px; color: #475569;">{{ $row['dob'] ?? '—' }}</td>
                        @endif
                        <td class="school-name">{{ strtoupper($school) }}</td>
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
