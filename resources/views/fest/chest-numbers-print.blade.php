<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — {{ $event->title }}@if(!empty($item)) — {{ $item->title }}@endif — Chest Number List</title>
    <style>
        {{-- The external Chromium converter (PDF_CONVERTER_URL) supplies its own native
             repeating header/footer (see FestChestNumberController::print()) and ignores
             this @page rule entirely -- margin there comes from the $margin passed to
             PdfGenerator instead. This margin only matters for the dompdf fallback. --}}
        @page { margin: {{ ($isDomPdf ?? true) ? '132px' : '22px' }} 28px 34px; }
        body { font-family: 'DejaVu Sans', system-ui, sans-serif; font-size: 11px; color: #0f172a; margin: 0; }
        h1 { font-size: 14px; font-weight: 800; color: #0f172a; margin: 4px 0 12px; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #f1f5f9; color: #334155; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; text-align: left; padding: 6px 8px; border: 1px solid #cbd5e1; }
        table.data td { border: 1px solid #cbd5e1; padding: 7px 8px; font-size: 10.5px; vertical-align: middle; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        .chest-no { font-weight: 800; font-size: 12px; color: #0f172a; }
        .footer { margin-top: 14px; padding-top: 6px; border-top: 1px solid #cbd5e1; font-size: 8.5px; color: #64748b; }
        {{-- Item/category context repeated inside the table's own <thead> -- dompdf and
             Chromium both natively reprint <thead> on every page a table spans, which is
             the one repeat-per-page mechanism already proven reliable here (it's why the
             Chest No/Order No/... column row already survived pagination). --}}
        .item-context-row th { background: #ffffff; border: none; padding: 0 8px 8px; font-weight: normal; text-transform: none; letter-spacing: normal; }
    </style>
</head>
<body>
    <div class="pdf-page-header" @if($isDomPdf ?? true) style="position: fixed; top: -122px; left: 0; right: 0;" @endif>
        @include('partials.pdf-branding-header', [
            'orgName' => $orgName ?? 'Sahodaya',
            'logoSrc' => $logoSrc ?? null,
            'docTitle' => 'Chest Number List',
        ])
        <h1>{{ $event->title }}@if(!empty($item)) — {{ $item->title }}@endif</h1>
    </div>

    <table class="data">
        <thead>
            @if(!empty($item))
            <tr class="item-context-row">
                <th colspan="{{ empty($item) ? 8 : 7 }}">
                    <strong style="font-size: 12.5px; color: #0f172a;">{{ $item->item_code ? "[{$item->item_code}] " : '' }}{{ $item->title }}</strong>
                    @if(!empty($itemCategory))
                        &nbsp;&middot;&nbsp;{{ $itemCategory }}
                    @endif
                </th>
            </tr>
            @endif
            <tr>
                <th style="width: 70px;">Chest No</th>
                <th style="width: 60px;">Order No</th>
                <th style="width: 70px;">Fest ID</th>
                <th>Name</th>
                @if(empty($item))<th>Item</th>@endif
                <th>Category</th>
                <th>School</th>
                <th style="width: 80px; text-align: center;">Sign</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
            <tr>
                <td class="chest-no">{{ $row['chest_no'] }}</td>
                <td>{{ $row['order_no'] ?? '' }}</td>
                <td>{{ $row['fest_id'] ?? '—' }}</td>
                <td>{{ $row['name'] }}</td>
                @if(empty($item))<td>{{ $row['item'] }}</td>@endif
                <td>{{ $row['category'] ?? '—' }}</td>
                <td>{{ $row['school'] }}</td>
                <td style="width: 80px;"></td>
            </tr>
            @empty
            <tr>
                <td colspan="{{ empty($item) ? 8 : 7 }}" style="text-align: center; padding: 16px; color: #64748b;">No chest numbers assigned yet.</td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">{{ $orgName ?? 'Sahodaya' }} &bull; {{ $event->title }} &bull; Generated {{ now()->format('d M Y, h:i A') }}</div>
</body>
</html>
