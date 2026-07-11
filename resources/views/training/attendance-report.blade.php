<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance report — {{ $program->title }}</title>
    <style>
        /* DomPDF: `* { margin:0 }` zeroes @page margins — use .page margin instead. */
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; font-family: DejaVu Sans, sans-serif; color: #0f2744; font-size: 10px; }
        .page { margin: 36px 40px; }
        .header { border-bottom: 2px solid #0f2744; padding-bottom: 10px; margin-bottom: 12px; }
        .header table { width: 100%; }
        .logo { width: 48px; height: 48px; object-fit: contain; }
        .org { font-size: 14px; font-weight: 700; }
        .tag { font-size: 8px; color: #64748b; text-transform: uppercase; letter-spacing: 0.55px; margin-top: 2px; }
        .title { font-size: 15px; font-weight: 700; margin: 8px 0 3px; }
        .meta { font-size: 9px; color: #64748b; margin-bottom: 12px; }
        table.report { width: 100%; border-collapse: collapse; }
        table.report th {
            background: #0f2744; color: #fff; text-align: left;
            padding: 6px 5px; font-size: 8px; text-transform: uppercase; letter-spacing: 0.3px;
        }
        table.report td { padding: 5px; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
        table.report tr:nth-child(even) td { background: #f8fafc; }
        .sl { width: 32px; text-align: center; }
        .mark { text-align: center; width: 52px; font-weight: 700; }
        .p { color: #15803d; }
        .a { color: #b91c1c; }
        .u { color: #94a3b8; font-weight: 500; }
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
                    <td style="width:58px;vertical-align:middle;">
                        <img class="logo" src="{{ $logoSrc }}" alt="">
                    </td>
                @endif
                <td style="vertical-align:middle;">
                    <div class="org">{{ $orgName }}</div>
                    <div class="tag">Teacher training · Attendance report</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="title">{{ $program->title }}</div>
    <div class="meta">
        {{ count($rows) }} participant(s) · {{ $sessions->count() }} session(s)
        @if($program->start_date)
            · {{ $program->start_date->format('d M Y') }}
            @if($program->end_date && ! $program->end_date->isSameDay($program->start_date))
                – {{ $program->end_date->format('d M Y') }}
            @endif
        @endif
        @if($program->venue)
            · {{ $program->venue }}
        @endif
    </div>

    @if(empty($rows))
        <p class="empty">No attendance data yet.</p>
    @else
        <table class="report">
            <thead>
                <tr>
                    <th class="sl">Sl</th>
                    <th>Teacher name</th>
                    <th>Category</th>
                    <th>School</th>
                    <th>Present</th>
                    @foreach($sessions as $session)
                        <th class="mark">
                            {{ $session->title }}
                            @if($session->scheduled_at)
                                <br>{{ $session->scheduled_at->format('d M') }}
                            @endif
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $i => $row)
                    <tr>
                        <td class="sl">{{ $i + 1 }}</td>
                        <td>{{ $row['teacher_name'] !== '' ? $row['teacher_name'] : '—' }}</td>
                        <td>{{ ($row['category'] ?? '') !== '' ? $row['category'] : '—' }}</td>
                        <td>{{ $row['school_name'] !== '' ? $row['school_name'] : '—' }}</td>
                        <td>{{ $row['days_present'] }}/{{ $row['total_sessions'] }}</td>
                        @foreach($sessions as $session)
                            @php $mark = $row['session_'.$session->id] ?? 'unmarked'; @endphp
                            <td class="mark">
                                @if($mark === 'present')
                                    <span class="p">P</span>
                                @elseif($mark === 'absent')
                                    <span class="a">A</span>
                                @else
                                    <span class="u">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">Generated {{ $generatedAt }} · P = Present · A = Absent · — = Not marked</div>
</div>
</body>
</html>
