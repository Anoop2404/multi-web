<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Full A1 Achievers — {{ $academicYear }}</title>
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
            margin-bottom: 12px;
        }

        table.report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        table.report-table th {
            background: #0f3d7a;
            color: #ffffff;
            text-align: left;
            padding: 6px;
            font-size: 8px;
            text-transform: uppercase;
            border: 1px solid #0f3d7a;
            letter-spacing: 0.3px;
        }
        table.report-table td {
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        table.report-table tr:nth-child(even) td {
            background: #f8fafc;
        }

        .sl-col {
            width: 28px;
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
        .badge-a1 {
            background: #dcfce7;
            color: #166534;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 8px;
            display: inline-block;
        }
        .marks-col {
            font-weight: bold;
            color: #166534;
            text-align: center;
            font-size: 9.5px;
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
                        @if(!empty($selectedClass))
                            CBSE Class {{ $selectedClass }} ({{ $selectedClass == 10 ? 'AISSE' : 'AISSCE' }}) Board Examination {{ $academicYear }}
                        @else
                            CBSE Board Examinations {{ $academicYear }} (Class X AISSE & Class XII AISSCE)
                        @endif
                    </div>
                    <div class="tag">Academic Board Results · Full A1 Achievers Official Register</div>
                </td>
                <td style="text-align: right; vertical-align: middle; width: 210px;">
                    <div class="meta-box">
                        <div><strong>Academic Year:</strong> {{ $academicYear }}</div>
                        <div><strong>Examination:</strong> {{ !empty($selectedClass) ? 'Class '.$selectedClass.' ('.($selectedClass == 10 ? 'AISSE' : 'AISSCE').')' : 'Class X & Class XII' }}</div>
                        @if(!empty($selectedStream))
                            <div><strong>Stream:</strong> {{ $selectedStream }}</div>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="report-title">Full A1 Achievers Official Register</div>
    <div class="meta-line">
        Students scoring A1 grade (91-100) in all entered subjects · Total Achievers: <strong>{{ count($rows) }}</strong> · Generated: {{ $generatedAt }}
    </div>

    @if(empty($rows))
        <p style="text-align: center; color: #64748b; margin-top: 30px;">No Full A1 Achievers found for the selected criteria.</p>
    @else
        <table class="report-table">
            <thead>
                <tr>
                    <th class="sl-col">#</th>
                    <th>Student Name</th>
                    <th>Roll No.</th>
                    <th>Member School</th>
                    <th style="width: 60px;">Class</th>
                    <th style="width: 75px;">Stream</th>
                    <th style="width: 70px; text-align: center;">Total Marks</th>
                    <th style="width: 60px; text-align: center;">Grade</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $i => $row)
                    <tr>
                        <td class="sl-col">{{ $i + 1 }}</td>
                        <td class="student-name">{{ $row['student_name'] }}</td>
                        <td class="roll-no">{{ $row['roll_no'] ?: '—' }}</td>
                        <td class="school-name">{{ strtoupper($row['school_name'] ?? '') }}</td>
                        <td style="font-weight: bold; color: #4338ca;">{{ $row['class'] ? 'Class '.$row['class'] : '—' }}</td>
                        <td>{{ $row['stream'] ?: '—' }}</td>
                        <td class="marks-col">{{ $row['total_marks'] ?? '—' }}</td>
                        <td style="text-align: center;"><span class="badge-a1">FULL A1</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

</body>
</html>
