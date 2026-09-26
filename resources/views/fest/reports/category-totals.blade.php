<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — {{ $event->title }} — Category-wise Totals</title>
    <style>
        @page { margin: {{ ($isDomPdf ?? true) ? '22px 28px 24px 28px' : '32mm 10mm 14mm 10mm' }}; }
        body { font-family: 'DejaVu Sans', system-ui, sans-serif; font-size: 11px; color: #0f172a; margin: 0; }
        h1 { font-size: 15px; font-weight: 800; color: #0f172a; margin: 4px 0 2px; text-align: center; }
        .subtitle { text-align: center; font-size: 10px; color: #64748b; margin: 0 0 12px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }
        table.data th { background: #1d3557; color: #ffffff; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; text-align: center; padding: 7px 6px; border: 1px solid #1d3557; }
        table.data th.school-col { text-align: left; }
        table.data td { border: 1px solid #cbd5e1; padding: 0 6px; height: 28px; font-size: 10.5px; text-align: center; }
        table.data td.school-col { text-align: left; font-weight: 700; }
        table.data tr:nth-child(even) td { background: #f7f9fc; }
        table.data td.overall-col { font-weight: bold; }
        table.data td.rank-col, table.data th.rank-col { font-weight: 800; }
        table.data tr.top3 td { font-weight: bold; background: #fef9e7; }
        .footer { margin-top: 14px; padding-top: 6px; border-top: 1px solid #cbd5e1; font-size: 8.5px; color: #64748b; }
        .empty { text-align: center; padding: 20px; color: #64748b; }
    </style>
</head>
<body>
    @if($isDomPdf ?? true)
        @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
        @include('partials.pdf-branding-header', [
            'orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'),
            'logoSrc' => $logoSrc ?? null,
            'docTitle' => 'Category-wise Totals',
        ])
        <h1>{{ $event->title }}</h1>
        <div class="subtitle">Category-wise Totals</div>
    @endif

    @if(count($rows))
        <table class="data">
            <thead>
                <tr>
                    <th class="school-col">School</th>
                    @foreach($categories as $category)
                        <th>{{ $category['label'] }}</th>
                    @endforeach
                    <th class="overall-col">Overall</th>
                    <th class="rank-col" style="width: 34px;">Rank</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                <tr class="{{ end($row) <= 3 ? 'top3' : '' }}">
                    @foreach($row as $i => $value)
                        <td class="{{ $i === 0 ? 'school-col' : ($i === count($row) - 1 ? 'rank-col' : ($i === count($row) - 2 ? 'overall-col' : '')) }}">{{ $value }}</td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">No results recorded yet for this event.</div>
    @endif

    @if($isDomPdf ?? true)
        <div class="footer">{{ $orgName ?? ($sahodaya->name ?? 'Sahodaya') }} &bull; {{ $event->title }} &bull; Generated {{ now()->format('d M Y, h:i A') }}</div>
    @endif
</body>
</html>
