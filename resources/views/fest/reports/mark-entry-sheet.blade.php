<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Mark Entry Sheet — {{ $event->title }}</title>
    <style>
        {{-- Top margin reserved for the fixed running header below -- must stay >= that
             header's rendered height or dompdf/Chromium will let content overlap it. --}}
        @page { margin: 108px 20px 24px; size: portrait; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11.5px; color: #1e293b; line-height: 1.4; }
        .sheet { page-break-after: always; }
        .sheet:last-child { page-break-after: avoid; }
        .title { font-size: 16px; font-weight: bold; color: #0f172a; text-transform: uppercase; margin: 0; }
        .subtitle { font-size: 13px; font-weight: bold; color: #475569; margin-top: 2px; }
        .meta { margin-top: 6px; font-size: 11px; color: #475569; }
        .table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .table th { background: #0f172a; color: #ffffff; font-size: 11px; font-weight: bold; text-transform: uppercase; text-align: left; padding: 6px 8px; border: 1px solid #0f172a; }
        .table td { border: 1px solid #cbd5e1; padding: 9px 8px; font-size: 11.5px; height: 26px; }
        .table tr:nth-child(even) { background-color: #f8fafc; }
        .center { text-align: center; }
        .sign-box { margin-top: 24px; border-top: 1px solid #e2e8f0; padding-top: 12px; }
        .sign-col { float: left; width: 33%; font-size: 11px; font-weight: bold; color: #475569; }
        .clear { clear: both; }
        {{-- Only the ORG branding (logo/name/doc-title) is document-wide constant, so it's
             the only part that can be a single fixed element repeating on every page --
             position:fixed content is pinned to one place across the WHOLE document, so it
             can't vary per item the way each sheet's own item context needs to. Each
             item's own name/category (which DOES vary sheet to sheet) instead goes inside
             that sheet's own table <thead>, which dompdf/Chromium already natively repeat
             per page -- but only across the pages that ONE sheet's table spans, correctly
             scoping it to just that item. --}}
        .pdf-page-header { position: fixed; top: -98px; left: 0; right: 0; }
        .item-context-row th { background: #ffffff; color: #0f172a; border: none; padding: 4px 0 8px; font-weight: normal; text-transform: none; letter-spacing: normal; }
    </style>
</head>
<body>
    @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
    <div class="pdf-page-header">
        @include('partials.pdf-report-heading', [
            'orgName' => $sahodaya->name ?? 'Sahodaya',
            'logoSrc' => $logoSrc ?? null,
            'docTitle' => 'MARK ENTRY SHEET',
            'eventTitle' => $event->title,
        ])
    </div>
    @foreach($sheets as $sheet)
        <div class="sheet">
            @php
                $itemInfoParts = array_filter([
                    ($sheet['item']->item_code ?? null) ? "[{$sheet['item']->item_code}] " . $sheet['item']->title : ($sheet['item']->title ?? null),
                    $sheet['category_label'] ?? null,
                    \App\Support\FestTeamSquadRules::isMultiPerson($sheet['item']->participant_type ?? null) ? 'Group' : 'Individual',
                    \App\Support\FestSportsAgeGroup::genderLabel($sheet['item']->gender ?? null),
                ]);
            @endphp

            @if(!empty($sheet['is_sum_sheet']))
                {{-- Consolidated sheet: one column per judge (their paper subtotal) + Grand Total --}}
                <table class="table">
                    <thead>
                        @if(!empty($itemInfoParts))
                        <tr class="item-context-row">
                            <th colspan="{{ $sheet['judge_count'] + 3 }}">
                                @if(!empty($sheet['sheet_label']))
                                    <span style="background: #0f172a; color: #ffffff; padding: 2px 7px; border-radius: 3px; font-size: 10px; letter-spacing: 0.03em; margin-right: 6px;">{{ $sheet['sheet_label'] }}</span>
                                @endif
                                {{ implode(' · ', $itemInfoParts) }} &middot; {{ count($sheet['rows']) }} participants
                            </th>
                        </tr>
                        @endif
                        <tr>
                            <th class="center" style="width: 40px;">SL NO</th>
                            <th style="width: 90px;">CHEST NO.</th>
                            @for($j = 1; $j <= $sheet['judge_count']; $j++)
                                <th>JUDGE {{ $j }}</th>
                            @endfor
                            <th style="width: 100px;">GRAND TOTAL</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sheet['rows'] as $idx => $row)
                            <tr>
                                <td class="center" style="color: #64748b; font-weight: bold;">{{ $idx + 1 }}</td>
                                <td style="font-weight: bold; font-family: monospace; font-size: 12px; color: #0f172a;">
                                    {{ ($blankChest ?? false) ? '' : ($row['chest_no'] ? '#'.$row['chest_no'] : '—') }}
                                </td>
                                @for($j = 1; $j <= $sheet['judge_count']; $j++)
                                    <td></td>
                                @endfor
                                <td></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 3 + $sheet['judge_count'] }}" class="center" style="padding: 16px; color: #64748b;">
                                    No approved registrations for this item.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @else
                <table class="table">
                    <thead>
                        @if(!empty($itemInfoParts))
                        <tr class="item-context-row">
                            <th colspan="{{ 2 + ($sheet['criteria']->isNotEmpty() ? $sheet['criteria']->count() + 1 : 1) }}">
                                @if(!empty($sheet['sheet_label']))
                                    <span style="background: #0f172a; color: #ffffff; padding: 2px 7px; border-radius: 3px; font-size: 10px; letter-spacing: 0.03em; margin-right: 6px;">{{ $sheet['sheet_label'] }}</span>
                                @endif
                                {{ implode(' · ', $itemInfoParts) }} &middot; {{ count($sheet['rows']) }} participants
                            </th>
                        </tr>
                        @endif
                        <tr>
                            <th class="center" style="width: 40px;">SL NO</th>
                            <th style="width: 90px;">CHEST NO.</th>
                            @if($sheet['criteria']->isNotEmpty())
                                @foreach($sheet['criteria'] as $c)
                                    <th>{{ $c->label }}<br><small>/ {{ rtrim(rtrim(number_format($c->max_score, 2), '0'), '.') }}</small></th>
                                @endforeach
                                <th style="width: 90px;">TOTAL</th>
                            @else
                                <th>MARKS / SCORE</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($sheet['rows'] as $idx => $row)
                            <tr>
                                <td class="center" style="color: #64748b; font-weight: bold;">{{ $idx + 1 }}</td>
                                <td style="font-weight: bold; font-family: monospace; font-size: 12px; color: #0f172a;">
                                    {{ ($blankChest ?? false) ? '' : ($row['chest_no'] ? '#'.$row['chest_no'] : '—') }}
                                </td>
                                @if($sheet['criteria']->isNotEmpty())
                                    @foreach($sheet['criteria'] as $c)
                                        <td></td>
                                    @endforeach
                                    <td></td>
                                @else
                                    <td></td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ 2 + ($sheet['criteria']->isNotEmpty() ? $sheet['criteria']->count() + 1 : 1) }}" class="center" style="padding: 16px; color: #64748b;">
                                    No approved registrations for this item.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            @endif

            <div class="sign-box">
                @php
                    $jCount = max(1, (int) ($sheet['judge_count'] ?? 1));
                    $colsCount = !empty($sheet['is_sum_sheet']) ? ($jCount + 1) : 2;
                    $colWidth = floor(100 / max(1, $colsCount));
                @endphp
                @if(!empty($sheet['is_sum_sheet']))
                    @for($j = 1; $j <= $jCount; $j++)
                        <div class="sign-col" style="width: {{ $colWidth }}%;">Judge {{ $j }} Signature: __________________</div>
                    @endfor
                    <div class="sign-col" style="width: {{ $colWidth }}%;">Convenor Signature: __________________</div>
                @else
                    <div class="sign-col" style="width: 50%;">Judge Signature: __________________</div>
                    <div class="sign-col" style="width: 50%;">Convenor Signature: __________________</div>
                @endif
                <div class="clear"></div>
            </div>
        </div>
    @endforeach
</body>
</html>
