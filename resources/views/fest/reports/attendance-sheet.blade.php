<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Sheet — {{ $event->title }}</title>
    <style>
        @page {
            margin-top: 36mm;
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
        .header-container {
            position: fixed;
            top: -30mm;
            left: 0;
            right: 0;
            height: 24mm;
            border-bottom: 2px solid #0f172a;
            text-align: center;
        }
        .logo-img {
            max-height: 36px;
            margin-bottom: 2px;
        }
        .sahodaya-title {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 0;
            line-height: 1.1;
        }
        .event-subtitle {
            font-size: 10px;
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
        .team-divider td {
            background: #f1f5f9;
            color: #0f172a;
            font-weight: bold;
            font-size: 9px;
            padding: 4px 8px;
            border-top: 2px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .team-tag {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            font-size: 8px;
            font-weight: bold;
            padding: 1px 5px;
            border-radius: 3px;
            margin-top: 2px;
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
        <table style="width: 100%; border: none; margin-bottom: 0;">
            <tr>
                <td style="background: #0f172a; color: #ffffff; padding: 6px 10px; font-size: 11px; font-weight: bold; border: none; border-radius: 4px 0 0 0; text-transform: uppercase; letter-spacing: 0.03em;">
                    {{ $cleanTitle }}
                </td>
                <td style="background: #0f172a; color: #ffffff; padding: 6px 10px; font-size: 9px; text-align: right; border: none; border-radius: 0 4px 0 0;">
                    <span style="background: #334155; color: #f8fafc; padding: 2px 8px; border-radius: 10px;">{{ count($rows) }} {{ count($rows) === 1 ? 'Participant' : 'Participants' }}</span>
                </td>
            </tr>
        </table>
        <table>
            <thead>
                <tr>
                    <th style="width: 30px;" class="text-center">Sl</th>
                    @if($event->event_type === 'sports')
                        <th style="width: 45px;" class="text-center">Photo</th>
                    @endif
                    <th style="width: 65px;" class="text-center">Chest No</th>
                    @if(($audience ?? 'staff') === 'staff')
                        <th>Participant / Team Name</th>
                        @if($event->event_type === 'sports')
                            <th style="width: 75px;" class="text-center">DOB</th>
                        @endif
                        <th style="width: 32%;">School</th>
                    @endif
                    <th style="width: 90px;" class="text-center">Attendance</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $lastTeamKey = null;
                @endphp
                @foreach($rows as $i => $row)
                    @php
                        $teamName = $row['team_name'] ?? null;
                        $groupId = $row['group_id'] ?? null;
                        $currentTeamKey = $groupId ? 'group_'.$groupId : ($teamName ? 'team_'.$teamName.'_'.($row['school'] ?? '') : null);
                    @endphp

                    @if($currentTeamKey && $currentTeamKey !== $lastTeamKey)
                        @php $lastTeamKey = $currentTeamKey; @endphp
                        <tr class="team-divider">
                            <td colspan="{{ $event->event_type === 'sports' ? 7 : 5 }}">
                                <strong>TEAM: {{ strtoupper($teamName ?? 'Team Entry') }}</strong>
                                @if(!empty($row['school']))
                                    &bull; <span style="color: #475569;">{{ strtoupper($row['school']) }}</span>
                                @endif
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td class="text-center">{{ $i + 1 }}</td>
                        @if($event->event_type === 'sports')
                            <td class="text-center" style="padding: 2px;">
                                @if(!empty($row['photo_src']))
                                    <img src="{{ $row['photo_src'] }}" style="width: 26px; height: 26px; object-fit: cover; border-radius: 3px;" alt="">
                                @else
                                    <span style="color: #cbd5e1; font-size: 8px;">—</span>
                                @endif
                            </td>
                        @endif
                        <td class="text-center chest-no">{{ $row['reference'] ?? '—' }}</td>
                        @if(($audience ?? 'staff') === 'staff')
                            <td>
                                <strong>{{ $row['name'] ?? '' }}</strong>
                                @if(!empty($row['team_name']))
                                    <div><span class="team-tag">Team: {{ $row['team_name'] }}</span></div>
                                @endif
                            </td>
                            @if($event->event_type === 'sports')
                                <td class="text-center" style="font-size: 9px; color: #475569;">{{ $row['dob'] ?? '—' }}</td>
                            @endif
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
