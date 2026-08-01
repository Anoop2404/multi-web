<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $threshold }}%+ Achievers — {{ $academicYear }}</title>
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
        .report-title {
            font-size: 14px;
            font-weight: 700;
            margin: 10px 0 3px;
            color: #0f172a;
        }
        .meta-line {
            font-size: 8.5px;
            color: #64748b;
            margin-bottom: 14px;
        }

        .section-banner {
            background: #0f3d7a;
            color: #ffffff;
            padding: 6px 10px;
            font-size: 10px;
            font-weight: 700;
            border-radius: 3px;
            margin-top: 14px;
            margin-bottom: 6px;
            page-break-inside: avoid;
        }

        table.report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.report-table th {
            background: #f1f5f9;
            color: #334155;
            text-align: left;
            padding: 6px;
            font-size: 8px;
            text-transform: uppercase;
            border: 1px solid #cbd5e1;
        }
        table.report-table td {
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        table.report-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        .rank-col {
            width: 32px;
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
            font-size: 10px;
        }
    </style>
</head>
<body>

    <div class="header">
        <table>
            <tr>
                @if(!empty($logoSrc))
                    <td style="width: 56px; vertical-align: middle;">
                        <img class="logo" src="{{ $logoSrc }}" alt="Logo">
                    </td>
                @endif
                <td style="vertical-align: middle;">
                    <div class="org">{{ $orgName }}</div>
                    <div class="exam-title">
                        CBSE Class {{ $selectedClass }} ({{ $selectedClass == 10 ? 'AISSE' : 'AISSCE' }}) Board Examination {{ $academicYear }}
                    </div>
                    <div class="tag">Academic Board Results · {{ rtrim(rtrim(number_format($threshold, 2), '0'), '.') }}%+ Achievers</div>
                </td>
                <td style="text-align: right; vertical-align: middle; width: 210px;">
                    <div class="meta-box">
                        <div><strong>Academic Year:</strong> {{ $academicYear }}</div>
                        <div><strong>Class:</strong> Class {{ $selectedClass }}</div>
                        <div><strong>Threshold:</strong> {{ rtrim(rtrim(number_format($threshold, 2), '0'), '.') }}%+</div>
                        <div><strong>Generated:</strong> {{ $generatedAt }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="report-title">Sahodaya {{ rtrim(rtrim(number_format($threshold, 2), '0'), '.') }}%+ Achievers Register</div>
    <div class="meta-line">
        Every student scoring at/above {{ rtrim(rtrim(number_format($threshold, 2), '0'), '.') }}% across all member schools — not capped to Top-N.
    </div>

    @if($selectedClass == 10)
        @if(empty($overall))
            <p style="color: #64748b; margin: 10px 0;">No achievers found for the selected criteria.</p>
        @else
            <table class="report-table">
                <thead>
                    <tr>
                        @unless($noRank ?? false)
                            <th class="rank-col">Rank</th>
                        @endunless
                        <th>Student Name</th>
                        <th>Roll No.</th>
                        <th>Member School</th>
                        <th style="width: 70px; text-align: center;">Total Marks</th>
                        <th style="width: 60px; text-align: center;">%</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($overall as $i => $row)
                        <tr>
                            @unless($noRank ?? false)
                                <td class="rank-col">#{{ $row['rank'] ?? ($i + 1) }}</td>
                            @endunless
                            <td class="student-name">{{ $row['student_name'] ?? '—' }}</td>
                            <td class="roll-no">{{ $row['roll_no'] ?? '—' }}</td>
                            <td class="school-name">{{ strtoupper($row['school_name'] ?? '') }}</td>
                            <td class="marks-col">{{ $row['total_marks'] ?? ($row['marks_obtained'] ?? '—') }}</td>
                            <td style="text-align: center; font-weight: bold; color: #4338ca;">{{ $row['percentage'] ? number_format($row['percentage'], 1).'%' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @else
        @if(empty($byStream))
            <p style="color: #64748b; margin: 10px 0;">No achievers found for the selected criteria.</p>
        @else
            @foreach($byStream as $streamLabel => $streamRows)
                <div class="section-banner">Stream: {{ $streamLabel }}</div>
                <table class="report-table">
                    <thead>
                        <tr>
                            @unless($noRank ?? false)
                                <th class="rank-col">Rank</th>
                            @endunless
                            <th>Student Name</th>
                            <th>Roll No.</th>
                            <th>Member School</th>
                            <th style="width: 70px; text-align: center;">Total Marks</th>
                            <th style="width: 60px; text-align: center;">%</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($streamRows as $i => $row)
                            <tr>
                                @unless($noRank ?? false)
                                    <td class="rank-col">#{{ $row['rank'] ?? ($i + 1) }}</td>
                                @endunless
                                <td class="student-name">{{ $row['student_name'] ?? '—' }}</td>
                                <td class="roll-no">{{ $row['roll_no'] ?? '—' }}</td>
                                <td class="school-name">{{ strtoupper($row['school_name'] ?? '') }}</td>
                                <td class="marks-col">{{ $row['total_marks'] ?? ($row['marks_obtained'] ?? '—') }}</td>
                                <td style="text-align: center; font-weight: bold; color: #4338ca;">{{ $row['percentage'] ? number_format($row['percentage'], 1).'%' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        @endif
    @endif

</body>
</html>
