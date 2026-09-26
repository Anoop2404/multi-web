<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — {{ $event->title }} — Certificate Print Report</title>
    <style>
        @page { margin: {{ ($isDomPdf ?? true) ? '22px 28px 24px 28px' : '32mm 10mm 14mm 10mm' }}; }
        body { font-family: 'DejaVu Sans', system-ui, sans-serif; font-size: 11px; color: #0f172a; margin: 0; }
        .school + .school { page-break-before: always; }
        h2 { font-size: 15px; font-weight: bold; color: #1d3557; text-transform: uppercase; margin: 0 0 4px; padding-bottom: 5px; border-bottom: 2px solid #1d3557; }
        .summary { font-size: 10.5px; color: #475569; margin: 0 0 10px; }
        h3 { font-size: 11.5px; font-weight: bold; margin: 14px 0 6px; }
        h3.printed { color: #166534; }
        h3.pending { color: #b45309; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #1d3557; color: #fff; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.03em; text-align: left; padding: 0 8px; height: 26px; border: 1px solid #1d3557; }
        td { border: 1px solid #cbd5e1; padding: 5px 8px; font-size: 10.5px; vertical-align: top; }
        td.num, th.num { width: 28px; text-align: center; padding: 5px 2px; }
        td.cls, th.cls { width: 46px; text-align: center; }
        .pending-table th { background: #92400e; border-color: #92400e; }
        .empty { color: #64748b; font-size: 10.5px; padding: 6px 0; }
        .footer { margin-top: 12px; font-size: 8.5px; color: #64748b; }
    </style>
</head>
<body>
    @if($isDomPdf ?? true)
        @include('partials.pdf-generated-footer', ['generatedAt' => null])
        @include('partials.pdf-branding-header', ['orgName' => $orgName ?? 'Sahodaya', 'logoSrc' => $logoSrc ?? null, 'docTitle' => 'Certificate Print Report'])
    @endif

    @foreach($schools as $school)
        <div class="school">
            <h2>{{ $school['name'] }}</h2>
            <div class="summary">
                {{ count($school['printed']) }} printed in this run{{ $school['earlier'] ? " · {$school['earlier']} printed earlier" : '' }} ·
                {{ count($school['pending']) }} still pending results · printed {{ optional($printedAt)->format('d M Y, h:i A') }}
            </div>

            <h3 class="printed">Printed in this run ({{ count($school['printed']) }})</h3>
            @if(count($school['printed']))
                <table>
                    <thead><tr><th class="num">#</th><th style="width: 30%;">Student</th><th class="cls">Class</th><th>Items</th></tr></thead>
                    <tbody>
                        @foreach($school['printed'] as $i => $row)
                            <tr>
                                <td class="num">{{ $i + 1 }}</td>
                                <td><strong>{{ $row['name'] }}</strong></td>
                                <td class="cls">{{ $row['class'] }}</td>
                                <td>{{ implode(', ', $row['items']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty">None.</div>
            @endif

            @if(count($school['pending']))
                <h3 class="pending">Still pending — waiting for results ({{ count($school['pending']) }})</h3>
                <table class="pending-table">
                    <thead><tr><th class="num">#</th><th style="width: 30%;">Student</th><th class="cls">Class</th><th>Items awaiting results</th></tr></thead>
                    <tbody>
                        @foreach($school['pending'] as $i => $row)
                            <tr>
                                <td class="num">{{ $i + 1 }}</td>
                                <td><strong>{{ $row['name'] }}</strong></td>
                                <td class="cls">{{ $row['class'] }}</td>
                                <td>{{ implode(', ', $row['items']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach
</body>
</html>
