<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Register — {{ $exam->title }}</title>
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

        .class-page {
            page-break-after: always;
        }
        .class-page:last-child {
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
        .sub-tag {
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
            text-align: right;
        }
        .meta-box strong {
            color: #0f3d7a;
        }

        .exam-title {
            font-size: 13px;
            font-weight: 700;
            margin: 4px 0 2px;
            color: #1e1b4b;
        }
        .meta-line {
            font-size: 8.5px;
            color: #64748b;
            margin-bottom: 12px;
        }
        
        .class-header {
            background: #0f3d7a;
            color: #ffffff;
            padding: 6px 10px;
            font-size: 11px;
            font-weight: 700;
            border-radius: 4px;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
        }
        .class-count {
            font-weight: 400;
            font-size: 9px;
            opacity: 0.9;
        }

        table.report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
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
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }
        table.report-table tr:nth-child(even) td {
            background: #f8fafc;
        }
        
        .sl-col {
            width: 24px;
            text-align: center;
            font-weight: bold;
        }
        .photo-col {
            width: 34px;
            text-align: center;
        }
        .photo-img {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid #cbd5e1;
        }
        .student-name {
            font-weight: bold;
            color: #0f172a;
            font-size: 9.5px;
        }
        .admission-no {
            font-family: monospace;
            font-size: 8.5px;
            color: #334155;
        }
        .ticket-no {
            font-family: monospace;
            font-size: 8.5px;
            font-weight: bold;
            color: #4338ca;
        }
        .badge-status {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8px;
            font-weight: bold;
            border-radius: 3px;
            text-transform: uppercase;
        }
        .badge-approved {
            background: #dcfce7;
            color: #166534;
        }
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .badge-rejected {
            background: #ffe4e6;
            color: #9f1239;
        }
    </style>
</head>
<body>

    @if(empty($groupedRows))
        <div class="header">
            <table>
                <tr>
                    @if(!empty($logoSrc))
                        <td style="width: 56px; vertical-align: middle;"><img class="logo" src="{{ $logoSrc }}" alt="Logo"></td>
                    @endif
                    <td style="vertical-align: middle;">
                        <div class="org">{{ $orgName }}</div>
                        <div class="sub-tag">Talent Search Exams · Registration Register Official Report</div>
                    </td>
                    @if($school)
                        <td style="text-align: right; vertical-align: middle;">
                            <div class="meta-box">
                                <div><strong>School:</strong> {{ $school->name }}</div>
                                <div><strong>Generated:</strong> {{ $generatedAt }}</div>
                            </div>
                        </td>
                    @endif
                </tr>
            </table>
        </div>
        <p style="text-align: center; color: #64748b; margin-top: 30px;">No registered candidates found for this exam.</p>
    @else
        @foreach($groupedRows as $className => $students)
            <div class="class-page">
                <!-- HEADER ON EACH CLASS PAGE -->
                <div class="header">
                    <table>
                        <tr>
                            @if(!empty($logoSrc))
                                <td style="width: 56px; vertical-align: middle;"><img class="logo" src="{{ $logoSrc }}" alt="Logo"></td>
                            @endif
                            <td style="vertical-align: middle;">
                                <div class="org">{{ $orgName }}</div>
                                <div class="sub-tag">Talent Search Exams · Registration Register Official Report</div>
                            </td>
                            <td style="text-align: right; vertical-align: middle;">
                                <div class="meta-box">
                                    @if($school)<div><strong>School:</strong> {{ $school->name }}</div>@endif
                                    <div><strong>Class:</strong> {{ $className }}</div>
                                    <div><strong>Generated:</strong> {{ $generatedAt }}</div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="exam-title">{{ $exam->title }} @if($exam->code)<span style="font-weight:400; font-size:10px; color:#475569;">({{ $exam->code }})</span>@endif</div>
                <div class="meta-line">
                    Total Candidates in Class: <strong>{{ count($students) }}</strong>
                    @if($exam->scheduled_at) · Scheduled: {{ $exam->scheduled_at->format('d M Y') }} @endif
                </div>

                <div class="class-header">
                    <span>{{ $className }}</span>
                    <span class="class-count">({{ count($students) }} {{ count($students) === 1 ? 'student' : 'students' }})</span>
                </div>

                <table class="report-table">
                    <thead>
                        <tr>
                            <th class="sl-col">#</th>
                            <th class="photo-col">Photo</th>
                            <th>Student Name</th>
                            <th>Admission No.</th>
                            <th>Hall Ticket No.</th>
                            @if(!$school) <th>School</th> @endif
                            <th>Approval Status</th>
                            <th>Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($students as $index => $row)
                            <tr>
                                <td class="sl-col">{{ $index + 1 }}</td>
                                <td class="photo-col">
                                    @if(!empty($row['photo_src']))
                                        <img src="{{ $row['photo_src'] }}" class="photo-img" alt="">
                                    @else
                                        <div style="width:28px; height:28px; line-height:28px; border-radius:50%; background:#e2e8f0; color:#475569; font-weight:bold; font-size:9px; margin:0 auto; text-align:center;">
                                            {{ strtoupper(substr($row['student_name'] ?? '?', 0, 1)) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="student-name">{{ $row['student_name'] }}</td>
                                <td class="admission-no">{{ $row['admission_number'] }}</td>
                                <td class="ticket-no">{{ $row['hall_ticket_no'] }}</td>
                                @if(!$school) <td>{{ $row['school_name'] }}</td> @endif
                                <td>
                                    <span class="badge-status {{ strtolower($row['approval_status']) === 'approved' ? 'badge-approved' : (strtolower($row['approval_status']) === 'rejected' ? 'badge-rejected' : 'badge-pending') }}">
                                        {{ $row['approval_status'] }}
                                    </span>
                                </td>
                                <td style="font-weight:bold; text-transform:capitalize;">{{ $row['attendance_status'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif

</body>
</html>
