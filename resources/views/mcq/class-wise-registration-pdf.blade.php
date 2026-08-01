<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Register — {{ $exam->title }}</title>
    <style>
        @page {
            margin: 14mm 12mm 16mm 12mm;
        }
        * {
            box-sizing: border-box;
        }
        html, body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1a2236;
            font-size: 8.5px;
            margin: 0;
            padding: 0;
            background: #fff;
        }

        /* ── PAGE BREAK ── */
        .class-page {
            page-break-after: always;
        }
        .class-page:last-child {
            page-break-after: avoid;
        }

        /* ── HEADER ── */
        .report-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        .header-left {
            vertical-align: middle;
            width: 56px;
        }
        .header-mid {
            vertical-align: middle;
            padding-left: 10px;
        }
        .header-right {
            vertical-align: middle;
            text-align: right;
        }
        .logo {
            width: 52px;
            height: 52px;
            object-fit: contain;
        }
        .org-name {
            font-size: 15px;
            font-weight: 700;
            color: #0b2e6e;
            letter-spacing: 0.2px;
            line-height: 1.1;
        }
        .sub-tag {
            font-size: 7.5px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            margin-top: 3px;
        }
        .meta-pill-wrap {
            display: inline-block;
            text-align: right;
        }
        .meta-pill {
            display: inline-block;
            background: #f0f4fb;
            border: 1px solid #d0daf0;
            border-radius: 5px;
            padding: 5px 10px;
            font-size: 8px;
            line-height: 1.5;
            color: #1a2236;
        }
        .meta-pill .label {
            color: #8898b4;
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .meta-pill .val {
            font-weight: 700;
            color: #0b2e6e;
        }

        /* ── DIVIDER ── */
        .header-divider {
            height: 3px;
            background: linear-gradient(90deg, #0b2e6e 0%, #3b6fd4 60%, #c7d7f5 100%);
            border-radius: 2px;
            margin: 7px 0 8px 0;
        }

        /* ── EXAM BANNER ── */
        .exam-banner {
            background: #0b2e6e;
            color: #fff;
            border-radius: 5px 5px 0 0;
            padding: 8px 12px 6px 12px;
            margin-bottom: 0;
            width: 100%;
        }
        .exam-banner-table {
            width: 100%;
            border-collapse: collapse;
        }
        .exam-title-text {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.2px;
            color: #ffffff;
            line-height: 1.2;
        }
        .exam-code {
            font-size: 8px;
            color: #a8c0e8;
            font-weight: 400;
            margin-left: 5px;
        }
        .exam-meta {
            font-size: 7.5px;
            color: #a8c0e8;
            margin-top: 2px;
        }
        .exam-stats {
            text-align: right;
            vertical-align: middle;
        }
        .stat-box {
            display: inline-block;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 4px;
            padding: 4px 10px;
            text-align: center;
            margin-left: 5px;
        }
        .stat-num {
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            line-height: 1;
        }
        .stat-label {
            font-size: 7px;
            color: #a8c0e8;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        /* ── CLASS SECTION HEADER ── */
        .class-section-header {
            background: #1e3a6e;
            color: #fff;
            padding: 5px 12px;
            display: table;
            width: 100%;
            border-radius: 0 0 0 0;
        }
        .class-section-header.standalone {
            border-radius: 5px 5px 0 0;
            margin-top: 10px;
        }
        .class-name-text {
            font-size: 10px;
            font-weight: 700;
            color: #fff;
        }
        .class-count-text {
            font-size: 8px;
            color: #a8c0e8;
            font-weight: 400;
        }

        /* ── TABLE ── */
        table.report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }
        table.report-table thead tr {
            background: #1e3a6e;
        }
        table.report-table th {
            color: #cddcf5;
            text-align: left;
            padding: 5px 6px;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: none;
            border-right: 1px solid rgba(255,255,255,0.08);
            font-weight: 700;
        }
        table.report-table th:last-child {
            border-right: none;
        }
        table.report-table td {
            padding: 5px 6px;
            border-bottom: 1px solid #e8edf5;
            border-right: 1px solid #eef1f8;
            vertical-align: middle;
            color: #1a2236;
        }
        table.report-table td:last-child {
            border-right: none;
        }
        table.report-table tbody tr:nth-child(odd) td {
            background: #fff;
        }
        table.report-table tbody tr:nth-child(even) td {
            background: #f7f9fd;
        }
        table.report-table tbody tr:last-child td {
            border-bottom: 2px solid #1e3a6e;
        }

        /* ── COLUMN WIDTHS ── */
        .col-sl    { width: 22px; text-align: center; font-weight: 700; color: #6b7a9e; }
        .col-photo { width: 36px; text-align: center; }
        .col-name  { min-width: 100px; }
        .col-adm   { width: 80px; }
        .col-ticket{ width: 80px; }
        .col-status{ width: 80px; }
        .col-att   { width: 55px; }

        /* ── PHOTO ── */
        .photo-img {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            object-fit: cover;
            border: 1.5px solid #c7d3ec;
            display: block;
            margin: 0 auto;
        }
        .photo-avatar {
            width: 30px;
            height: 30px;
            line-height: 30px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b6fd4, #0b2e6e);
            color: #fff;
            font-weight: 700;
            font-size: 11px;
            text-align: center;
            margin: 0 auto;
            display: block;
        }

        /* ── STUDENT NAME ── */
        .student-name {
            font-weight: 700;
            color: #0b2236;
            font-size: 9px;
        }

        /* ── MONOSPACE FIELDS ── */
        .mono {
            font-family: 'Courier New', Courier, monospace;
            font-size: 8px;
            color: #334155;
        }
        .ticket-val {
            font-family: 'Courier New', Courier, monospace;
            font-size: 8.5px;
            font-weight: 700;
            color: #1e40af;
        }

        /* ── BADGE ── */
        .badge {
            display: inline-block;
            padding: 2px 7px;
            font-size: 7px;
            font-weight: 700;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-approved   { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-pending    { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
        .badge-rejected   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-payment    { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

        /* ── ATTENDANCE ── */
        .att-present { color: #166534; font-weight: 700; }
        .att-absent  { color: #991b1b; font-weight: 700; }
        .att-pending { color: #6b7280; }

        /* ── FOOTER ── */
        .page-footer {
            margin-top: 8px;
            padding-top: 5px;
            border-top: 1px solid #d0daf0;
            font-size: 7.5px;
            color: #8898b4;
            display: table;
            width: 100%;
        }
        .footer-left  { display: table-cell; text-align: left; }
        .footer-right { display: table-cell; text-align: right; }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            color: #8898b4;
            padding: 40px 20px;
            font-size: 10px;
        }
        .empty-icon {
            font-size: 30px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

    @if(empty($groupedRows))
        {{-- EMPTY STATE --}}
        <table class="report-header">
            <tr>
                @if(!empty($logoSrc))
                    <td class="header-left"><img class="logo" src="{{ $logoSrc }}" alt="Logo"></td>
                @endif
                <td class="header-mid">
                    <div class="org-name">{{ $orgName }}</div>
                    <div class="sub-tag">Talent Search Exams · Registration Register — Official Report</div>
                </td>
                @if($school)
                    <td class="header-right">
                        <div class="meta-pill">
                            <div><span class="label">School</span><br><span class="val">{{ $school->name }}</span></div>
                            <div style="margin-top:3px;"><span class="label">Generated</span><br><span class="val">{{ $generatedAt }}</span></div>
                        </div>
                    </td>
                @endif
            </tr>
        </table>
        <div class="header-divider"></div>
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            No registered candidates found for this exam.
        </div>
    @else
        @foreach($groupedRows as $className => $students)
        <div class="class-page">
            {{-- ══ PAGE HEADER ══ --}}
            <table class="report-header">
                <tr>
                    @if(!empty($logoSrc))
                        <td class="header-left"><img class="logo" src="{{ $logoSrc }}" alt="Logo"></td>
                    @endif
                    <td class="header-mid">
                        <div class="org-name">{{ $orgName }}</div>
                        <div class="sub-tag">Talent Search Exams · Registration Register — Official Report</div>
                    </td>
                    <td class="header-right">
                        <div class="meta-pill">
                            @if($school)<div><span class="label">School</span>&nbsp;&nbsp;<span class="val">{{ $school->name }}</span></div>@endif
                            <div><span class="label">Class</span>&nbsp;&nbsp;<span class="val">{{ $className }}</span></div>
                            <div><span class="label">Generated</span>&nbsp;&nbsp;<span class="val">{{ $generatedAt }}</span></div>
                        </div>
                    </td>
                </tr>
            </table>
            <div class="header-divider"></div>

            {{-- ══ EXAM BANNER ══ --}}
            <div class="exam-banner">
                <table class="exam-banner-table">
                    <tr>
                        <td style="vertical-align: middle;">
                            <div class="exam-title-text">
                                {{ $exam->title }}
                                @if($exam->code)<span class="exam-code">({{ $exam->code }})</span>@endif
                            </div>
                            <div class="exam-meta">
                                @if($exam->scheduled_at)Scheduled: {{ $exam->scheduled_at->format('d M Y') }} &nbsp;·&nbsp; @endif
                                Class: <strong style="color:#fff;">{{ $className }}</strong>
                            </div>
                        </td>
                        <td class="exam-stats" style="width: 130px; vertical-align: middle;">
                            <div class="stat-box">
                                <div class="stat-num">{{ count($students) }}</div>
                                <div class="stat-label">{{ count($students) === 1 ? 'Candidate' : 'Candidates' }}</div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            {{-- ══ CLASS SUB-HEADER (seamless below banner) ══ --}}
            <div style="background:#1e3a6e; padding:4px 12px 5px 12px; border-top:1px solid rgba(255,255,255,0.1);">
                <span class="class-name-text">Class {{ $className }}</span>
                <span class="class-count-text"> &mdash; {{ count($students) }} {{ count($students) === 1 ? 'student' : 'students' }} registered</span>
            </div>

            {{-- ══ STUDENT TABLE ══ --}}
            <table class="report-table">
                <thead>
                    <tr>
                        <th class="col-sl">#</th>
                        <th class="col-photo">Photo</th>
                        <th class="col-name">Student Name</th>
                        <th class="col-adm">Admission No.</th>
                        <th class="col-ticket">Hall Ticket No.</th>
                        @if(!$school) <th style="min-width:90px;">School</th> @endif
                        <th class="col-status">Approval Status</th>
                        <th class="col-att">Attendance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($students as $index => $row)
                        <tr>
                            <td class="col-sl">{{ $index + 1 }}</td>
                            <td class="col-photo">
                                @if(!empty($row['photo_src']))
                                    <img src="{{ $row['photo_src'] }}" class="photo-img" alt="">
                                @else
                                    <div class="photo-avatar">{{ strtoupper(substr($row['student_name'] ?? '?', 0, 1)) }}</div>
                                @endif
                            </td>
                            <td class="col-name">
                                <span class="student-name">{{ $row['student_name'] }}</span>
                            </td>
                            <td class="col-adm">
                                <span class="mono">{{ $row['admission_number'] }}</span>
                            </td>
                            <td class="col-ticket">
                                <span class="{{ $row['hall_ticket_no'] !== '—' ? 'ticket-val' : 'mono' }}">{{ $row['hall_ticket_no'] }}</span>
                            </td>
                            @if(!$school)
                                <td><span style="font-size:8px;">{{ $row['school_name'] }}</span></td>
                            @endif
                            <td class="col-status">
                                @php
                                    $st = strtolower($row['approval_status']);
                                    $badgeCls = match(true) {
                                        str_contains($st, 'approved') => 'badge-approved',
                                        str_contains($st, 'reject') => 'badge-rejected',
                                        str_contains($st, 'payment') => 'badge-payment',
                                        default => 'badge-pending',
                                    };
                                @endphp
                                <span class="badge {{ $badgeCls }}">{{ $row['approval_status'] }}</span>
                            </td>
                            <td class="col-att">
                                @php
                                    $att = strtolower($row['attendance_status']);
                                    $attCls = match(true) {
                                        $att === 'present' => 'att-present',
                                        $att === 'absent' => 'att-absent',
                                        default => 'att-pending',
                                    };
                                @endphp
                                <span class="{{ $attCls }}" style="font-size:8px; text-transform:capitalize;">{{ $row['attendance_status'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- ══ FOOTER ══ --}}
            <div class="page-footer">
                <div class="footer-left">{{ $exam->title }} &mdash; Registration Register</div>
                <div class="footer-right">
                    @if($school){{ $school->name }} &nbsp;|&nbsp; @endif
                    Generated: {{ $generatedAt }}
                </div>
            </div>
        </div>
        @endforeach
    @endif

</body>
</html>
