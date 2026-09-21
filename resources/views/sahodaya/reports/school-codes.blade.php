<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>School Code Directory — {{ $sahodaya->name }}</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm 10mm 14mm 10mm;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9.5px;
            color: #1e293b;
            line-height: 1.35;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border-bottom: 2px solid #0f3d7a;
            padding-bottom: 8px;
        }
        .header-logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
        }
        .org-name {
            font-size: 15px;
            font-weight: bold;
            color: #0f3d7a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-title {
            font-size: 13px;
            font-weight: bold;
            color: #1e293b;
            margin-top: 3px;
        }
        .doc-meta {
            font-size: 8.5px;
            color: #64748b;
            margin-top: 2px;
        }
        .summary-bar {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .summary-card {
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            border-radius: 4px;
            padding: 6px 10px;
            text-align: center;
        }
        .summary-num {
            font-size: 14px;
            font-weight: bold;
            color: #0f3d7a;
        }
        .summary-lbl {
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-top: 2px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
        }
        .table th {
            background-color: #0f3d7a;
            color: #ffffff;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 6px 7px;
            border: 1px solid #0f3d7a;
            text-align: left;
        }
        .table td {
            padding: 5.5px 7px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .code-badge {
            display: inline-block;
            font-family: 'Courier New', monospace;
            font-weight: bold;
            color: #0f3d7a;
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 1.5px 5px;
            border-radius: 3px;
            font-size: 9px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 8px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            @if(!empty($logoSrc))
                <td style="width: 55px; vertical-align: middle;">
                    <img src="{{ $logoSrc }}" alt="" class="header-logo">
                </td>
            @endif
            <td style="vertical-align: middle;">
                <div class="org-name">{{ $sahodaya->name }}</div>
                <div class="doc-title">Official School Code Register &amp; Directory</div>
                <div class="doc-meta">Generated on {{ $generatedAt }} · Prefix: {{ $sahodayaPrefix }}</div>
            </td>
        </tr>
    </table>

    <table class="summary-bar">
        <tr>
            <td style="width: 32%; padding-right: 8px;">
                <div class="summary-card">
                    <div class="summary-num">{{ $totalSchools }}</div>
                    <div class="summary-lbl">Member Schools</div>
                </div>
            </td>
            <td style="width: 32%; padding-right: 8px;">
                <div class="summary-card">
                    <div class="summary-num" style="color: #059669;">{{ $totalAssigned }}</div>
                    <div class="summary-lbl">Codes Assigned</div>
                </div>
            </td>
            <td style="width: 36%;">
                <div class="summary-card">
                    <div class="summary-num" style="color: #4f46e5;">{{ number_format($totalStudents) }}</div>
                    <div class="summary-lbl">Total Registered Students</div>
                </div>
            </td>
        </tr>
    </table>

    <table class="table">
        <thead>
            <tr>
                <th style="width: 4%;" class="text-center">#</th>
                <th style="width: 14%;">School Code</th>
                <th style="width: 8%;" class="text-center">No.</th>
                <th style="width: 10%;">Short Code</th>
                <th style="width: 38%;">School Name</th>
                <th style="width: 14%;">CBSE Affiliation</th>
                <th style="width: 12%;" class="text-right">Students</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
            <tr>
                <td class="text-center" style="color: #64748b;">{{ $r['sl_no'] }}</td>
                <td>
                    @if($r['code'] !== '—')
                        <span class="code-badge">{{ $r['code'] }}</span>
                    @else
                        <span style="color: #94a3b8;">—</span>
                    @endif
                </td>
                <td class="text-center font-bold" style="color: #334155;">{{ $r['school_no'] }}</td>
                <td style="font-family: monospace; color: #475569;">{{ $r['prefix'] }}</td>
                <td>
                    <strong>{{ $r['name'] }}</strong>
                    @if($r['email'] !== '—')
                        <div style="font-size: 8px; color: #64748b;">{{ $r['email'] }}</div>
                    @endif
                </td>
                <td style="font-family: monospace; color: #334155;">{{ $r['affiliation'] }}</td>
                <td class="text-right font-bold" style="color: #0f3d7a;">{{ number_format($r['students_count']) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center" style="padding: 16px; color: #94a3b8;">
                    No approved member schools found.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        <table style="width: 100%;">
            <tr>
                <td>{{ $sahodaya->name }} · School Code Assignment Register</td>
                <td class="text-right">Page 1 of 1</td>
            </tr>
        </table>
    </div>
</body>
</html>
