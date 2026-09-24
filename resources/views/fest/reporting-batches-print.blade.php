<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — {{ $event->title }} — {{ $item->title }} — Reporting Batches</title>
    <style>
        @page { margin: 140px 28px 34px; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; color: #0f172a; margin: 0; }
        .batch-header { margin-top: 18px; margin-bottom: 6px; page-break-after: avoid; break-after: avoid-page; }
        .batch-header:first-of-type { margin-top: 0; }
        .batch-title { font-size: 14px; font-weight: bold; color: #0f172a; }
        .batch-meta { font-size: 10px; color: #475569; margin-top: 2px; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.data thead { display: table-header-group; }
        table.data th { background: #f1f5f9; color: #334155; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; text-align: left; padding: 6px 8px; border: 1px solid #cbd5e1; }
        table.data td { border: 1px solid #cbd5e1; padding: 7px 8px; font-size: 10.5px; vertical-align: middle; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        table.data tr { page-break-inside: avoid; break-inside: avoid; }
        .footer { margin-top: 14px; padding-top: 6px; border-top: 1px solid #cbd5e1; font-size: 8.5px; color: #64748b; }
    </style>
</head>
<body>
    @if($isDomPdf ?? true)
    <div class="pdf-page-header" style="position: fixed; top: 0; left: 0; right: 0;">
        @include('partials.pdf-report-heading', [
            'orgName' => $orgName ?? 'Sahodaya',
            'logoSrc' => $logoSrc ?? null,
            'docTitle' => 'REPORTING BATCHES',
            'eventTitle' => $event->title,
            'item' => $item,
            'categoryLabel' => $categoryLabel ?? null,
            'participantCount' => collect($sections)->sum(fn ($s) => count($s['rows'])),
        ])
    </div>
    @endif

    @include('fest.partials.reporting-batches-sections', ['sections' => $sections, 'isGroup' => $isGroup])

    <div class="footer">{{ $orgName ?? 'Sahodaya' }} &bull; {{ $event->title }} &bull; Generated {{ now()->format('d M Y, h:i A') }}</div>
</body>
</html>
