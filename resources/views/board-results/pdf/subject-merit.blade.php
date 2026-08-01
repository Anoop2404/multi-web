<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subject-Wise Merit Register — {{ $academicYear }}</title>
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

        .subject-page { page-break-after: always; }
        .subject-page:last-child { page-break-after: avoid; }

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
            padding: 10px 14px 14px 14px;
        }

        .subject-sub {
            background: #0f2d5e;
            border: 1px solid #1e3a6e;
            border-radius: 4px;
            padding: 6px 12px;
            margin-bottom: 8px;
        }
        .subj-name  { font-size: 11px; font-weight: 700; color: #fff; text-transform: uppercase; }
        .subj-meta  { font-size: 8px; color: #a8c0e8; float: right; }

        .summary-bar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 5px 10px;
            margin-bottom: 8px;
            font-size: 8.5px;
            color: #475569;
        }

        table.report-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.report-table thead tr { background: #0b2558; }
        table.report-table th {
            color: #c8d9f5;
            text-align: left;
            padding: 8px 10px;
            font-size: 8px;
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
            font-size: 9px;
            line-height: 1.35;
        }
        table.report-table td:last-child { border-right: none; }
        table.report-table tbody tr:nth-child(odd)  td { background: #fff; }
        table.report-table tbody tr:nth-child(even) td { background: #f7f9fd; }
        table.report-table tbody tr:last-child      td { border-bottom: 2px solid #0b2558; }

        .rank-col { width: 35px; text-align: center; font-weight: 700; color: #1e40af; }
        .student-name { font-weight: 700; color: #0b2236; font-size: 9px; }
        .roll-no { font-family: 'Courier New', monospace; font-size: 8px; color: #334155; }
        .school-name { font-size: 8px; color: #334155; }
        .marks-col { font-weight: 700; color: #166534; text-align: center; font-size: 10px; }

        .page-footer {
            margin-top: 8px;
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

    @php
        $grouped = collect($rows)->groupBy(function($r) {
            $subj = $r['subject'] ?? 'Subject';
            $cls = !empty($r['class']) ? ' (Class '.$r['class'].')' : '';
            return $subj . $cls;
        });
    @endphp

    @if($grouped->isEmpty())
        <div class="page-header">
            <table class="hdr-top">
                <tr>
                    @if(!empty($logoSrc))
                    <td class="hdr-logo-cell"><img src="{{ $logoSrc }}" alt=""></td>
                    @endif
                    <td class="hdr-org-cell">
                        <span class="org-name">{{ $orgName ?? 'Sahodaya' }}</span>
                        <span class="org-sub">Talent Search & Board Results &nbsp;&middot;&nbsp; Subject-Wise Merit Register</span>
                    </td>
                </tr>
            </table>
            <hr class="hdr-divider">
            <table class="hdr-info-bar">
                <tr>
                    <td class="info-cell">
                        <span class="info-lbl">Academic Year</span>
                        <span class="info-val">{{ $academicYear }}</span>
                    </td>
                    <td class="info-cell">
                        <span class="info-lbl">Class</span>
                        <span class="info-val">{{ !empty($selectedClass) ? 'Class '.$selectedClass : 'All Classes' }}</span>
                    </td>
                </tr>
            </table>
        </div>
        <div class="header-stripe"></div>
        <div class="content-wrap">
            <div class="empty-state">No subject toppers found for the selected criteria.</div>
        </div>
    @else
        @foreach($grouped as $subjectLabel => $subjectRows)
            @php
                // Ensure rows are strictly sorted descending by marks float
                $sortedSubjectRows = collect($subjectRows)->sortByDesc(fn($r) => (float)($r['marks'] ?? 0))->values();
            @endphp
            <div class="subject-page">
                <div class="page-header">
                    <table class="hdr-top">
                        <tr>
                            @if(!empty($logoSrc))
                            <td class="hdr-logo-cell"><img src="{{ $logoSrc }}" alt=""></td>
                            @endif
                            <td class="hdr-org-cell">
                                <span class="org-name">{{ $orgName ?? 'Sahodaya' }}</span>
                                <span class="org-sub">Academic Board Results &nbsp;&middot;&nbsp; Subject-Wise Merit Register</span>
                            </td>
                        </tr>
                    </table>
                    <hr class="hdr-divider">
                    <table class="hdr-info-bar">
                        <tr>
                            <td class="info-cell">
                                <span class="info-lbl">Subject</span>
                                <span class="info-val-lg">{{ $subjectLabel }}</span>
                            </td>
                            <td class="info-cell" style="width:100px;">
                                <span class="info-lbl">Academic Year</span>
                                <span class="info-val">{{ $academicYear }}</span>
                            </td>
                            <td class="info-cell" style="width:90px;">
                                <span class="info-lbl">Class</span>
                                <span class="info-val">{{ !empty($selectedClass) ? 'Class '.$selectedClass : 'Class X & XII' }}</span>
                            </td>
                            <td class="info-cell" style="width:120px;">
                                <span class="info-lbl">Generated</span>
                                <span class="info-val">{{ $generatedAt }}</span>
                            </td>
                        </tr>
                    </table>
                </div>
                <div class="header-stripe"></div>

                <div class="content-wrap">
                    <div class="subject-sub">
                        <span class="subj-name">{{ $subjectLabel }}</span>
                        <span class="subj-meta">Generated: {{ $generatedAt }}</span>
                    </div>

                    <div class="summary-bar">
                        Highest Mark: <strong>{{ $sortedSubjectRows->max(fn($r) => (float)($r['marks'] ?? 0)) }}</strong> &nbsp;&middot;&nbsp; Total Toppers: <strong>{{ $sortedSubjectRows->count() }}</strong>
                    </div>

                    <table class="report-table">
                        <thead>
                            <tr>
                                @unless($noRank ?? false)
                                    <th class="rank-col">Rank</th>
                                @endunless
                                <th>Student Name</th>
                                <th>Roll No.</th>
                                <th>Member School</th>
                                <th style="width: 70px; text-align: center;">Marks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sortedSubjectRows as $i => $row)
                                <tr>
                                    @unless($noRank ?? false)
                                        <td class="rank-col">#{{ $row['rank'] ?? ($i + 1) }}</td>
                                    @endunless
                                    <td><span class="student-name">{{ $row['name'] ?? ($row['student_name'] ?? '—') }}</span></td>
                                    <td><span class="roll-no">{{ $row['roll_no'] ?? ($row['cbse_roll_no'] ?? '—') }}</span></td>
                                    <td><span class="school-name">{{ strtoupper($row['school_name'] ?? '') }}</span></td>
                                    <td class="marks-col">{{ $row['marks'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="page-footer">
                        <div class="footer-left">{{ $subjectLabel }} &mdash; Subject-Wise Merit Register &mdash; {{ $orgName }}</div>
                        <div class="footer-right">{{ $generatedAt }}</div>
                    </div>
                </div>
            </div>
        @endforeach
    @endif

</body>
</html>
