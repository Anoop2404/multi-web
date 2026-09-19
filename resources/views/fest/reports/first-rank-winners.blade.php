<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — {{ $event->title }} — 1st Rank Winners</title>
    <style>
        @page { margin: 22px 28px; }
        body { font-family: 'DejaVu Sans', system-ui, sans-serif; font-size: 11px; color: #0f172a; margin: 0; }
        h1 { font-size: 15px; font-weight: 800; color: #0f172a; margin: 4px 0 2px; text-align: center; }
        .subtitle { text-align: center; font-size: 10px; color: #64748b; margin: 0 0 12px; text-transform: uppercase; letter-spacing: 0.06em; font-weight: 700; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #b45309; color: #ffffff; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; text-align: left; padding: 7px 8px; border: 1px solid #b45309; }
        table.data td { border: 1px solid #fde68a; padding: 7px 8px; font-size: 10.5px; vertical-align: middle; }
        table.data tr:nth-child(even) td { background: #fffbeb; }
        .name { font-weight: 700; }
        .head-cell { font-size: 9px; color: #92400e; text-transform: uppercase; }
        .footer { margin-top: 14px; padding-top: 6px; border-top: 1px solid #cbd5e1; font-size: 8.5px; color: #64748b; }
        .empty { text-align: center; padding: 20px; color: #64748b; }
    </style>
</head>
<body>
    @include('partials.pdf-branding-header', [
        'orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'),
        'logoSrc' => $logoSrc ?? null,
        'docTitle' => '1st Rank Winners',
    ])
    <h1>{{ $event->title }}</h1>
    <div class="subtitle">🥇 1st Rank Winners — All Items</div>

    @if(count($rows))
        <table class="data">
            <thead>
                <tr>
                    <th>Item</th>
                    <th style="width: 100px;">Category</th>
                    <th style="width: 70px;">Type</th>
                    <th style="width: 60px;">Gender</th>
                    <th>Winner / Team</th>
                    <th>School</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                <tr>
                    <td>{{ $row['item'] }}</td>
                    <td class="head-cell">{{ $row['category_label'] ?? '—' }}</td>
                    <td class="head-cell">{{ $row['type_label'] ?? '—' }}</td>
                    <td class="head-cell">{{ $row['gender_label'] ?? '—' }}</td>
                    <td class="name">{{ $row['name'] ?? '' }}</td>
                    <td>{{ strtoupper($row['school'] ?? '') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">No 1st rank results recorded yet for any item.</div>
    @endif

    <div class="footer">{{ $orgName ?? ($sahodaya->name ?? 'Sahodaya') }} &bull; {{ $event->title }} &bull; Generated {{ now()->format('d M Y, h:i A') }}</div>
</body>
</html>
