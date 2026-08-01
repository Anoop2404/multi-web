<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }} — {{ $academicYear ?? '' }}</title>
    <style>
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1a2236;
            font-size: 9px;
            margin: 0; padding: 0;
            background: #fff;
        }

        .page-header {
            width: 100%;
            background: #0b2558;
            padding: 14px 20px 0 20px;
        }

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
            font-size: 7px;
            color: rgba(255,255,255,0.5);
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-top: 3px;
            display: block;
        }

        .hdr-divider {
            border: none;
            border-top: 1px solid rgba(255,255,255,0.15);
            margin: 0;
        }

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
            font-size: 6.5px;
            text-transform: uppercase;
            letter-spacing: 0.9px;
            color: rgba(255,255,255,0.4);
            display: block;
            margin-bottom: 2px;
        }
        .info-val {
            font-size: 9px;
            font-weight: 700;
            color: #ffffff;
            display: block;
        }
        .info-val-lg {
            font-size: 9.5px;
            font-weight: 700;
            color: #7eb3ff;
            display: block;
        }

        .header-stripe {
            width: 100%;
            height: 3px;
            background: #2563eb;
        }

        .content-wrap {
            padding: 14px;
        }

        table.report-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        table.report-table thead tr { background: #0b2558; }
        table.report-table th {
            color: #c8d9f5;
            text-align: left;
            padding: 6px 8px;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            border-right: 1px solid rgba(255,255,255,0.08);
        }
        table.report-table th:last-child { border-right: none; }
        table.report-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e8edf5;
            border-right: 1px solid #eef1f8;
            vertical-align: middle;
            font-size: 8.5px;
        }
        table.report-table td:last-child { border-right: none; }
        table.report-table tbody tr:nth-child(odd)  td { background: #fff; }
        table.report-table tbody tr:nth-child(even) td { background: #f7f9fd; }
        table.report-table tbody tr:last-child      td { border-bottom: 2px solid #0b2558; }

        .page-footer {
            margin-top: 12px;
            padding-top: 6px;
            border-top: 1px solid #d0daf0;
            font-size: 7.5px; color: #8898b4;
            width: 100%; display: table;
        }
        .footer-left  { display: table-cell; text-align: left; }
        .footer-right { display: table-cell; text-align: right; }

        .empty-state { text-align: center; color: #8898b4; padding: 50px 20px; font-size: 11px; }
    </style>
</head>
<body>

    <div class="page-header">
        <table class="hdr-top">
            <tr>
                @if(!empty($logoSrc))
                <td class="hdr-logo-cell"><img src="{{ $logoSrc }}" alt=""></td>
                @endif
                <td class="hdr-org-cell">
                    <span class="org-name">{{ $orgName ?? 'Sahodaya' }}</span>
                    <span class="org-sub">Official Board Result Report &nbsp;&middot;&nbsp; {{ $title }}</span>
                </td>
            </tr>
        </table>
        <hr class="hdr-divider">
        <table class="hdr-info-bar">
            <tr>
                <td class="info-cell">
                    <span class="info-lbl">Report Title</span>
                    <span class="info-val-lg">{{ $title }}</span>
                </td>
                @if(!empty($academicYear))
                <td class="info-cell" style="width:100px;">
                    <span class="info-lbl">Academic Year</span>
                    <span class="info-val">{{ $academicYear }}</span>
                </td>
                @endif
                @if(!empty($selectedClass))
                <td class="info-cell" style="width:70px;">
                    <span class="info-lbl">Class</span>
                    <span class="info-val">Class {{ $selectedClass }}</span>
                </td>
                @endif
                <td class="info-cell" style="width:120px;">
                    <span class="info-lbl">Generated At</span>
                    <span class="info-val">{{ $generatedAt ?? now()->format('d M Y · h:i A') }}</span>
                </td>
            </tr>
        </table>
    </div>
    <div class="header-stripe"></div>

    <div class="content-wrap">
        @if(empty($rows) || count($rows) === 0)
            <div class="empty-state">No records found matching the report criteria for {{ $academicYear ?? 'the selected session' }}.</div>
        @else
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width:28px; text-align:center;">#</th>
                        @foreach($columns as $col)
                            <th>{{ $col['label'] ?? ucfirst(str_replace('_', ' ', $col['key'])) }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $idx => $row)
                        <tr>
                            <td style="text-align:center; font-weight:700; color:#6b7a9e;">{{ $idx + 1 }}</td>
                            @foreach($columns as $col)
                                @php
                                    $val = data_get($row, $col['key']);
                                    if (is_numeric($val) && is_float($val + 0)) {
                                        $val = number_format($val, 2);
                                    }
                                @endphp
                                <td>{{ $val ?? '—' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="page-footer">
                <div class="footer-left">{{ $title }} &mdash; {{ $orgName ?? 'Sahodaya' }}</div>
                <div class="footer-right">Generated: {{ $generatedAt ?? now()->format('d M Y · h:i A') }}</div>
            </div>
        @endif
    </div>

</body>
</html>
