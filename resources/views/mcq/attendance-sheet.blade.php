<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance — {{ $exam->title }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body { font-family: DejaVu Sans, sans-serif; color: #0f2744; font-size: 11.5px; margin: 0; padding: 0; }
        .class-page { page-break-after: always; margin: 40px 48px; }
        .class-page:last-child { page-break-after: avoid; }
        .header { border-bottom: 2px solid #0f2744; padding-bottom: 10px; margin-bottom: 12px; }
        .header table { width: 100%; }
        .logo { width: 48px; height: 48px; object-fit: contain; }
        .org { font-size: 15px; font-weight: 700; }
        .tag { font-size: 9.5px; color: #64748b; text-transform: uppercase; letter-spacing: 0.55px; margin-top: 2px; }
        .title { font-size: 16px; font-weight: 700; margin: 8px 0 3px; }
        .meta { font-size: 10.5px; color: #64748b; margin-bottom: 10px; }
        .class-banner {
            background: #0f2744; color: #fff; padding: 7px 10px; margin-bottom: 10px;
            border-radius: 3px; font-size: 12px; font-weight: 700;
        }
        .class-banner span { font-weight: 400; font-size: 10.5px; color: #cbd5e1; margin-left: 8px; }
        .note { font-size: 10.5px; color: #334155; margin-bottom: 12px; padding: 6px 8px; background: #f8fafc; border: 1px solid #000; }
        table.sheet { width: 100%; border-collapse: collapse; }
        table.sheet th { background: #0f2744; color: #fff; text-align: left; padding: 8px 6px; font-size: 10px; text-transform: uppercase; }
        table.sheet td { padding: 8px 6px; border: 1px solid #000; vertical-align: middle; font-size: 11px; }
        table.sheet tr:nth-child(even) td { background: #f8fafc; }
        .photo-cell { width: 30px; text-align: center; }
        .photo-cell img { width: 24px; height: 24px; border-radius: 50%; object-fit: cover; }
        .initials-avatar { display: inline-block; width: 24px; height: 24px; line-height: 24px; border-radius: 50%; background: #e2e8f0; color: #475569; font-weight: bold; font-size: 10px; text-align: center; }
        .mark { width: 52px; text-align: center; }
        .box { display: inline-block; width: 14px; height: 14px; border: 1.2px solid #0f2744; }
        .sl { width: 28px; text-align: center; }
        table.sign-line { width: 100%; margin-top: 22px; border-collapse: collapse; }
        table.sign-line td { width: 33.33%; padding-top: 28px; border-top: 1px solid #94a3b8; text-align: center; font-size: 10px; color: #64748b; }
        .footer { margin-top: 10px; font-size: 9.5px; color: #94a3b8; }
        .empty { color: #94a3b8; padding: 24px 0; text-align: center; }
    </style>
</head>
<body>
@php
    $groupedRows = $groupedRows ?? [];
    $totalCount = $totalCount ?? collect($groupedRows)->flatten(1)->count();
    $showSchoolColumn = empty($school);
@endphp

@if(empty($groupedRows))
<div class="class-page">
    <div class="header">
        <table>
            <tr>
                @if(!empty($logoSrc))
                    <td style="width:58px;vertical-align:middle;"><img class="logo" src="{{ $logoSrc }}" alt=""></td>
                @endif
                <td style="vertical-align:middle;">
                    <div class="org">{{ $orgName }}</div>
                    <div class="tag">Talent Search · Class-wise attendance sheet</div>
                </td>
            </tr>
        </table>
    </div>
    <div class="title">{{ $exam->title }}@if($exam->code) <span style="font-weight:400;font-size:11px">({{ $exam->code }})</span>@endif</div>
    <div class="meta">
        0 candidate(s)
        @if(!empty($school)) · {{ $school->name }} @endif
        @if(!empty($selectedClass)) · Class {{ $selectedClass }} @endif
        @if($exam->scheduled_at) · {{ $exam->scheduled_at->format('d M Y · h:i A') }} @endif
        @if($exam->venue) · {{ $exam->venue }} @endif
    </div>
    <p class="empty">No registrations yet.</p>
    <div class="footer">Generated {{ $generatedAt }} · Blank marks for physical verification</div>
</div>
@else
@foreach($groupedRows as $className => $rows)
<div class="class-page">
    <div class="header">
        <table>
            <tr>
                @if(!empty($logoSrc))
                    <td style="width:58px;vertical-align:middle;"><img class="logo" src="{{ $logoSrc }}" alt=""></td>
                @endif
                <td style="vertical-align:middle;">
                    <div class="org">{{ $orgName }}</div>
                    <div class="tag">Talent Search · Class-wise attendance sheet</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="title">{{ $exam->title }}@if($exam->code) <span style="font-weight:400;font-size:11px">({{ $exam->code }})</span>@endif</div>
    <div class="meta">
        {{ $totalCount }} candidate(s) total · {{ count($rows) }} in this class
        @if(!empty($school)) · {{ $school->name }} @endif
        @if($exam->scheduled_at) · {{ $exam->scheduled_at->format('d M Y · h:i A') }} @endif
        @if($exam->venue) · {{ $exam->venue }} @endif
    </div>

    <div class="class-banner">
        Class {{ $className }}
        <span>{{ count($rows) }} candidate(s)</span>
    </div>

    <div class="note">Physical verification sheet — mark attendance in the blank boxes (✓ present / A absent).</div>

    <table class="sheet">
        <thead>
            <tr>
                <th class="sl">Sl</th>
                <th class="photo-cell">Photo</th>
                <th>Reg. no.</th>
                <th>Name</th>
                @if($showSchoolColumn)
                    <th>School</th>
                @endif
                <th class="mark">Present</th>
                <th class="mark">Absent</th>
                <th style="width:100px;text-align:center">Sign</th>
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $row)
                <tr>
                    <td class="sl">{{ $row['sl'] }}</td>
                    <td class="photo-cell">
                        @if(!empty($row['photo_url']))
                            <img src="{{ $row['photo_url'] }}" alt="">
                        @else
                            <span class="initials-avatar">{{ strtoupper(substr($row['name'] ?? '?', 0, 1)) }}</span>
                        @endif
                    </td>
                    <td>{{ $row['hall_ticket_no'] }}</td>
                    <td>{{ $row['name'] }}</td>
                    @if($showSchoolColumn)
                        <td>{{ $row['school'] }}</td>
                    @endif
                    <td class="mark"><span class="box"></span></td>
                    <td class="mark"><span class="box"></span></td>
                    <td>&nbsp;</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sign-line">
        <tr>
            <td>Prepared by</td>
            <td>Verified by</td>
            <td>Sahodaya seal / date</td>
        </tr>
    </table>
    <div class="footer">Generated {{ $generatedAt }} · Blank marks for physical verification · Class {{ $className }}</div>
</div>
@endforeach
@endif
</body>
</html>
