<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — {{ $event->title }} — {{ $categoryLabel }} Totals</title>
    <style>
        @page { margin: {{ ($isDomPdf ?? true) ? '22px 28px 24px 28px' : '32mm 10mm 14mm 10mm' }}; }
        body { font-family: 'DejaVu Sans', system-ui, sans-serif; font-size: 11px; color: #0f172a; margin: 0; }
        h1 { font-size: 15px; font-weight: 800; color: #0f172a; margin: 4px 0 2px; text-align: center; }
        .subtitle { text-align: center; font-size: 10px; color: #64748b; margin: 0 0 12px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
        table.data { width: 100%; max-width: 560px; margin: 0 auto; border-collapse: collapse; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }
        table.data th { background: #1d3557; color: #ffffff; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; text-align: center; padding: 7px 8px; border: 1px solid #1d3557; }
        table.data th.school-col { text-align: left; }
        table.data td { border: 1px solid #cbd5e1; padding: 0 8px; height: 28px; font-size: 10.5px; text-align: center; }
        table.data td.school-col { text-align: left; font-weight: 700; }
        table.data tr:nth-child(even) td { background: #f7f9fc; }
        table.data td.overall-col, table.data th.overall-col { background: #c8d6ea; color: #1d3557; font-weight: 800; }
        table.data td.rank-col, table.data th.rank-col { font-weight: 800; }
        table.data tr.top3 td { font-weight: bold; background: #fef9e7; }
        table.data tr.top3 td.overall-col { background: #f5e6a8; }
        .footer { margin-top: 14px; padding-top: 6px; border-top: 1px solid #cbd5e1; font-size: 8.5px; color: #64748b; }
        .empty { text-align: center; padding: 20px; color: #64748b; }
    </style>
</head>
<body>
    {{-- With the Chromium converter its header/footer templates repeat branding + page numbers
         on every page (PdfChromeHeaderFooter::download()), so the in-page heading is only for
         the dompdf fallback. --}}
    @if($isDomPdf ?? true)
        @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
        @include('partials.pdf-branding-header', [
            'orgName' => $orgName ?? 'Sahodaya',
            'logoSrc' => $logoSrc ?? null,
            'docTitle' => "{$categoryLabel} Totals",
        ])
        <h1>{{ $event->title }}</h1>
        <div class="subtitle">{{ $categoryLabel }} — Totals</div>
    @else
        <div class="subtitle" style="margin-top: 2px;">{{ $categoryLabel }} — Totals</div>
    @endif

    @if(count($rows))
        <table class="data">
            <thead>
                <tr>
                    <th class="school-col">School</th>
                    <th class="overall-col">Total</th>
                    <th class="rank-col" style="width: 34px;">Rank</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                <tr class="{{ $row[2] <= 3 ? 'top3' : '' }}">
                    <td class="school-col">{{ $row[0] }}</td>
                    <td class="overall-col">{{ $row[1] }}</td>
                    <td class="rank-col">{{ $row[2] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">No results recorded yet for this category.</div>
    @endif

    @if($isDomPdf ?? true)
        <div class="footer">{{ $orgName ?? 'Sahodaya' }} &bull; {{ $event->title }} &bull; Generated {{ now()->format('d M Y, h:i A') }}</div>
    @endif
</body>
</html>
