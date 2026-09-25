<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — {{ $event->title }} — Rank Tally</title>
    <style>
        @page { margin: 22px 28px; margin-bottom: 24px; }
        body { font-family: 'DejaVu Sans', system-ui, sans-serif; font-size: 10.5px; color: #0f172a; margin: 0; }
        h1 { font-size: 15px; font-weight: 800; color: #0f172a; margin: 4px 0 2px; text-align: center; }
        h2 { font-size: 11.5px; font-weight: 800; color: #1d3557; margin: 18px 0 6px; text-transform: uppercase; letter-spacing: 0.05em; }
        .subtitle { text-align: center; font-size: 10px; color: #64748b; margin: 0 0 12px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #1d3557; color: #ffffff; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; text-align: center; padding: 6px 5px; border: 1px solid #1d3557; }
        table.data th.left, table.data td.left { text-align: left; }
        table.data td { border: 1px solid #cbd5e1; padding: 6px 5px; font-size: 10px; text-align: center; }
        table.data tr:nth-child(even) td { background: #f7f9fc; }
        table.data td.item { font-weight: 700; }
        table.data td.rank { font-weight: 800; }
        table.data td.certs { font-weight: 800; color: #b45309; }
        table.data tr.total td { background: #c8d6ea !important; color: #1d3557; font-weight: 800; border-top: 2px solid #1d3557; }
        .note { font-size: 8.5px; color: #64748b; margin: 4px 0 0; }
        .empty { text-align: center; padding: 20px; color: #64748b; }
    </style>
</head>
<body>
    @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
    @include('partials.pdf-branding-header', [
        'orgName' => $orgName ?? 'Sahodaya',
        'logoSrc' => $logoSrc ?? null,
        'docTitle' => 'Rank Tally',
    ])
    <h1>{{ $event->title }}</h1>
    <div class="subtitle">Rank tally — merit places by item</div>

    @if(count($rows))
        <table class="data">
            <thead>
                <tr>
                    <th style="width: 26px;">Sl</th>
                    <th class="left">Item</th>
                    <th class="left">Category</th>
                    <th>Type</th>
                    <th>Entries</th>
                    <th>Rank 1</th>
                    <th>Rank 2</th>
                    <th>Rank 3</th>
                    <th>Total placed</th>
                    <th>Winner certificates</th>
                    <th>Projected (top 3)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $i => $row)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td class="left item">{{ $row['title'] }}</td>
                        <td class="left">{{ $row['category_label'] }}</td>
                        <td>{{ $row['is_team'] ? 'Team' : 'Individual' }}</td>
                        <td>
                            @if($row['is_team'])
                                {{ $row['entry_count'] }} teams<br><span style="color:#64748b;font-size:8.5px;">{{ $row['member_count'] }} members</span>
                            @else
                                {{ $row['entry_count'] }}
                            @endif
                        </td>
                        <td class="rank">{{ $row['rank_1'] }}</td>
                        <td class="rank">{{ $row['rank_2'] }}</td>
                        <td class="rank">{{ $row['rank_3'] }}</td>
                        <td class="rank">{{ $row['rank_1'] + $row['rank_2'] + $row['rank_3'] }}</td>
                        <td class="certs">{{ $row['winner_certs'] }}</td>
                        <td>{{ $row['projected_winner_certs'] }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td></td>
                    <td class="left" colspan="4">All items ({{ $totals['items'] }})</td>
                    <td>{{ $totals['rank_1'] }}</td>
                    <td>{{ $totals['rank_2'] }}</td>
                    <td>{{ $totals['rank_3'] }}</td>
                    <td>{{ $totals['rank_1'] + $totals['rank_2'] + $totals['rank_3'] }}</td>
                    <td>{{ $totals['winner_certs'] }}</td>
                    <td>{{ $totals['projected_winner_certs'] }}</td>
                </tr>
            </tbody>
        </table>
        <p class="note">Rank counts: individual items count people, team items count teams (a team shares one place); ties give several entries the same place. Winner certificates are per person, so a team's members each get one. Projected (top 3) is a planning estimate from registered entries, for before marks are entered.</p>

        <h2>Summary — rank 1 / 2 / 3 by category</h2>
        <table class="data">
            <thead>
                <tr>
                    <th class="left">Category</th>
                    <th>Items</th>
                    <th>Rank 1</th>
                    <th>Rank 2</th>
                    <th>Rank 3</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($summary as $row)
                    <tr>
                        <td class="left item">{{ $row['category'] }}</td>
                        <td>{{ $row['items'] }}</td>
                        <td class="rank">{{ $row['rank_1'] }}</td>
                        <td class="rank">{{ $row['rank_2'] }}</td>
                        <td class="rank">{{ $row['rank_3'] }}</td>
                        <td class="rank">{{ $row['total'] }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td class="left">All categories</td>
                    <td>{{ $totals['items'] }}</td>
                    <td>{{ $totals['rank_1'] }}</td>
                    <td>{{ $totals['rank_2'] }}</td>
                    <td>{{ $totals['rank_3'] }}</td>
                    <td>{{ $totals['rank_1'] + $totals['rank_2'] + $totals['rank_3'] }}</td>
                </tr>
            </tbody>
        </table>
    @else
        <div class="empty">No approved entries yet for this event.</div>
    @endif
</body>
</html>
