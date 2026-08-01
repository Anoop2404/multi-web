<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subject-Wise Merit Register — {{ $academicYear }}</title>
    <style>
        @page {
            margin: 15mm 12mm 15mm 12mm;
        }
        * {
            box-sizing: border-box;
        }
        html, body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f2744;
            font-size: 9px;
            margin: 0;
            padding: 0;
        }
        
        .subject-page {
            page-break-after: always;
        }
        .subject-page:last-child {
            page-break-after: avoid;
        }

        .header {
            border-bottom: 2.5px solid #0f3d7a;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .header table {
            width: 100%;
            border-collapse: collapse;
        }
        .logo {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }
        .org {
            font-size: 15px;
            font-weight: 700;
            color: #0f3d7a;
            letter-spacing: 0.3px;
        }
        .exam-title {
            font-size: 10.5px;
            font-weight: 700;
            color: #1e293b;
            margin-top: 3px;
        }
        .tag {
            font-size: 8px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
        .meta-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 5px 8px;
            font-size: 8.5px;
            line-height: 1.4;
        }
        .meta-box strong {
            color: #0f3d7a;
        }

        .subject-header-banner {
            background: #0f3d7a;
            color: #ffffff;
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .subject-header-banner table {
            width: 100%;
        }
        .subj-title {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            color: #ffffff;
        }
        .subj-meta {
            font-size: 9px;
            color: #e2e8f0;
            text-align: right;
        }

        .summary-bar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px 10px;
            margin-bottom: 10px;
            font-size: 8.5px;
            color: #475569;
        }

        table.report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        table.report-table th {
            background: #1e293b;
            color: #ffffff;
            text-align: left;
            padding: 6px;
            font-size: 8px;
            text-transform: uppercase;
            border: 1px solid #1e293b;
        }
        table.report-table td {
            padding: 6px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        table.report-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        .rank-col {
            width: 35px;
            text-align: center;
            font-weight: bold;
        }
        .student-name {
            font-weight: bold;
            color: #0f172a;
            font-size: 9.5px;
        }
        .roll-no {
            font-family: monospace;
            font-size: 8.5px;
            color: #334155;
        }
        .school-name {
            font-size: 8.5px;
            color: #334155;
        }
        .marks-col {
            font-weight: bold;
            color: #166534;
            text-align: center;
            font-size: 10.5px;
        }
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
        <div class="header">
            <table>
                <tr>
                    @if(!empty($logoSrc))
                        <td style="width: 56px; vertical-align: middle;"><img class="logo" src="{{ $logoSrc }}" alt="Logo"></td>
                    @endif
                    <td style="vertical-align: middle;">
                        <div class="org">{{ $orgName }}</div>
                        <div class="exam-title">
                            @if(!empty($selectedClass))
                                CBSE Class {{ $selectedClass }} ({{ $selectedClass == 10 ? 'AISSE' : 'AISSCE' }}) Board Examination {{ $academicYear }}
                            @else
                                CBSE Board Examinations {{ $academicYear }} (Class X AISSE & Class XII AISSCE)
                            @endif
                        </div>
                        <div class="tag">Academic Results · Subject-Wise Merit Register</div>
                    </td>
                    <td style="text-align: right; vertical-align: middle; width: 210px;">
                        <div class="meta-box">
                            <div><strong>Academic Year:</strong> {{ $academicYear }}</div>
                            <div><strong>Class:</strong> {{ !empty($selectedClass) ? 'Class '.$selectedClass.' ('.($selectedClass == 10 ? 'AISSE' : 'AISSCE').')' : 'Class X & Class XII' }}</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <p style="text-align: center; color: #64748b; margin-top: 30px;">No subject toppers found for the selected criteria.</p>
    @else
        @foreach($grouped as $subjectLabel => $subjectRows)
            <div class="subject-page">
                <!-- PAGE HEADER -->
                <div class="header">
                    <table>
                        <tr>
                            @if(!empty($logoSrc))
                                <td style="width: 56px; vertical-align: middle;"><img class="logo" src="{{ $logoSrc }}" alt="Logo"></td>
                            @endif
                            <td style="vertical-align: middle;">
                                <div class="org">{{ $orgName }}</div>
                                <div class="exam-title">
                                    @if(!empty($selectedClass))
                                        CBSE Class {{ $selectedClass }} ({{ $selectedClass == 10 ? 'AISSE' : 'AISSCE' }}) Board Examination {{ $academicYear }}
                                    @else
                                        CBSE Board Examinations {{ $academicYear }} (Class X AISSE & Class XII AISSCE)
                                    @endif
                                </div>
                                <div class="tag">Academic Board Results · Subject-Wise Merit Register</div>
                            </td>
                            <td style="text-align: right; vertical-align: middle; width: 210px;">
                                <div class="meta-box">
                                    <div><strong>Academic Year:</strong> {{ $academicYear }}</div>
                                    <div><strong>Class:</strong> {{ !empty($selectedClass) ? 'Class '.$selectedClass.' ('.($selectedClass == 10 ? 'AISSE' : 'AISSCE').')' : 'Class X & Class XII' }}</div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- SUBJECT TITLE BANNER -->
                <div class="subject-header-banner">
                    <table>
                        <tr>
                            <td class="subj-title">{{ $subjectLabel }}</td>
                            <td class="subj-meta">Generated: {{ $generatedAt }}</td>
                        </tr>
                    </table>
                </div>

                <div class="summary-bar">
                    Highest Mark: <strong>{{ $subjectRows->max('marks') ?? '—' }}</strong> · Total Toppers: <strong>{{ $subjectRows->count() }}</strong>
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
                        @foreach($subjectRows as $i => $row)
                            <tr>
                                @unless($noRank ?? false)
                                    <td class="rank-col">#{{ $row['rank'] ?? ($i + 1) }}</td>
                                @endunless
                                <td class="student-name">{{ $row['name'] ?? ($row['student_name'] ?? '—') }}</td>
                                <td class="roll-no">{{ $row['roll_no'] ?? ($row['cbse_roll_no'] ?? '—') }}</td>
                                <td class="school-name">{{ strtoupper($row['school_name'] ?? '') }}</td>
                                <td class="marks-col">{{ $row['marks'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif

</body>
</html>
