<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance — {{ $exam->title }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body { font-family: DejaVu Sans, sans-serif; color: #0f2744; font-size: 10px; margin: 0; padding: 0; }
        .page { margin: 40px 48px; }
        .header { border-bottom: 2px solid #0f2744; padding-bottom: 10px; margin-bottom: 12px; }
        .header table { width: 100%; }
        .logo { width: 48px; height: 48px; object-fit: contain; }
        .org { font-size: 14px; font-weight: 700; }
        .tag { font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: 0.55px; margin-top: 2px; }
        .title { font-size: 15px; font-weight: 700; margin: 8px 0 3px; }
        .meta { font-size: 9px; color: #64748b; margin-bottom: 12px; }
        .note { font-size: 9px; color: #334155; margin-bottom: 12px; padding: 6px 8px; background: #f8fafc; border: 1px solid #e2e8f0; }
        table.sheet { width: 100%; border-collapse: collapse; }
        table.sheet th { background: #0f2744; color: #fff; text-align: left; padding: 8px 6px; font-size: 8px; text-transform: uppercase; }
        table.sheet td { padding: 8px 6px; border: 1px solid #cbd5e1; vertical-align: middle; }
        table.sheet tr:nth-child(even) td { background: #f8fafc; }
        .sl { width: 36px; text-align: center; }
        .mark { text-align: center; width: 72px; }
        .box { display: inline-block; width: 22px; height: 22px; border: 2px solid #0f2744; vertical-align: middle; }
        .empty { text-align: center; color: #94a3b8; padding: 24px 0; }
        .footer { margin-top: 14px; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 6px; }
        .sign-line { margin-top: 28px; width: 100%; }
        .sign-line td { width: 33%; padding-top: 36px; font-size: 9px; color: #475569; border-top: 1px solid #94a3b8; text-align: center; }
    </style>
</head>
<body>
<div class="page">
    <div class="header">
        <table>
            <tr>
                @if(!empty($logoSrc))
                    <td style="width:58px;vertical-align:middle;"><img class="logo" src="{{ $logoSrc }}" alt=""></td>
                @endif
                <td style="vertical-align:middle;">
                    <div class="org">{{ $orgName }}</div>
                    <div class="tag">Talent Search · Attendance sheet</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="title">{{ $exam->title }}@if($exam->code) <span style="font-weight:400;font-size:11px">({{ $exam->code }})</span>@endif</div>
    <div class="meta">
        {{ count($rows) }} candidate(s)
        @if($exam->scheduled_at) · {{ $exam->scheduled_at->format('d M Y · h:i A') }} @endif
        @if($exam->venue) · {{ $exam->venue }} @endif
    </div>
    <div class="note">Physical verification sheet — mark attendance in the blank boxes (✓ present / A absent).</div>

    @if(empty($rows))
        <p class="empty">No registrations yet.</p>
    @else
        <table class="sheet">
            <thead>
                <tr>
                    <th class="sl">Sl</th>
                    <th>Reg. no.</th>
                    <th>Name</th>
                    <th>Class</th>
                    <th>School</th>
                    <th class="mark">Present</th>
                    <th class="mark">Absent</th>
                    <th style="width:100px;text-align:center">Sign</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td class="sl">{{ $row['sl'] }}</td>
                        <td>{{ $row['hall_ticket_no'] }}</td>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['class'] }}</td>
                        <td>{{ $row['school'] }}</td>
                        <td class="mark"><span class="box"></span></td>
                        <td class="mark"><span class="box"></span></td>
                        <td>&nbsp;</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="sign-line">
        <tr>
            <td>Prepared by</td>
            <td>Verified by</td>
            <td>Sahodaya seal / date</td>
        </tr>
    </table>
    <div class="footer">Generated {{ $generatedAt }} · Blank marks for physical verification</div>
</div>
</body>
</html>
