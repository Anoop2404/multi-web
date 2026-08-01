<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Register — Class-wise Report — {{ $exam->title }}</title>
    <style>
        @page { margin: 30px 36px; }
        * { box-sizing: border-box; }
        html, body { font-family: DejaVu Sans, sans-serif; color: #0f2744; font-size: 9px; margin: 0; padding: 0; }
        .header { border-bottom: 2px solid #0f2744; padding-bottom: 8px; margin-bottom: 12px; }
        .header table { width: 100%; }
        .logo { width: 44px; height: 44px; object-fit: contain; }
        .org { font-size: 13px; font-weight: 700; color: #0f2744; }
        .sub-tag { font-size: 8px; color: #475569; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
        .exam-title { font-size: 14px; font-weight: 700; margin: 6px 0 2px; color: #1e1b4b; }
        .meta-line { font-size: 9px; color: #64748b; margin-bottom: 14px; }
        
        .class-section { margin-bottom: 16px; page-break-inside: avoid; }
        .class-header { background: #312e81; color: #ffffff; padding: 5px 8px; font-size: 10px; font-weight: 700; border-radius: 3px; margin-bottom: 6px; }
        .class-count { font-weight: 400; font-size: 9px; opacity: 0.9; }

        table.report-table { width: 100%; border-collapse: collapse; margin-bottom: 4px; }
        table.report-table th { background: #f1f5f9; color: #334155; text-align: left; padding: 6px; font-size: 8px; text-transform: uppercase; border: 1px solid #cbd5e1; }
        table.report-table td { padding: 5px 6px; border: 1px solid #cbd5e1; vertical-align: middle; }
        table.report-table tr:nth-child(even) td { background: #f8fafc; }
        
        .sl-col { width: 24px; text-align: center; font-weight: bold; }
        .photo-col { width: 34px; text-align: center; }
        .photo-img { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid #cbd5e1; }
        .student-name { font-weight: bold; color: #0f172a; font-size: 9.5px; }
        .admission-no { font-family: monospace; font-size: 8.5px; color: #334155; }
        .ticket-no { font-family: monospace; font-size: 8.5px; font-weight: bold; color: #4338ca; }
        .status-pill { display: inline-block; padding: 1px 4px; font-size: 7.5px; font-weight: bold; border-radius: 2px; text-transform: capitalize; }
        .status-approved { background: #dcfce7; color: #166534; }
        .status-pending { background: #fef3c7; color: #92400e; }
        
        .footer { position: fixed; bottom: 0; left: 0; right: 0; font-size: 8px; color: #94a3b8; text-align: center; border-t: 1px solid #e2e8f0; padding-top: 4px; }
        .page-number:before { content: counter(page); }
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
                    <div class="sub-tag">Talent Search Exams · Registration Register (Class-wise PDF Report)</div>
                </td>
                @if($school)
                    <td style="text-align:right; vertical-align:middle;">
                        <div style="font-size:11px; font-weight:700; color:#1e293b;">{{ $school->name }}</div>
                        <div style="font-size:8px; color:#64748b;">School Admin Copy</div>
                    </td>
                @endif
            </tr>
        </table>
    </div>

    <div class="exam-title">{{ $exam->title }} @if($exam->code)<span style="font-weight:400; font-size:10px; color:#475569;">({{ $exam->code }})</span>@endif</div>
    <div class="meta-line">
        Total Registrations: <strong>{{ $totalCount }}</strong> candidate(s)
        @if($exam->scheduled_at) · Scheduled: {{ $exam->scheduled_at->format('d M Y') }} @endif
        · Generated: {{ $generatedAt }}
    </div>

    @if(empty($groupedRows))
        <p style="text-align:center; color:#64748b; margin-top:30px;">No registered candidates found for this exam.</p>
    @else
        @foreach($groupedRows as $className => $students)
            <div class="class-section">
                <div class="class-header">
                    {{ $className }}
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
                                <td>
                                    <div class="student-name">{{ $row['student_name'] }}</div>
                                    @if(!empty($row['reg_no']) && $row['reg_no'] !== $row['admission_number'])
                                        <div style="font-size:7.5px; color:#64748b;">Reg No: {{ $row['reg_no'] }}</div>
                                    @endif
                                </td>
                                <td><span class="admission-no">{{ $row['admission_number'] }}</span></td>
                                <td><span class="ticket-no">{{ $row['hall_ticket_no'] }}</span></td>
                                @if(!$school) <td>{{ $row['school_name'] }}</td> @endif
                                <td>
                                    <span class="status-pill {{ strtolower($row['approval_status']) === 'approved' ? 'status-approved' : 'status-pending' }}">
                                        {{ $row['approval_status'] }}
                                    </span>
                                </td>
                                <td>{{ $row['attendance_status'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif

</body>
</html>
