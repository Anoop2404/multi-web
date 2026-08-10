<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Register — {{ $exam->title }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1a2236;
            font-size: 10.5px;
            margin: 0; padding: 0;
            background: #fff;
        }

        .class-page { page-break-after: always; }
        .class-page:last-child { page-break-after: avoid; }

        /* ════════════════════════════════════════════
           HEADER — centered official document style
        ════════════════════════════════════════════ */
        .page-header {
            width: 100%;
            background: #0b2558;
            padding: 14px 20px 0 20px;
        }

        /* Row 1: logo + sahodaya name (centered together) */
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
            font-size: 8.5px;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-top: 3px;
            display: block;
        }

        /* Horizontal divider inside header */
        .hdr-divider {
            border: none;
            border-top: 1px solid rgba(255,255,255,0.15);
            margin: 0 0 0 0;
        }

        /* Row 2: exam name | school | class — info row */
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
            font-size: 8.5px;
            text-transform: uppercase;
            letter-spacing: 0.9px;
            color: rgba(255,255,255,0.4);
            display: block;
            margin-bottom: 2px;
        }
        .info-val {
            font-size: 10.5px;
            font-weight: 700;
            color: #ffffff;
            display: block;
        }
        .info-val-lg {
            font-size: 11px;
            font-weight: 700;
            color: #7eb3ff;
            display: block;
        }

        /* Blue accent stripe */
        .header-stripe {
            width: 100%;
            height: 3px;
            background: #2563eb;
        }

        /* ── CONTENT ── */
        .content-wrap {
            padding: 10px 14px 14px 14px;
        }

        /* ── CLASS SUB-HEADER ── */
        .class-sub {
            background: #0f2d5e;
            border: 1px solid #1e3a6e;
            border-radius: 4px;
            padding: 5px 12px;
            margin-bottom: 8px;
        }
        .cls-name  { font-size: 11.5px; font-weight: 700; color: #fff; }
        .cls-count { font-size: 9.5px; color: #a8c0e8; }

        /* ── TABLE ── */
        table.report-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.report-table thead tr { background: #0b2558; }
        table.report-table th {
            color: #c8d9f5;
            text-align: left;
            padding: 5px 6px;
            font-size: 9.5px;
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
            font-size: 10.5px;
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

        .student-name { font-weight: 700; color: #0b2236; font-size: 10.5px; }
        .mono         { font-family: 'Courier New', monospace; font-size: 9.5px; color: #334155; }
        .ticket-val   { font-family: 'Courier New', monospace; font-size: 10px; font-weight: 700; color: #1e40af; }

        .badge {
            display: inline-block;
            padding: 2px 7px;
            font-size: 9px; font-weight: 700;
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

        .page-footer {
            margin-top: 8px;
            padding-top: 5px;
            border-top: 1px solid #d0daf0;
            font-size: 9px; color: #8898b4;
            width: 100%; display: table;
        }
        .footer-left  { display: table-cell; text-align: left; }
        .footer-right { display: table-cell; text-align: right; }

        .empty-state { text-align: center; color: #8898b4; padding: 40px 20px; font-size: 11.5px; }
    </style>
</head>
<body>

    {{-- ════════════════════════════════════════════
         MACRO: header — reused for each page
    ════════════════════════════════════════════ --}}

    @if(empty($groupedRows))
        <div class="page-header">
            <table class="hdr-top">
                <tr>
                    @if(!empty($logoSrc))
                    <td class="hdr-logo-cell"><img src="{{ $logoSrc }}" alt=""></td>
                    @endif
                    <td class="hdr-org-cell">
                        <span class="org-name">{{ $orgName }}</span>
                        <span class="org-sub">Talent Search Exams &nbsp;&middot;&nbsp; Registration Register &nbsp;&middot;&nbsp; Official Report</span>
                    </td>
                </tr>
            </table>
            <hr class="hdr-divider">
            <table class="hdr-info-bar">
                <tr>
                    @if($school)
                    <td class="info-cell">
                        <span class="info-lbl">School</span>
                        <span class="info-val">{{ $school->name }}</span>
                    </td>
                    @endif
                    <td class="info-cell">
                        <span class="info-lbl">Generated</span>
                        <span class="info-val">{{ $generatedAt }}</span>
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
                {{-- Row 1: Logo + Sahodaya Name --}}
                <table class="hdr-top">
                    <tr>
                        @if(!empty($logoSrc))
                        <td class="hdr-logo-cell"><img src="{{ $logoSrc }}" alt=""></td>
                        @endif
                        <td class="hdr-org-cell">
                            <span class="org-name">{{ $orgName }}</span>
                            <span class="org-sub">Talent Search Exams &nbsp;&middot;&nbsp; Registration Register &nbsp;&middot;&nbsp; Official Report</span>
                        </td>
                    </tr>
                </table>

                <hr class="hdr-divider">

                {{-- Row 2: Exam · School · Class · Generated --}}
                <table class="hdr-info-bar">
                    <tr>
                        <td class="info-cell">
                            <span class="info-lbl">Exam</span>
                            <span class="info-val-lg">
                                {{ $exam->title }}@if($exam->code) ({{ $exam->code }})@endif
                            </span>
                        </td>
                        @if($school)
                        <td class="info-cell">
                            <span class="info-lbl">School</span>
                            <span class="info-val">{{ $school->name }}</span>
                        </td>
                        @endif
                        <td class="info-cell" style="width:60px;">
                            <span class="info-lbl">Class</span>
                            <span class="info-val">{{ $className }}</span>
                        </td>
                        <td class="info-cell" style="width:110px;">
                            <span class="info-lbl">Generated</span>
                            <span class="info-val">{{ $generatedAt }}</span>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="header-stripe"></div>

            {{-- ══ CONTENT ══ --}}
            <div class="content-wrap">

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
                            <th class="col-adm">School Adm. No.</th>
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
                            <td>
                                <span class="mono">{{ $row['admission_number'] }}</span>
                            </td>
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
                    <div class="footer-left">{{ $exam->title }} &mdash; Registration Register &mdash; {{ $orgName }}</div>
                    <div class="footer-right">
                        @if($school){{ $school->name }} &nbsp;|&nbsp; Class {{ $className }} &nbsp;|&nbsp; @endif
                        {{ $generatedAt }}
                    </div>
                </div>

            </div>{{-- .content-wrap --}}
        </div>
        @endforeach
    @endif

</body>
</html>
