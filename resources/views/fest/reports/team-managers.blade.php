<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>School Team Managers Report</title>
    <style>
        @page {
            margin: 30px 35px 30px 35px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.4;
        }
        h2 {
            text-align: center;
            margin: 12px 0 4px;
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
        }
        .meta {
            text-align: center;
            color: #64748b;
            margin-bottom: 16px;
            font-size: 11px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            font-size: 10.5px;
            font-weight: bold;
            text-transform: uppercase;
            text-align: left;
        }
        td {
            border: 1px solid #e2e8f0;
            padding: 10px 8px;
            font-size: 11px;
            color: #334155;
            vertical-align: top;
            line-height: 1.8;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .manager-name {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            font-weight: bold;
        }
        .manager-role {
            font-size: 9.5px;
            color: #64748b;
            display: block;
        }
        .on-file {
            color: #b45309;
        }
        .count-badge {
            display: inline-block;
            min-width: 20px;
            padding: 2px 6px;
            border-radius: 3px;
            background: #eff6ff;
            color: #1e40af;
            font-weight: bold;
        }
    </style>
</head>
<body>
    {{-- Both skipped for the Chromium/Puppeteer path (production) -- its own
         headerTemplate/footerTemplate (PdfChromeHeaderFooter::build(), passed by
         FestReportService::teamManagersPdf()) repeats branding, "Generated on", and
         page numbering on every page instead of only the first (a plain in-page
         header only ever renders once, at the top of page 1). Still included for the
         dompdf fallback and the on-screen preview, neither of which get a Chromium
         header/footer at all. --}}
    @if($isDomPdf ?? true)
        @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
        @include('partials.pdf-branding-header', ['orgName' => $orgName ?? 'Sahodaya', 'logoSrc' => $logoSrc ?? null])
    @endif

    <h2>School Team Managers</h2>
    <div class="meta">{{ $event->title }}</div>

    <table>
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 30%;">School</th>
                <th style="width: 10%;" class="text-center">Students</th>
                <th style="width: 28%;">Team Manager 1</th>
                <th style="width: 28%;">Team Manager 2</th>
            </tr>
        </thead>
        <tbody>
            @forelse($schools as $idx => $row)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <span class="font-bold">{{ strtoupper($row->school_name) }}</span>
                    </td>
                    <td class="text-center">
                        <span class="count-badge">{{ $row->unique_student_count }}</span>
                    </td>
                    <td>
                        @if($row->manager_name_1)
                            <span class="font-bold manager-name">{{ $row->manager_name_1 }}</span>
                        @endif
                        @if($row->manager_phone_1)
                            <span>📞 {{ $row->manager_phone_1 }}</span>
                        @endif
                        @if($row->manager_role_1 === 'Events Coordinator (on file)')
                            <span class="manager-role on-file">{{ $row->manager_role_1 }}</span>
                        @elseif($row->manager_role_1)
                            <span class="manager-role">{{ $row->manager_role_1 }}</span>
                        @endif
                    </td>
                    <td>
                        @if($row->manager_name_2)
                            <span class="font-bold manager-name">{{ $row->manager_name_2 }}</span>
                        @endif
                        @if($row->manager_phone_2)
                            <span>📞 {{ $row->manager_phone_2 }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center" style="padding: 20px;">No school team managers recorded.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
