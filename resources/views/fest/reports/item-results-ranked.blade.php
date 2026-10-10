<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — {{ $event->title }}@if(!empty($item)) — {{ $item->title }}@endif — Results</title>
    <style>
        @page { size: A4 portrait; margin: 15mm 15mm 20mm; }
        body { font-family: 'DejaVu Sans', system-ui, sans-serif; font-size: 11px; color: #0f172a; margin: 0; }
        h1 { font-size: 14px; font-weight: 800; color: #0f172a; margin: 4px 0 12px; text-align: center; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #1d3557; color: #ffffff; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; text-align: left; padding: 6px 8px; border: 1px solid #1d3557; }
        table.data td { border: 1px solid #000; padding: 7px 8px; font-size: 10.5px; vertical-align: middle; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        table.data thead { display: table-header-group; }
        table.data tr { break-inside: avoid; page-break-inside: avoid; }
        .rank { font-weight: 800; font-size: 12px; color: #0f172a; }
        .footer { margin-top: 14px; padding-top: 6px; border-top: 1px solid #000; font-size: 8.5px; color: #64748b; }
        .published-at { margin-top: 14px; font-size: 13px; font-weight: bold; color: #0f172a; }
    </style>
</head>
<body>
    @php $isSports = $event->event_type === 'sports'; @endphp
    @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
    @include('partials.pdf-branding-header', [
        'orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'),
        'logoSrc' => $logoSrc ?? null,
        'docTitle' => 'Results — Rank Order',
    ])
    <h1>{{ $event->title }}@if(!empty($item)) — {{ $item->title }}@endif</h1>

    @include('partials.pdf-item-info-bar', ['item' => $item ?? null, 'category' => $itemCategory ?? null])

    <table class="data">
        <thead>
            <tr>
                <th style="width: 50px;">Rank</th>
                <th style="width: 70px;">Chest No</th>
                <th>Participant</th>
                <th>School</th>
                @if($isSports)
                <th style="width: 90px;">Time / Distance</th>
                @else
                <th style="width: 70px;">Grade</th>
                <th style="width: 70px;">Score</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
            <tr>
                <td class="rank">{{ $row['position'] ?? '—' }}</td>
                <td>{{ $row['chest_no'] ?? '—' }}</td>
                <td>{{ $row['name'] ?? '' }}</td>
                <td>{{ strtoupper($row['school'] ?? '') }}</td>
                @if($isSports)
                <td>{{ isset($row['measurement']) && $row['measurement'] !== '' ? trim($row['measurement'].' '.($row['measurement_unit'] ?? '')) : '—' }}</td>
                @else
                <td>{{ $row['grade'] ?? '—' }}</td>
                <td>{{ $row['score'] ?? '—' }}</td>
                @endif
            </tr>
            @empty
            <tr>
                <td colspan="{{ $isSports ? 5 : 6 }}" style="text-align: center; padding: 16px; color: #64748b;">No participants for this item.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="published-at">Published At: {{ !empty($publishedAt) ? $publishedAt->format('d M Y, h:i A') : 'Not yet published' }}</div>

    <div class="footer">{{ $orgName ?? ($sahodaya->name ?? 'Sahodaya') }} &bull; {{ $event->title }} &bull; Generated {{ now()->format('d M Y, h:i A') }}</div>
</body>
</html>
