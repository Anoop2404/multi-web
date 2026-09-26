<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — {{ $event->title }} — Final Result Summary</title>
    <style>
        @page { margin: {{ ($isDomPdf ?? true) ? '22px 28px 24px 28px' : '32mm 10mm 14mm 10mm' }}; }
        body { font-family: 'DejaVu Sans', system-ui, sans-serif; font-size: 11px; color: #0f172a; margin: 0; }
        h1 { font-size: 15px; font-weight: bold; color: #0f172a; margin: 4px 0 2px; text-align: center; }
        .subtitle { text-align: center; font-size: 10px; color: #64748b; margin: 0 0 14px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: bold; }
        .section { page-break-inside: avoid; }
        .section + .section { page-break-before: always; }
        h2 { font-size: 17px; font-weight: bold; text-align: left; margin: 22px 0 14px; color: #1d3557; text-transform: uppercase; letter-spacing: 0.04em; padding-bottom: 6px; border-bottom: 2px solid #1d3557; }
        .note { font-size: 10.5px; color: #b45309; margin: -8px 0 10px; }
        table.data { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.data tr { page-break-inside: avoid; }
        table.data th { background: #1d3557; color: #ffffff; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.04em; text-align: center; padding: 0 14px; height: 40px; border: 1px solid #1d3557; }
        table.data th.school-col { text-align: left; }
        table.data td { border: 1px solid #cbd5e1; padding: 0 14px; height: 46px; font-size: 15px; text-align: center; }
        table.data td.school-col { text-align: left; font-weight: bold; }
        table.data td.total-col { font-weight: bold; font-size: 16px; }
        table.data td.rank-col { font-weight: bold; font-size: 16px; }
        table.data tr.rank-1 td { background: #fef3c7; }
        .footer { margin-top: 14px; padding-top: 6px; border-top: 1px solid #cbd5e1; font-size: 8.5px; color: #64748b; }
        .empty { text-align: center; padding: 18px; color: #64748b; font-size: 13px; }
    </style>
</head>
<body>
    @if($isDomPdf ?? true)
        @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
        @include('partials.pdf-branding-header', [
            'orgName' => $orgName ?? 'Sahodaya',
            'logoSrc' => $logoSrc ?? null,
            'docTitle' => 'Final Result Summary',
        ])
        <h1>{{ $event->title }}</h1>
        <div class="subtitle">Final Result Summary</div>
    @endif

    @php
        $sections = array_merge(
            [['label' => 'Overall — Top 3 Schools', 'excluded' => false, 'rows' => $overall, 'empty' => 'No overall results recorded yet.']],
            array_map(fn ($c) => ['label' => $c['label'].' — Top 3 Schools', 'excluded' => $c['excluded'], 'rows' => $c['rows'], 'empty' => 'No results recorded yet for this category.'], $categories),
        );
    @endphp

    @foreach($sections as $section)
        <div class="section">
            <h2>{{ $section['label'] }}</h2>
            @if($section['excluded'])
                <div class="note">Not counted toward the overall / championship total.</div>
            @endif
            @if(count($section['rows']))
                <table class="data">
                    <thead>
                        <tr>
                            <th class="school-col">School</th>
                            <th style="width: 22%;">Total</th>
                            <th style="width: 16%;">Rank</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($section['rows'] as $row)
                            <tr class="rank-{{ $row['rank'] }}">
                                <td class="school-col">{{ $row['school'] }}</td>
                                <td class="total-col">{{ $row['total'] }}</td>
                                <td class="rank-col">{{ $row['rank'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <div class="empty">{{ $section['empty'] }}</div>
            @endif
        </div>
    @endforeach

    @if($isDomPdf ?? true)
        <div class="footer">{{ $orgName ?? 'Sahodaya' }} &bull; {{ $event->title }} &bull; Generated {{ now()->format('d M Y, h:i A') }}</div>
    @endif
</body>
</html>
