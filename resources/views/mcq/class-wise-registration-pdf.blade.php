<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Register — {{ $exam->title }}</title>
    <style>
        /* DomPDF: zero @page margins → header is naturally full-bleed.
           All spacing is handled manually via padding on .content-wrap */
        @page {
            margin: 0;
        }
        * { box-sizing: border-box; }
        html, body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1a2236;
            font-size: 8.5px;
            margin: 0;
            padding: 0;
            background: #fff;
        }

        /* ── PAGE BREAK ── */
        .class-page         { page-break-after: always; }
        .class-page:last-child { page-break-after: avoid; }

        /* ════════════════════════════════════════
           FULL-BLEED HEADER
        ════════════════════════════════════════ */
        .page-header {
            width: 100%;
            background: #0b2558;
        }
        table.header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .hcell-logo {
            width: 76px;
            padding: 11px 0 11px 14px;
            vertical-align: middle;
            background: #0b2558;
        }
        .hcell-logo img {
            width: 52px;
            height: 52px;
            object-fit: contain;
            display: block;
        }
        .hcell-org {
            padding: 11px 8px;
            vertical-align: middle;
            background: #0b2558;
        }
        .org-name {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            letter-spacing: 0.3px;
            line-height: 1.15;
        }
        .org-sub {
            font-size: 7px;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 4px;
        }
        /* vertical divider between org and meta */
        .hcell-div {
            width: 2px;
            background: rgba(255,255,255,0.15);
            padding: 0;
        }
        .hcell-meta {
            width: 195px;
            padding: 0;
            vertical-align: middle;
            background: #091c42;
        }
        .meta-inner {
            padding: 9px 14px 9px 12px;
            text-align: right;
        }
        .meta-block {
            margin-bottom: 4px;
        }
        .meta-block:last-child { margin-bottom: 0; }
        .meta-lbl {
            font-size: 6.5px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: rgba(255,255,255,0.4);
            display: block;
        }
        .meta-val {
            font-size: 8.5px;
            font-weight: 700;
            color: #fff;
            display: block;
        }
        /* blue accent stripe below header */
        .header-stripe {
            width: 100%;
            height: 3px;
            background: #2563eb;
            margin: 0;
            padding: 0;
        }

        /* ════════════════════════════════════════
           CONTENT — padded area below header
        ════════════════════════════════════════ */
        .content-wrap {
            padding: 10px 14px 14px 14px;
        }

        /* ── EXAM BANNER ── */
        .exam-banner {
            background: #1e3a6e;
            border-radius: 5px 5px 0 0;
            padding: 8px 12px;
            width: 100%;
        }
        table.banner-table {
            width: 100%;
            border-collapse: collapse;
        }
        .exam-title-text {
            font-size: 12px;
            font-weight: 700;
            color: #fff;
            line-height: 1.2;
        }
        .exam-code-lbl {
            font-size: 8px;
            color: #a8c0e8;
            font-weight: 400;
            margin-left: 4px;
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
        .stat-num   { font-size: 16px; font-weight: 700; color: #fff; line-height: 1; display: block; }
        .stat-lbl   { font-size: 7px; color: #a8c0e8; text-transform: uppercase; letter-spacing: 0.4px; display: block; }

        /* ── CLASS ROW ── */
        .class-sub {
            background: #162d58;
            padding: 4px 12px 5px;
            border-radius: 0 0 4px 4px;
        }
        .cls-name  { font-size: 10px; font-weight: 700; color: #fff; }
        .cls-count { font-size: 8px; color: #a8c0e8; }

        /* ── TABLE ── */
        table.report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
        }
        table.report-table thead tr { background: #0b2558; }
        table.report-table th {
            color: #c8d9f5;
            text-align: left;
            padding: 5px 6px;
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            border-right: 1px solid rgba(255,255,255,0.08);
        }
        table.report-table th:last-child { border-right: none; }
        table.report-table td {
            padding: 5px 6px;
            border-bottom: 1px solid #e8edf5;
            border-right: 1px solid #eef1f8;
            vertical-align: middle;
        }
        table.report-table td:last-child { border-right: none; }
        table.report-table tbody tr:nth-child(odd)  td { background: #fff; }
        table.report-table tbody tr:nth-child(even) td { background: #f7f9fd; }
        table.report-table tbody tr:last-child      td { border-bottom: 2px solid #0b2558; }

        .col-sl    { width: 22px; text-align: center; font-weight: 700; color: #6b7a9e; }
        .col-photo { width: 36px; text-align: center; }
        .col-adm   { width: 80px; }
        .col-ticket{ width: 80px; }
        .col-status{ width: 85px; }
        .col-att   { width: 55px; }

        /* ── PHOTO ── */
        .photo-img {
            width: 30px; height: 30px;
            border-radius: 50%; object-fit: cover;
            border: 1.5px solid #c7d3ec;
            display: block; margin: 0 auto;
        }
        .photo-avatar {
            width: 30px; height: 30px; line-height: 30px;
            border-radius: 50%;
            background: #1e3a6e;
            color: #fff; font-weight: 700; font-size: 11px;
            text-align: center; display: block; margin: 0 auto;
        }

        .student-name { font-weight: 700; color: #0b2236; font-size: 9px; }
        .mono         { font-family: 'Courier New', monospace; font-size: 8px; color: #334155; }
        .ticket-val   { font-family: 'Courier New', monospace; font-size: 8.5px; font-weight: 700; color: #1e40af; }

        /* ── BADGES ── */
        .badge {
            display: inline-block;
            padding: 2px 7px;
            font-size: 7px; font-weight: 700;
            border-radius: 20px;
            text-transform: uppercase; letter-spacing: 0.3px;
        }
        .badge-approved { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .badge-pending  { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
        .badge-rejected { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .badge-payment  { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

        .att-present { color: #166534; font-weight: 700; }
        .att-absent  { color: #991b1b; font-weight: 700; }
        .att-pending { color: #6b7280; }

        /* ── FOOTER ── */
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

    @if(empty($groupedRows))
        {{-- EMPTY --}}
        <div class="page-header">
            <table class="header-table">
                <tr>
                    <td class="hcell-logo">
                        @if(!empty($logoSrc))<img src="{{ $logoSrc }}" alt="">@endif
                    </td>
                    <td class="hcell-org">
                        <div class="org-name">{{ $orgName }}</div>
                        <div class="org-sub">Talent Search Exams &nbsp;&middot;&nbsp; Registration Register — Official Report</div>
                    </td>
                    <td class="hcell-div"></td>
                    <td class="hcell-meta">
                        <div class="meta-inner">
                            @if($school)
                                <div class="meta-block">
                                    <span class="meta-lbl">School</span>
                                    <span class="meta-val">{{ $school->name }}</span>
                                </div>
                            @endif
                            <div class="meta-block">
                                <span class="meta-lbl">Generated</span>
                                <span class="meta-val">{{ $generatedAt }}</span>
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <div class="header-stripe"></div>
        <div class="content-wrap">
            <div class="empty-state">No registered candidates found for this exam.</div>
        </div>

    @else
        @foreach($groupedRows as $className => $students)
        <div class="class-page">

            {{-- ══ FULL-BLEED HEADER ══ --}}
            <div class="page-header">
                <table class="header-table">
                    <tr>
                        <td class="hcell-logo">
                            @if(!empty($logoSrc))
                                <img src="{{ $logoSrc }}" alt="">
                            @endif
                        </td>
                        <td class="hcell-org">
                            <div class="org-name">{{ $orgName }}</div>
                            <div class="org-sub">Talent Search Exams &nbsp;&middot;&nbsp; Registration Register — Official Report</div>
                        </td>
                        <td class="hcell-div"></td>
                        <td class="hcell-meta">
                            <div class="meta-inner">
                                @if($school)
                                    <div class="meta-block">
                                        <span class="meta-lbl">School</span>
                                        <span class="meta-val">{{ $school->name }}</span>
                                    </div>
                                @endif
                                <div class="meta-block">
                                    <span class="meta-lbl">Class</span>
                                    <span class="meta-val">{{ $className }}</span>
                                </div>
                                <div class="meta-block">
                                    <span class="meta-lbl">Generated</span>
                                    <span class="meta-val">{{ $generatedAt }}</span>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="header-stripe"></div>

            {{-- ══ CONTENT ══ --}}
            <div class="content-wrap">

                {{-- Exam Banner --}}
                <div class="exam-banner">
                    <table class="banner-table">
                        <tr>
                            <td style="vertical-align:middle;">
                                <div class="exam-title-text">
                                    {{ $exam->title }}
                                    @if($exam->code)<span class="exam-code-lbl">({{ $exam->code }})</span>@endif
                                </div>
                                <div class="exam-meta">
                                    @if($exam->scheduled_at)Scheduled: {{ $exam->scheduled_at->format('d M Y') }} &nbsp;&middot;&nbsp; @endif
                                    Class: <strong style="color:#fff;">{{ $className }}</strong>
                                </div>
                            </td>
                            <td style="width:90px; text-align:right; vertical-align:middle;">
                                <div class="stat-box">
                                    <span class="stat-num">{{ count($students) }}</span>
                                    <span class="stat-lbl">{{ count($students) === 1 ? 'Candidate' : 'Candidates' }}</span>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>

                {{-- Class sub-header --}}
                <div class="class-sub">
                    <span class="cls-name">Class {{ $className }}</span>
                    <span class="cls-count"> &mdash; {{ count($students) }} {{ count($students) === 1 ? 'student' : 'students' }} registered</span>
                </div>

                {{-- Student Table --}}
                <table class="report-table">
                    <thead>
                        <tr>
                            <th class="col-sl">#</th>
                            <th class="col-photo">Photo</th>
                            <th>Student Name</th>
                            <th class="col-adm">Admission No.</th>
                            <th class="col-ticket">Hall Ticket No.</th>
                            @if(!$school)<th style="min-width:90px;">School</th>@endif
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
                            <td><span class="student-name">{{ $row['student_name'] }}</span></td>
                            <td><span class="mono">{{ $row['admission_number'] }}</span></td>
                            <td><span class="{{ $row['hall_ticket_no'] !== '—' ? 'ticket-val' : 'mono' }}">{{ $row['hall_ticket_no'] }}</span></td>
                            @if(!$school)<td style="font-size:8px;">{{ $row['school_name'] }}</td>@endif
                            <td>
                                @php
                                    $st = strtolower($row['approval_status']);
                                    $bc = match(true) {
                                        str_contains($st,'approved') => 'badge-approved',
                                        str_contains($st,'reject')   => 'badge-rejected',
                                        str_contains($st,'payment')  => 'badge-payment',
                                        default                      => 'badge-pending',
                                    };
                                @endphp
                                <span class="badge {{ $bc }}">{{ $row['approval_status'] }}</span>
                            </td>
                            <td>
                                @php
                                    $att = strtolower($row['attendance_status']);
                                    $ac  = match(true) {
                                        $att==='present' => 'att-present',
                                        $att==='absent'  => 'att-absent',
                                        default          => 'att-pending',
                                    };
                                @endphp
                                <span class="{{ $ac }}" style="font-size:8px;text-transform:capitalize;">{{ $row['attendance_status'] }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Footer --}}
                <div class="page-footer">
                    <div class="footer-left">{{ $exam->title }} — Registration Register</div>
                    <div class="footer-right">
                        @if($school){{ $school->name }} &nbsp;|&nbsp;@endif
                        Generated: {{ $generatedAt }}
                    </div>
                </div>

            </div>{{-- .content-wrap --}}
        </div>
        @endforeach
    @endif

</body>
</html>
