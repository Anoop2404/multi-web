<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $threshold }}%+ Achievers — {{ $academicYear }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1a2236;
            font-size: 8.5px;
            margin: 0; padding: 0;
            background: #fff;
        }

        .page-header {
            width: 100%;
            background: #0b2558;
            padding: 14px 20px 0 20px;
        }

        .hdr-top {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .hdr-logo-cell {
            width: 62px;
            vertical-align: middle;
            text-align: right;
            padding-right: 10px;
        }
        .hdr-logo-cell img {
            width: 52px;
            height: 52px;
            object-fit: contain;
            display: inline-block;
        }
        .hdr-org-cell {
            vertical-align: middle;
            text-align: left;
        }
        .org-name {
            font-size: 18px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.3px;
            line-height: 1.1;
            display: block;
        }
        .org-sub {
            font-size: 7px;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-top: 3px;
            display: block;
        }

        .hdr-divider {
            border: none;
            border-top: 1px solid rgba(255,255,255,0.15);
            margin: 0;
        }

        .hdr-info-bar {
            width: 100%;
            border-collapse: collapse;
            padding: 6px 0 10px 0;
        }
        .info-cell {
            text-align: center;
            vertical-align: middle;
            padding: 0 8px;
            border-right: 1px solid rgba(255,255,255,0.12);
        }
        .info-cell:last-child { border-right: none; }
        .info-lbl {
            font-size: 6.5px;
            text-transform: uppercase;
            letter-spacing: 0.9px;
            color: rgba(255,255,255,0.4);
            display: block;
            margin-bottom: 2px;
        }
        .info-val {
            font-size: 9px;
            font-weight: 700;
            color: #ffffff;
            display: block;
        }
        .info-val-lg {
            font-size: 9.5px;
            font-weight: 700;
            color: #7eb3ff;
            display: block;
        }

        .header-stripe {
            width: 100%;
            height: 3px;
            background: #2563eb;
        }

        .content-wrap {
            padding: 12px 16px;
        }

        .section-banner {
            background: #0f2d5e;
            border: 1px solid #1e3a6e;
            color: #ffffff;
            padding: 6px 12px;
            font-size: 10px;
            font-weight: 700;
            border-radius: 4px;
            margin-top: 10px;
            margin-bottom: 6px;
            page-break-inside: avoid;
        }

        table.report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.report-table thead tr { background: #0b2558; }
        table.report-table th {
            color: #c8d9f5;
            text-align: left;
            padding: 5px 6px;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            border-right: 1px solid rgba(255,255,255,0.08);
        }
        table.report-table th:last-child { border-right: none; }
        table.report-table td {
            padding: 5px 6px;
            border-bottom: 1px solid #e8edf5;
            border-right: 1px solid #eef1f8;
            vertical-align: middle;
            font-size: 8.5px;
        }
        table.report-table td:last-child { border-right: none; }
        table.report-table tbody tr:nth-child(odd)  td { background: #fff; }
        table.report-table tbody tr:nth-child(even) td { background: #f7f9fd; }
        table.report-table tbody tr:last-child      td { border-bottom: 2px solid #0b2558; }

        .rank-col { width: 35px; text-align: center; font-weight: 700; color: #1e40af; }
        .student-name { font-weight: 700; color: #0b2236; font-size: 9px; }
        .roll-no { font-family: 'Courier New', monospace; font-size: 8px; color: #334155; }
        .school-name { font-size: 8px; color: #334155; }
        .score-col { font-weight: 700; color: #166534; text-align: center; font-size: 9.5px; }

        .page-footer {
            margin-top: 10px;
            padding-top: 5px;
            border-top: 1px solid #d0daf0;
            font-size: 7.5px; color: #8898b4;
            width: 100%; display: table;
        }
        .footer-left  { display: table-cell; text-align: left; }
        .footer-right { display: table-cell; text-align: right; }

        .empty-state { text-align: center; color: #8898b4; padding: 40px 20px; font-size: 10px; }
    </style>
</head>
<body>

    <div class="page-header">
        <table class="hdr-top">
            <tr>
                @if(!empty($logoSrc))
                <td class="hdr-logo-cell"><img src="{{ $logoSrc }}" alt=""></td>
                @endif
                <td class="hdr-org-cell">
                    <span class="org-name">{{ $orgName ?? 'Sahodaya' }}</span>
                    <span class="org-sub">Academic Board Results &nbsp;&middot;&nbsp; {{ $threshold }}%+ Achievers Register</span>
                </td>
            </tr>
        </table>
        <hr class="hdr-divider">
        <table class="hdr-info-bar">
            <tr>
                <td class="info-cell">
                    <span class="info-lbl">Report Title</span>
                    <span class="info-val-lg">High Achievers (≥ {{ $threshold }}%)</span>
                </td>
                <td class="info-cell" style="width:100px;">
                    <span class="info-lbl">Academic Year</span>
                    <span class="info-val">{{ $academicYear }}</span>
                </td>
                <td class="info-cell" style="width:80px;">
                    <span class="info-lbl">Class</span>
                    <span class="info-val">Class {{ $selectedClass }}</span>
                </td>
                <td class="info-cell" style="width:120px;">
                    <span class="info-lbl">Generated At</span>
                    <span class="info-val">{{ $generatedAt }}</span>
                </td>
            </tr>
        </table>
    </div>
    <div class="header-stripe"></div>

    <div class="content-wrap">
        @if($selectedClass == 10)
            <div class="section-banner">CLASS X (AISSE) — {{ $threshold }}%+ ACHIEVERS</div>
            @if(empty($overall) || count($overall) === 0)
                <div class="empty-state">No Class X achievers (≥ {{ $threshold }}%) found for {{ $academicYear }}.</div>
            @else
                @php
                    $sortedOverall = collect($overall)->sortByDesc(fn($r) => (float)($r['percentage'] ?? ($r['score'] ?? 0)))->values();
                @endphp
                <table class="report-table">
                    <thead>
                        <tr>
                            @unless($noRank ?? false)
                                <th class="rank-col">Rank</th>
                            @endunless
                            <th>Student Name</th>
                            <th>Roll No.</th>
                            <th>Member School</th>
                            <th style="width: 75px; text-align: center;">Marks / %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sortedOverall as $i => $row)
                            <tr>
                                @unless($noRank ?? false)
                                    <td class="rank-col">#{{ $row['rank'] ?? ($i + 1) }}</td>
                                @endunless
                                <td><span class="student-name">{{ $row['name'] ?? ($row['student_name'] ?? '—') }}</span></td>
                                <td><span class="roll-no">{{ $row['roll_no'] ?? '—' }}</span></td>
                                <td><span class="school-name">{{ strtoupper($row['school_name'] ?? '') }}</span></td>
                                <td class="score-col">
                                    {{ isset($row['percentage']) ? number_format((float)$row['percentage'], 2).'%' : (isset($row['score']) ? number_format((float)$row['score'], 2) : '—') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @else
            @if(empty($byStream) || count($byStream) === 0)
                <div class="section-banner">CLASS XII (AISSCE) — {{ $threshold }}%+ ACHIEVERS</div>
                <div class="empty-state">No Class XII achievers (≥ {{ $threshold }}%) found for {{ $academicYear }}.</div>
            @else
                @foreach($byStream as $streamName => $toppers)
                    @php
                        $sortedStream = collect($toppers)->sortByDesc(fn($r) => (float)($r['percentage'] ?? ($r['score'] ?? 0)))->values();
                    @endphp
                    <div class="section-banner">CLASS XII — {{ strtoupper($streamName) }} STREAM (≥ {{ $threshold }}%)</div>
                    <table class="report-table">
                        <thead>
                            <tr>
                                @unless($noRank ?? false)
                                    <th class="rank-col">Rank</th>
                                @endunless
                                <th>Student Name</th>
                                <th>Roll No.</th>
                                <th>Member School</th>
                                <th style="width: 75px; text-align: center;">Marks / %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sortedStream as $i => $row)
                                <tr>
                                    @unless($noRank ?? false)
                                        <td class="rank-col">#{{ $row['rank'] ?? ($i + 1) }}</td>
                                    @endunless
                                    <td><span class="student-name">{{ $row['name'] ?? ($row['student_name'] ?? '—') }}</span></td>
                                    <td><span class="roll-no">{{ $row['roll_no'] ?? '—' }}</span></td>
                                    <td><span class="school-name">{{ strtoupper($row['school_name'] ?? '') }}</span></td>
                                    <td class="score-col">
                                        {{ isset($row['percentage']) ? number_format((float)$row['percentage'], 2).'%' : (isset($row['score']) ? number_format((float)$row['score'], 2) : '—') }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endforeach
            @endif
        @endif

        <div class="page-footer">
            <div class="footer-left">{{ $threshold }}%+ Achievers &mdash; Class {{ $selectedClass }} &mdash; {{ $orgName }}</div>
            <div class="footer-right">Generated: {{ $generatedAt }}</div>
        </div>
    </div>

</body>
</html>
