<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Full A1 Achievers — {{ $academicYear }}</title>
    <style>
        @page { margin: 30px 36px; }
        * { box-sizing: border-box; }
        html, body { font-family: DejaVu Sans, sans-serif; color: #0f2744; font-size: 9px; margin: 0; padding: 0; }
        .header { border-bottom: 2px solid #0f3d7a; padding-bottom: 8px; margin-bottom: 12px; }
        .header table { width: 100%; }
        .logo { width: 44px; height: 44px; object-fit: contain; }
        .org { font-size: 14px; font-weight: 700; color: #0f3d7a; }
        .tag { font-size: 8px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
        .title { font-size: 14px; font-weight: 700; margin: 6px 0 2px; color: #0f172a; }
        .meta-line { font-size: 9px; color: #64748b; margin-bottom: 12px; }
        
        table.report-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.report-table th { background: #0f3d7a; color: #ffffff; text-align: left; padding: 6px; font-size: 8px; text-transform: uppercase; border: 1px solid #0f3d7a; }
        table.report-table td { padding: 5px 6px; border: 1px solid #cbd5e1; vertical-align: middle; }
        table.report-table tr:nth-child(even) td { background: #f8fafc; }

        .sl-col { width: 30px; text-align: center; font-weight: bold; }
        .student-name { font-weight: bold; color: #0f172a; font-size: 9.5px; }
        .roll-no { font-family: monospace; font-size: 8.5px; color: #334155; }
        .school-name { font-size: 8.5px; color: #334155; }
        .badge-a1 { background: #dcfce7; color: #166534; font-weight: bold; padding: 2px 5px; border-radius: 3px; font-size: 8px; display: inline-block; }
        .marks-col { font-weight: bold; color: #166534; text-align: center; font-size: 9.5px; }
    </style>
</head>
<body>

    <div class="header">
        <table>
            <tr>
                @if(!empty($logoSrc))
                    <td style="width:50px; vertical-align:middle;"><img class="logo" src="{{ $logoSrc }}" alt=""></td>
                @endif
                <td style="vertical-align:middle;">
                    <div class="org">{{ $orgName }}</div>
                    <div class="tag">Academic Board Results · Full A1 Achievers Official Register</div>
                </td>
                <td style="text-align:right; vertical-align:middle;">
                    <div style="font-size:11px; font-weight:700; color:#1e293b;">Academic Year: {{ $academicYear }}</div>
                    <div style="font-size:8px; color:#64748b;">Class: {{ $selectedClass ? 'Class '.$selectedClass : 'Class X & XII' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="title">Full A1 Achievers Register</div>
    <div class="meta-line">
        Students scoring A1 grade (91-100) in all entered subjects · Total Achievers: <strong>{{ count($rows) }}</strong> · Generated: {{ $generatedAt }}
    </div>

    @if(empty($rows))
        <p style="text-align:center; color:#64748b; margin-top:30px;">No Full A1 Achievers found for the selected criteria.</p>
    @else
        <table class="report-table">
            <thead>
                <tr>
                    <th class="sl-col">#</th>
                    <th>Student Name</th>
                    <th>Roll No.</th>
                    <th>Member School</th>
                    <th>Class</th>
                    <th>Stream</th>
                    <th style="width:70px; text-align:center;">Total Marks</th>
                    <th style="width:60px; text-align:center;">Grade</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $i => $row)
                    <tr>
                        <td class="sl-col">{{ $i + 1 }}</td>
                        <td class="student-name">{{ $row['student_name'] }}</td>
                        <td class="roll-no">{{ $row['roll_no'] ?: '—' }}</td>
                        <td class="school-name">{{ strtoupper($row['school_name'] ?? '') }}</td>
                        <td style="font-weight:bold; color:#4338ca;">{{ $row['class'] ? 'Class '.$row['class'] : '—' }}</td>
                        <td>{{ $row['stream'] ?: '—' }}</td>
                        <td class="marks-col">{{ $row['total_marks'] ?? '—' }}</td>
                        <td style="text-align:center;"><span class="badge-a1">FULL A1</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

</body>
</html>
