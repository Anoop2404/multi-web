<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School-Wise Toppers — {{ $academicYear }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1a2236;
            font-size: 10.5px;
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
            font-size: 8.5px;
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
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.9px;
            color: rgba(255,255,255,0.4);
            display: block;
            margin-bottom: 2px;
        }
        .info-val {
            font-size: 10.5px;
            font-weight: 700;
            color: #ffffff;
            display: block;
        }
        .info-val-lg {
            font-size: 11px;
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

        table.report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        table.report-table thead tr { background: #0b2558; }
        table.report-table th {
            color: #c8d9f5;
            text-align: left;
            padding: 8px 10px;
            font-size: 9.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            border-right: 1px solid rgba(255,255,255,0.08);
        }
        table.report-table th:last-child { border-right: none; }
        table.report-table td {
            padding: 9px 10px;
            border-bottom: 1px solid #e8edf5;
            border-right: 1px solid #eef1f8;
            vertical-align: middle;
            font-size: 10.5px;
            line-height: 1.35;
        }
        table.report-table td:last-child { border-right: none; }
        table.report-table tbody tr:nth-child(odd)  td { background: #fff; }
        table.report-table tbody tr:nth-child(even) td { background: #f7f9fd; }
        table.report-table tbody tr:last-child      td { border-bottom: 2px solid #0b2558; }

        .school-name-col { font-weight: 700; color: #0b2236; font-size: 10.5px; }
        .student-name { font-weight: 700; color: #0b2236; font-size: 10.5px; }
        .roll-no { font-family: 'Courier New', monospace; font-size: 9.5px; color: #334155; }
        .score-col { font-weight: 700; color: #166534; text-align: center; font-size: 11px; }
        .missing-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            background: #fef2f2;
            color: #b91c1c;
            font-weight: 700;
            font-size: 9px;
            text-transform: uppercase;
        }
        .submitted-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            background: #ecfdf5;
            color: #166534;
            font-weight: 700;
            font-size: 9px;
            text-transform: uppercase;
        }

        .page-footer {
            margin-top: 10px;
            padding-top: 5px;
            border-top: 1px solid #d0daf0;
            font-size: 9px; color: #8898b4;
            width: 100%; display: table;
        }
        .footer-left  { display: table-cell; text-align: left; }
        .footer-right { display: table-cell; text-align: right; }

        .empty-state { text-align: center; color: #8898b4; padding: 40px 20px; font-size: 11.5px; }
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
                    <span class="org-sub">Academic Board Results &nbsp;&middot;&nbsp; School-Wise Toppers Register</span>
                </td>
            </tr>
        </table>
        <hr class="hdr-divider">
        <table class="hdr-info-bar">
            <tr>
                <td class="info-cell">
                    <span class="info-lbl">Report Title</span>
                    <span class="info-val-lg">School-Wise Toppers</span>
                </td>
                <td class="info-cell" style="width:100px;">
                    <span class="info-lbl">Academic Year</span>
                    <span class="info-val">{{ $academicYear }}</span>
                </td>
                <td class="info-cell" style="width:90px;">
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
        @if(empty($rows) || count($rows) === 0)
            <div class="empty-state">No member schools found for {{ $academicYear }}.</div>
        @else
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 30px; text-align: center;">#</th>
                        <th>School Name</th>
                        <th>Topper Name</th>
                        <th>Roll No.</th>
                        <th>Stream</th>
                        <th style="width: 65px; text-align: center;">Marks</th>
                        <th style="width: 65px; text-align: center;">%</th>
                        <th style="width: 75px; text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $i => $row)
                        <tr>
                            <td style="text-align: center; font-weight: 700; color: #6b7a9e;">{{ $i + 1 }}</td>
                            <td><span class="school-name-col">{{ strtoupper($row['school_name'] ?? '') }}</span></td>
                            <td><span class="student-name">{{ $row['student_name'] ?? '—' }}</span></td>
                            <td><span class="roll-no">{{ $row['roll_no'] ?? '—' }}</span></td>
                            <td>{{ $row['stream'] ?? '—' }}</td>
                            <td style="text-align: center; font-weight: 700;">
                                {{ $row['marks_obtained'] !== null ? number_format((float)$row['marks_obtained'], 0).'/'.number_format((float)$row['total_marks'], 0) : '—' }}
                            </td>
                            <td class="score-col">
                                {{ $row['percentage'] !== null ? number_format((float)$row['percentage'], 2).'%' : '—' }}
                            </td>
                            <td style="text-align: center;">
                                @if($row['has_topper'])
                                    <span class="submitted-badge">Submitted</span>
                                @else
                                    <span class="missing-badge">Not Submitted</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="page-footer">
                <div class="footer-left">School-Wise Toppers Register &mdash; {{ $orgName }}</div>
                <div class="footer-right">Generated: {{ $generatedAt }}</div>
            </div>
        @endif
    </div>

</body>
</html>
