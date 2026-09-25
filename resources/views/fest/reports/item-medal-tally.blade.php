<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — {{ $event->title }} — Medal Tally</title>
    <style>
        @page { size: A4 portrait; margin: 22px 26px 26px; }
        body { font-family: 'DejaVu Sans', system-ui, sans-serif; font-size: 10.5px; color: #0f172a; margin: 0; }
        h1 { font-size: 14px; font-weight: 800; margin: 4px 0 2px; text-align: center; }
        .subtitle { text-align: center; font-size: 9.5px; color: #64748b; margin: 0 0 12px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #1d3557; color: #fff; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; padding: 6px 5px; border: 1px solid #1d3557; text-align: center; }
        table.data td { border: 1px solid #cbd5e1; padding: 5px; text-align: center; }
        table.data th.left, table.data td.left { text-align: left; }
        table.data tr:nth-child(even) td { background: #f7f9fc; }
        table.data td.item { font-weight: 700; }
        table.data td.medal { font-weight: 800; width: 52px; }
        table.data td.total-col { font-weight: 800; width: 60px; background: #eef2f8; }
        .team { font-weight: 400; color: #64748b; font-size: 8.5px; }
        table.data tr.total td { background: #c8d6ea !important; color: #1d3557; font-weight: 800; border-top: 2px solid #1d3557; }
        .note { font-size: 8px; color: #64748b; margin: 5px 0 0; }
        .empty { text-align: center; padding: 20px; color: #64748b; }
    </style>
</head>
<body>
    @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
    @include('partials.pdf-branding-header', [
        'orgName' => $orgName ?? 'Sahodaya',
        'logoSrc' => $logoSrc ?? null,
        'docTitle' => 'Medal Tally',
    ])
    <h1>{{ $event->title }}</h1>
    <div class="subtitle">Medals to be given — by item</div>

    @if(count($rows))
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 24px;">Sl</th>
                    <th class="left">Item</th>
                    <th class="left">Category</th>
                    <th>Gold</th>
                    <th>Silver</th>
                    <th>Bronze</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $i => $row)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td class="left item">{{ $row['title'] }}@if($row['is_team']) <span class="team">(Team)</span>@endif</td>
                        <td class="left">{{ $row['category_label'] }}</td>
                        <td class="medal">{{ $row['medals_1'] }}</td>
                        <td class="medal">{{ $row['medals_2'] }}</td>
                        <td class="medal">{{ $row['medals_3'] }}</td>
                        <td class="total-col">{{ $row['medals_1'] + $row['medals_2'] + $row['medals_3'] }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td></td>
                    <td class="left" colspan="2">Total ({{ count($rows) }} items)</td>
                    <td>{{ $totals['medals_1'] }}</td>
                    <td>{{ $totals['medals_2'] }}</td>
                    <td>{{ $totals['medals_3'] }}</td>
                    <td>{{ $totals['medals_total'] }}</td>
                </tr>
            </tbody>
        </table>
        <p class="note">One medal per person: every member of a placed team gets one, and tied places each get one.</p>
    @else
        <div class="empty">No approved entries yet for this event.</div>
    @endif
</body>
</html>
