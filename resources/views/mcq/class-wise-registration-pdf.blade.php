<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Register — {{ $exam->title }}</title>
    <style>
        @page {
            margin: 14mm 12mm 14mm 12mm;
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

        /* ══════════════════════════════════════
           FULL-BLEED HEADER
           Negative margins break out of @page margins
           so header is truly edge-to-edge
        ══════════════════════════════════════ */
        .page-header {
            width: calc(100% + 24mm);
            background: #0b2558;
            padding: 0;
            margin-left: -12mm;
            margin-right: -12mm;
            margin-top: -14mm;
        }
        .header-inner {
            width: 100%;
            border-collapse: collapse;
            padding: 0;
        }
        .header-logo-cell {
            width: 72px;
            padding: 10px 0 10px 14px;
            vertical-align: middle;
            background: #0b2558;
        }
        .header-logo-cell img {
            width: 52px;
            height: 52px;
            object-fit: contain;
            display: block;
        }
        .logo-placeholder {
            width: 52px;
            height: 52px;
            background: rgba(255,255,255,0.12);
            border-radius: 50%;
            display: block;
        }
        .header-org-cell {
            padding: 10px 10px 10px 8px;
            vertical-align: middle;
            background: #0b2558;
        }
        .org-name {
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.3px;
            line-height: 1.1;
        }
        .org-sub {
            font-size: 7px;
            color: rgba(255,255,255,0.55);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 3px;
        }
        /* separator strip */
        .header-sep-cell {
            width: 3px;
            background: rgba(255,255,255,0.15);
            padding: 0;
        }
        /* right meta section */
        .header-meta-cell {
            padding: 0;
            vertical-align: middle;
            text-align: right;
            background: #0d1f4a;
            width: 195px;
        }
        .header-meta-inner {
            padding: 8px 14px 8px 10px;
        }
        .meta-row {
            display: block;
            margin-bottom: 3px;
            line-height: 1.3;
        }
        .meta-row:last-child {
            margin-bottom: 0;
        }
        .meta-lbl {
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: rgba(255,255,255,0.45);
            display: block;
        }
        .meta-val {
            font-size: 8.5px;
            font-weight: 700;
            color: #ffffff;
            display: block;
        }
        /* accent bottom line on header */
        .header-accent {
            height: 3px;
            background: linear-gradient(90deg, #2563eb 0%, #60a5fa 50%, #1e40af 100%);
            width: 100%;
            margin: 0;
            padding: 0;
        }

        /* ── CONTENT WRAPPER ── */
        .content-wrap {
            padding: 0;
        }

        /* ── EXAM BANNER ── */
        .exam-banner {
            background: #1e3a6e;
            border-radius: 5px;
            padding: 8px 12px;
            margin-bottom: 0;
            width: 100%;
            margin-top: 10px;
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
        .stat-box {
            display: inline-block;
            background: rgba(255,255,255,0.12);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 4px;
            padding: 4px 10px;
            text-align: center;
        }
        .stat-num {
            font-size: 16px;
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

        /* ── CLASS SUB-HEADER ── */
        .class-sub-header {
            background: #162d58;
            padding: 4px 12px 5px 12px;
            border-top: 1px solid rgba(255,255,255,0.08);
            border-radius: 0 0 4px 4px;
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
            margin-top: 8px;
        }
        table.report-table thead tr {
            background: #0b2558;
        }
        table.report-table th {
            color: #c8d9f5;
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
        table.report-table tbody tr:nth-child(odd) td  { background: #fff; }
        table.report-table tbody tr:nth-child(even) td { background: #f7f9fd; }
        table.report-table tbody tr:last-child td      { border-bottom: 2px solid #0b2558; }

        /* ── COLUMNS ── */
        .col-sl    { width: 22px; text-align: center; font-weight: 700; color: #6b7a9e; }
        .col-photo { width: 36px; text-align: center; }
        .col-name  { min-width: 100px; }
        .col-adm   { width: 80px; }
        .col-ticket{ width: 80px; }
        .col-status{ width: 85px; }
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
            background: #1e3a6e;
            color: #fff;
            font-weight: 700;
            font-size: 11px;
            text-align: center;
            margin: 0 auto;
            display: block;
        }

        /* ── TYPOGRAPHY ── */
        .student-name { font-weight: 700; color: #0b2236; font-size: 9px; }
        .mono         { font-family: 'Courier New', monospace; font-size: 8px; color: #334155; }
        .ticket-val   { font-family: 'Courier New', monospace; font-size: 8.5px; font-weight: 700; color: #1e40af; }

        /* ── BADGES ── */
        .badge {
            display: inline-block;
            padding: 2px 7px;
            font-size: 7px;
            font-weight: 700;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-approved { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-pending  { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
        .badge-rejected { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-payment  { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

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
            width: 100%;
            display: table;
        }
        .footer-left  { display: table-cell; text-align: left; }
        .footer-right { display: table-cell; text-align: right; }

        /* ── EMPTY ── */
        .empty-state {
            text-align: center;
            color: #8898b4;
            padding: 40px 20px;
            font-size: 10px;
        }
    </style>
</head>
<body>

    @if(empty($groupedRows))
        {{-- ══ EMPTY STATE ══ --}}
        <div class="page-header">
            <table class="header-inner">
                <tr>
                    <td class="header-logo-cell">
                        @if(!empty($logoSrc))
                            <img src="{{ $logoSrc }}" alt="Logo">
                        @else
                            <span class="logo-placeholder"></span>
                        @endif
                    </td>
                    <td class="header-org-cell">
                        <div class="org-name">{{ $orgName }}</div>
                        <div class="org-sub">Talent Search Exams &nbsp;·&nbsp; Registration Register — Official Report</div>
                    </td>
                    <td class="header-sep-cell"></td>
                    <td class="header-meta-cell">
                        <div class="header-meta-inner">
                            @if($school)
                                <span class="meta-row">
                                    <span class="meta-lbl">School</span>
                                    <span class="meta-val">{{ $school->name }}</span>
                                </span>
                            @endif
                            <span class="meta-row">
                                <span class="meta-lbl">Generated</span>
                                <span class="meta-val">{{ $generatedAt }}</span>
                            </span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <div class="header-accent"></div>
        <div class="empty-state">No registered candidates found for this exam.</div>
    @else
        @foreach($groupedRows as $className => $students)
        <div class="class-page">

            {{-- ══ FULL-BLEED HEADER ══ --}}
            <div class="page-header">
                <table class="header-inner">
                    <tr>
                        <td class="header-logo-cell">
                            @if(!empty($logoSrc))
                                <img src="{{ $logoSrc }}" alt="Logo">
                            @else
                                <span class="logo-placeholder"></span>
                            @endif
                        </td>
                        <td class="header-org-cell">
                            <div class="org-name">{{ $orgName }}</div>
                            <div class="org-sub">Talent Search Exams &nbsp;·&nbsp; Registration Register — Official Report</div>
                        </td>
                        <td class="header-sep-cell"></td>
                        <td class="header-meta-cell">
                            <div class="header-meta-inner">
                                @if($school)
                                    <span class="meta-row">
                                        <span class="meta-lbl">School</span>
                                        <span class="meta-val">{{ $school->name }}</span>
                                    </span>
                                @endif
                                <span class="meta-row">
                                    <span class="meta-lbl">Class</span>
                                    <span class="meta-val">{{ $className }}</span>
                                </span>
                                <span class="meta-row">
                                    <span class="meta-lbl">Generated</span>
                                    <span class="meta-val">{{ $generatedAt }}</span>
                                </span>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="header-accent"></div>

            {{-- ══ CONTENT ══ --}}
            <div class="content-wrap">

                {{-- EXAM BANNER --}}
                <div class="exam-banner">
                    <table class="exam-banner-table">
                        <tr>
                            <td style="vertical-align: middle;">
                                <div class="exam-title-text">
                                    {{ $exam->title }}
                                    @if($exam->code)<span class="exam-code">({{ $exam->code }})</span>@endif
                                </div>
                                <div class="exam-meta">
                                    @if($exam->scheduled_at)Scheduled: {{ $exam->scheduled_at->format('d M Y') }} &nbsp;&middot;&nbsp; @endif
                                    Class: <strong style="color:#fff;">{{ $className }}</strong>
                                </div>
                            </td>
                            <td style="width: 90px; vertical-align: middle; text-align: right;">
                                <div class="stat-box">
                                    <div class="stat-num">{{ count($students) }}</div>
                                    <div class="stat-label">{{ count($students) === 1 ? 'Candidate' : 'Candidates' }}</div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                {{-- CLASS SUB-HEADER --}}
                <div class="class-sub-header">
                    <span class="class-name-text">Class {{ $className }}</span>
                    <span class="class-count-text"> &mdash; {{ count($students) }} {{ count($students) === 1 ? 'student' : 'students' }} registered</span>
                </div>

                {{-- STUDENT TABLE --}}
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
                                            str_contains($st, 'reject')   => 'badge-rejected',
                                            str_contains($st, 'payment')  => 'badge-payment',
                                            default                       => 'badge-pending',
                                        };
                                    @endphp
                                    <span class="badge {{ $badgeCls }}">{{ $row['approval_status'] }}</span>
                                </td>
                                <td class="col-att">
                                    @php
                                        $att = strtolower($row['attendance_status']);
                                        $attCls = match(true) {
                                            $att === 'present' => 'att-present',
                                            $att === 'absent'  => 'att-absent',
                                            default            => 'att-pending',
                                        };
                                    @endphp
                                    <span class="{{ $attCls }}" style="font-size:8px; text-transform:capitalize;">{{ $row['attendance_status'] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            </div>{{-- end .content-wrap --}}

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
