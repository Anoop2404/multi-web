<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $orgName ?? 'Sahodaya' }} — {{ $event->title }}@if(!empty($item)) — {{ $item->title }}@endif — Chest Number List</title>
    <style>
        {{-- Chromium's preferCSSPageSize option (always on in chrome-print-server.js)
             makes it prefer this @page margin over the $margin passed via the API to
             PdfGenerator -- confirmed the hard way: this used to read 22px on the
             Chromium branch on the assumption the API margin alone would apply, which
             instead produced a header/content overlap. Kept as one fixed, generous value
             for both paths now, matching attendance-sheet.blade.php/timesheet.blade.php. --}}
        @page { margin: 120px 28px 34px; }
        body { font-family: 'DejaVu Sans', system-ui, sans-serif; font-size: 11px; color: #0f172a; margin: 0; }
        table.data { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.data th { background: #f1f5f9; color: #334155; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; text-align: left; padding: 6px 8px; border: 1px solid #cbd5e1; }
        table.data td { border: 1px solid #cbd5e1; padding: 7px 8px; font-size: 10.5px; vertical-align: middle; }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        .chest-no { font-weight: 800; font-size: 12px; color: #0f172a; }
        .footer { margin-top: 14px; padding-top: 6px; border-top: 1px solid #cbd5e1; font-size: 8.5px; color: #64748b; }
        {{-- Same dark item bar every other Bulk Sheets report type uses (see
             .item-heading-bar in fest.reports.attendance-sheet and the JUDGE/SUM badge
             in fest.reports.mark-entry-sheet) -- inside <thead> so it reprints on every
             page this table spans, same mechanism the column header row already relies
             on. --}}
        .item-bar-row th { background: #0f172a; color: #ffffff; padding: 7px 10px; font-size: 12px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.03em; border: none; }
        .item-bar-row .item-meta { font-weight: normal; font-size: 10px; color: #94a3b8; text-transform: none; margin-left: 8px; }
        .item-bar-row .count-badge { float: right; background: #334155; color: #f8fafc; font-size: 10px; padding: 2px 8px; border-radius: 10px; font-weight: normal; text-transform: none; letter-spacing: normal; }
    </style>
</head>
<body>
    {{-- On the Chromium path, the controller already supplies an equivalent repeating
         header natively (see chestNumberHeaderFooterTemplates()), which now actually
         renders correctly there -- so this in-flow copy is skipped entirely to avoid
         showing the branding twice. dompdf has no such native mechanism, so it still
         needs this, made position:fixed to repeat on every page. --}}
    @if($isDomPdf ?? true)
    <div class="pdf-page-header" style="position: fixed; top: -96px; left: 0; right: 0;">
        @include('partials.pdf-report-heading', [
            'orgName' => $orgName ?? 'Sahodaya',
            'logoSrc' => $logoSrc ?? null,
            'docTitle' => 'CHEST NUMBER LIST',
            'eventTitle' => $event->title,
            'item' => $item ?? null,
            'categoryLabel' => $itemCategory ?? null,
            'participantCount' => !empty($item) ? count($rows) : null,
        ])
    </div>
    @endif

    <table class="data">
        <thead>
            @if(!empty($item))
            @php
                $itemBarParts = array_filter([
                    $itemCategory ?? null,
                    \App\Support\FestTeamSquadRules::isMultiPerson($item->participant_type ?? null) ? 'Group' : 'Individual',
                    \App\Support\FestSportsAgeGroup::genderLabel($item->gender ?? null),
                ]);
            @endphp
            <tr class="item-bar-row">
                <th colspan="7">
                    {{ ($item->item_code ? "[{$item->item_code}] " : '').$item->title }}
                    @if(!empty($itemBarParts))
                        <span class="item-meta">{{ implode(' · ', $itemBarParts) }}</span>
                    @endif
                    <span class="count-badge">{{ count($rows) }} participant{{ count($rows) === 1 ? '' : 's' }}</span>
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
