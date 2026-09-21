<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Items List — {{ $event->title }}</title>
    <style>
        @page { margin: 22px 28px; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11.5px; color: #1e293b; line-height: 1.4; }
        h1 { font-size: 14px; font-weight: 800; color: #0f172a; margin: 10px 0 4px; text-align: center; }
        .meta { text-align: center; font-size: 10px; color: #64748b; margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #0f172a; color: #ffffff; font-size: 10.5px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.03em; text-align: left; padding: 6px 8px; border: 1px solid #0f172a; }
        td { border: 1px solid #cbd5e1; padding: 6px 8px; font-size: 11px; vertical-align: middle; }
        tr:nth-child(even) td { background: #f8fafc; }
        .center { text-align: center; }
    </style>
</head>
<body>
    @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
    @include('partials.pdf-branding-header', [
        'orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'),
        'logoSrc' => $logoSrc ?? null,
        'docTitle' => 'Items List',
    ])
    <h1>{{ $event->title }} — Items List</h1>
    <p class="meta">Total Items: {{ count($rows) }} &bull; Generated on {{ now()->format('d M Y, h:i A') }}</p>

    <table>
        <thead>
            <tr>
                <th class="center" style="width: 36px;">#</th>
                <th>Item Name</th>
                <th style="width: 70px;">Code</th>
                <th style="width: 70px;">Gender</th>
                <th style="width: 160px;">Category</th>
                <th style="width: 80px;">Type</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $row['title'] }}</td>
                    <td class="center">{{ $row['code'] ?? '—' }}</td>
                    <td class="center">{{ $row['gender'] }}</td>
                    <td>{{ $row['category'] ?? '—' }}</td>
                    <td class="center">{{ $row['type'] }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="center" style="padding: 16px; color: #64748b;">No items found.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
