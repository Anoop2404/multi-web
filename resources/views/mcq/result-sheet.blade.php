<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Results — {{ $exam->title }}</title>
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
        .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 8px; font-weight: 700; text-transform: uppercase; }
        .badge-ok { background: #dcfce7; color: #166534; }
        .badge-draft { background: #fef3c7; color: #92400e; }
        table.sheet { width: 100%; border-collapse: collapse; }
        table.sheet th { background: #0f2744; color: #fff; text-align: left; padding: 8px 6px; font-size: 8px; text-transform: uppercase; }
        table.sheet td { padding: 7px 6px; border: 1px solid #cbd5e1; }
        table.sheet tr:nth-child(even) td { background: #f8fafc; }
        .sl { width: 36px; text-align: center; }
        .num { text-align: right; width: 64px; }
        .empty { text-align: center; color: #94a3b8; padding: 24px 0; }
        .footer { margin-top: 14px; font-size: 8px; color: #94a3b8; border-top: 1px solid #e2e8f0; padding-top: 6px; }
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
                    <div class="tag">Talent Search · Result sheet</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="title">{{ $exam->title }}@if($exam->code) <span style="font-weight:400;font-size:11px">({{ $exam->code }})</span>@endif</div>
    <div class="meta">
        {{ count($rows) }} candidate(s)
        · <span class="badge {{ $published ? 'badge-ok' : 'badge-draft' }}">{{ $published ? 'Published' : 'Draft / unpublished' }}</span>
        @if($exam->result_date) · Result date {{ $exam->result_date->format('d M Y') }} @endif
    </div>

    @if(empty($rows))
        <p class="empty">No registrations yet.</p>
    @else
        <table class="sheet">
            <thead>
                <tr>
                    <th class="sl">Sl</th>
                    <th>Reg. no.</th>
                    <th>Name</th>
                    <th>School</th>
                    <th>Attendance</th>
                    <th class="num">Score</th>
                    <th class="num">%</th>
                    <th class="num">Grade</th>
                    <th class="num">Rank</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        <td class="sl">{{ $row['sl'] }}</td>
                        <td>{{ $row['hall_ticket_no'] }}</td>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['school'] }}</td>
                        <td>{{ $row['attendance'] }}</td>
                        <td class="num">{{ $row['score'] !== null ? number_format((float) $row['score'], 2) : '—' }}</td>
                        <td class="num">{{ $row['percentage'] !== null ? number_format((float) $row['percentage'], 1) : '—' }}</td>
                        <td class="num">{{ $row['grade'] ?? '—' }}</td>
                        <td class="num">{{ $row['rank'] ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">Generated {{ $generatedAt }}</div>
</div>
</body>
</html>
