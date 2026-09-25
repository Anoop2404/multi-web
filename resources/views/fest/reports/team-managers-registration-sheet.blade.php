<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Team Manager Registration Sheet</title>
    <style>
        @page {
            {{-- With the Chromium header/footer template the converter honours this rule, so the
                 top/bottom margins must leave room for that header (~24mm) and the page-number
                 footer; otherwise the body starts at the very top and the header is drawn over it. --}}
            margin: {{ (($isDomPdf ?? true) || ($preview ?? false)) ? '25px 30px 25px 30px' : '32mm 10mm 14mm 10mm' }};
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
            table-layout: fixed;
            border-collapse: collapse;
            margin-top: 10px;
        }
        {{-- Explicit table-header-group so the column headers repeat on every printed
             page for a long sign-in sheet, not just the first -- the default UA
             stylesheet already implies this for a <thead>, but both rendering engines
             (dompdf and the Chromium print pipeline) honor it more reliably stated
             outright. --}}
        thead {
            display: table-header-group;
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
            word-wrap: break-word;
        }
        {{-- A fixed row height, the same for every row regardless of how much manager
             text a school has (or how little, for a blank row) -- this is a physical
             sign-in sheet, not just a data table, so every row needs the same
             comfortable amount of blank space to write a count and sign in. --}}
        tbody tr {
            height: 62px;
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
        {{-- Left blank on purpose -- filled in by hand at the registration desk, not
             from the system's own count (see the Blade file's own docblock note in
             FestReportService::teamManagersRegistrationSheetPdf()). --}}
        .blank-box {
            display: block;
            height: 22px;
            border-bottom: 1px solid #cbd5e1;
        }
        .signature-box {
            display: block;
            height: 30px;
        }
    </style>
</head>
<body>
    @if(($isDomPdf ?? true) || ($preview ?? false))
        @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
        @include('partials.pdf-branding-header', ['orgName' => $orgName ?? 'Sahodaya', 'logoSrc' => $logoSrc ?? null])
        <h2>Team Manager Registration Sheet</h2>
        <div class="meta">{{ $event->title }}</div>
    @endif

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 26%;">School</th>
                <th style="width: 10%;" class="text-center">Students</th>
                <th style="width: 29%;">Team Manager</th>
                <th style="width: 30%;">Signature</th>
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
                        <span class="blank-box"></span>
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
                        <span class="signature-box"></span>
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
