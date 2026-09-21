<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Result Declaration Sheet — {{ $event->title }}</title>
    <style>
        {{-- Top margin reserved for the fixed running header below -- must stay >= that
             header's rendered height or dompdf/Chromium will let content overlap it. --}}
        @page { margin: 108px 20px 24px; size: portrait; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11.5px; color: #1e293b; line-height: 1.4; }
        .sheet { page-break-after: always; }
        .sheet:last-child { page-break-after: avoid; }
        .table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .table th { background: #0f172a; color: #ffffff; font-size: 11px; font-weight: bold; text-transform: uppercase; text-align: left; padding: 6px 8px; border: 1px solid #0f172a; }
        .table td { border: 1px solid #cbd5e1; padding: 9px 8px; font-size: 11.5px; height: 30px; }
        .table tr:nth-child(even) { background-color: #f8fafc; }
        .center { text-align: center; }
        .sign-box { margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 12px; }
        .sign-col { float: left; width: 50%; font-size: 11px; font-weight: bold; color: #475569; }
        .clear { clear: both; }
        .published-at { margin-top: 18px; font-size: 13px; font-weight: bold; color: #0f172a; }
        {{-- Same repeating-header split as mark-entry-sheet.blade.php: org branding is
             document-wide constant so it's the one fixed element; each sheet's own item
             name/category goes in that sheet's own table <thead> instead, since that's
             scoped correctly to just the pages that ONE item's table spans. --}}
        .pdf-page-header { position: fixed; top: -98px; left: 0; right: 0; }
        {{-- Same dark item bar every other Bulk Sheets report type uses -- see
             .item-heading-bar in fest.reports.attendance-sheet and the equivalent row in
             fest.chest-numbers-print. --}}
        .item-context-row th { background: #0f172a; color: #ffffff; border: none; padding: 7px 10px; font-weight: bold; font-size: 12px; text-transform: uppercase; letter-spacing: 0.03em; }
        .item-context-row .item-meta { font-weight: normal; font-size: 10px; color: #94a3b8; text-transform: none; margin-left: 8px; }
    </style>
</head>
<body>
    @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
    <div class="pdf-page-header">
        @include('partials.pdf-report-heading', [
            'orgName' => $sahodaya->name ?? 'Sahodaya',
            'logoSrc' => $logoSrc ?? null,
            'docTitle' => 'RESULT DECLARATION SHEET',
            'eventTitle' => $event->title,
        ])
    </div>
    @foreach($sheets as $sheet)
        <div class="sheet">
            @php
                $itemTitle = ($sheet['item']->item_code ?? null) ? "[{$sheet['item']->item_code}] " . $sheet['item']->title : ($sheet['item']->title ?? null);
                $itemMetaParts = array_filter([
                    $sheet['category_label'] ?? null,
                    \App\Support\FestTeamSquadRules::isMultiPerson($sheet['item']->participant_type ?? null) ? 'Group' : 'Individual',
                    \App\Support\FestSportsAgeGroup::genderLabel($sheet['item']->gender ?? null),
                ]);
            @endphp

            <table class="table">
                <thead>
                    @if(!empty($itemTitle))
                    <tr class="item-context-row">
                        <th colspan="4">
                            {{ $itemTitle }}
                            @if(!empty($itemMetaParts))
                                <span class="item-meta">{{ implode(' · ', $itemMetaParts) }}</span>
                            @endif
                        </th>
                    </tr>
                    @endif
                    <tr>
                        <th class="center" style="width: 40px;">SL NO</th>
                        <th style="width: 140px;">CHEST NO.</th>
                        <th style="width: 140px;">POINTS</th>
                        <th style="width: 140px;">RANK</th>
                    </tr>
                </thead>
                <tbody>
                    @for($i = 1; $i <= 10; $i++)
                        <tr>
                            <td class="center" style="color: #64748b; font-weight: bold;">{{ $i }}</td>
                            <td></td>
                            <td></td>
                            <td></td>
                        </tr>
                    @endfor
                </tbody>
            </table>

            <div class="sign-box">
                <div class="sign-col" style="width: 100%;">Convenor Signature: __________________________________</div>
                <div class="clear"></div>
                <div class="published-at">Published At: ______________________________________________ (Date &amp; Time)</div>
            </div>
        </div>
    @endforeach
</body>
</html>
