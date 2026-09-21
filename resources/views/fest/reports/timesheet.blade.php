<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Timesheet — {{ $event->title }}</title>
    <style>
        /* Same repeating header/footer split as fest.reports.attendance-sheet — see that
           file's own comment for the full reasoning (Chromium's native header/footer vs
           dompdf's baked-in thead/tfoot fallback). */
        {{-- Chromium (PDF_CONVERTER_URL) ignores this @page rule -- see the matching
             comment in fest.reports.attendance-sheet for why bumping it is safe here. --}}
        @page {
            margin: 116px 38px 24px;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11.5px;
            color: #0f172a;
            margin: 0;
            padding: @if(!empty($isPreview)) 24px 38px @else 0 @endif;
        }
        .report-header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 6px;
            margin-bottom: 12px;
        }
        .event-context-bar {
            display: table;
            width: 100%;
            font-size: 10.5px;
            color: #334155;
            margin-top: 2px;
        }
        .footer-container {
            display: table;
            width: 100%;
            border-top: 1px solid #cbd5e1;
            padding-top: 4px;
            margin-top: 16px;
            font-size: 9.5px;
            color: #64748b;
        }
        .footer-container .footer-left {
            display: table-cell;
            text-align: left;
            vertical-align: top;
        }
        main {
            width: 100%;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }
        thead {
            display: table-header-group;
        }
        tfoot {
            display: table-footer-group;
        }
        th {
            background: #f1f5f9;
            color: #334155;
            font-size: 10.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }
        td {
            padding: 10px 7px;
            border: 1px solid #cbd5e1;
            font-size: 10.5px;
            color: #1e293b;
            vertical-align: middle;
        }
        tr:nth-child(even) td {
            background: #f8fafc;
        }
        .text-center {
            text-align: center;
        }
        .chest-no {
            font-weight: bold;
            color: #0f172a;
            font-size: 11.5px;
        }
        .team-divider td {
            background: #f1f5f9;
            color: #0f172a;
            font-weight: bold;
            font-size: 10.5px;
            padding: 4px 6px;
            border-top: 2px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .team-divider {
            page-break-inside: avoid;
        }
        .team-tag {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            font-size: 8.5px;
            font-weight: bold;
            padding: 1px 4px;
            border-radius: 3px;
            margin-top: 1px;
        }
        .item-heading-bar {
            background: #0f172a;
            color: #ffffff;
            padding: 6px 10px;
            font-size: 12px;
            font-weight: bold;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 0;
            page-break-after: avoid;
        }
        .item-heading-bar .count-badge {
            float: right;
            background: #334155;
            color: #f8fafc;
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 10px;
            font-weight: normal;
            text-transform: none;
            letter-spacing: normal;
        }
        .brand-cell-table {
            width: 100%;
            border-collapse: collapse;
        }
        .brand-cell-table td {
            border: none;
            padding: 0;
        }
        .brand-cell-table .logo-cell {
            width: 50px;
            vertical-align: middle;
            padding-right: 10px;
        }
        .brand-cell-table .logo-cell img {
            width: 42px;
            height: 42px;
            object-fit: contain;
        }
        .brand-cell-table .org-cell {
            vertical-align: middle;
        }
        .brand-cell-table .org-name {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
        }
        .brand-cell-table .org-context {
            font-size: 11px;
            font-weight: 600;
            color: #475569;
            margin-top: 3px;
        }
        .brand-cell-table .doc-badge-cell {
            text-align: right;
            vertical-align: middle;
            white-space: nowrap;
        }
        .brand-cell-table .doc-badge {
            display: inline-block;
            background: #0f172a;
            color: #ffffff;
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.3px;
        }
    </style>
</head>
<body>
    @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])

{{-- Position:fixed on the dompdf fallback so it repeats every page -- see the matching
     comment in fest.reports.attendance-sheet. Left in normal flow on the Chromium path,
     which already gets its own native repeating header. --}}
<div class="report-header" @if($isDomPdf ?? true) style="position: fixed; top: -100px; left: 0; right: 0;" @endif>
    @include('partials.pdf-branding-header', [
        'orgName' => $sahodaya->name ?? 'SAHODAYA',
        'logoSrc' => $logo ?? null,
        'docTitle' => 'TIMESHEET',
    ])
    <div class="event-context-bar" style="margin-top: 4px; padding-top: 4px; border-top: 1px solid #e2e8f0; display: block;">
        <div style="font-size: 9px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.3px;">
            {{ $event->title }}
        </div>
        @if(!empty($singleItemMetaStr))
            <div style="font-size: 12px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                {!! $singleItemMetaStr !!}
            </div>
        @elseif(!empty($singleItemName))
            <div style="font-size: 12px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                {{ $singleItemName }}
            </div>
        @endif
    </div>
</div>

<main>
@php
    // Same per-page chunking as fest.reports.attendance-sheet — see that file's comment.
    $reportSections = collect($rowsByItem)->flatMap(function ($itemRows, $itemName) use ($isPreview, $isDomPdf) {
        $allRows = collect($itemRows)->values();
        $chunks = empty($isPreview) && ($isDomPdf ?? true)
            ? $allRows->chunk(20)->values()
            : collect([$allRows]);

        if ($chunks->count() > 1 && $chunks->last()->count() < 4) {
            $lastIndex = $chunks->count() - 1;
            $previous = $chunks->get($lastIndex - 1)->values();
            $last = $chunks->get($lastIndex)->values();
            $moved = $previous->splice(-1 * (4 - $last->count()));

            $chunks->put($lastIndex - 1, $previous->values());
            $chunks->put($lastIndex, $moved->concat($last)->values());
        }

        $offset = 0;

        return $chunks->values()->map(function ($chunk) use ($itemName, $allRows, &$offset) {
            $section = [
                'itemName' => $itemName,
                'rows' => $chunk->values()->all(),
                'allRows' => $allRows->all(),
                'offset' => $offset,
            ];
            $offset += $chunk->count();

            return $section;
        });
    })->values();
@endphp
@forelse($reportSections as $sectionIndex => $section)
    @php
        $itemName = $section['itemName'];
        $rows = $section['rows'];
        $allRows = $section['allRows'];
        $serialOffset = $section['offset'];
        $cleanTitle = str_replace('_', ' ', $itemName);

        $teamKeys = collect($allRows)->map(function ($r) {
            if (!empty($r['group_id'])) {
                return 'g_'.$r['group_id'];
            }
            if (!empty($r['team_name'])) {
                return 't_'.$r['team_name'].'_'.($r['school'] ?? '');
            }
            return null;
        })->filter()->unique();
        $teamCount = $teamKeys->count();
        $individualCount = collect($allRows)->filter(fn ($r) => empty($r['group_id']) && empty($r['team_name']))->count();

        if ($teamCount > 0 && $individualCount === 0) {
            $countLabel = $teamCount.' '.($teamCount === 1 ? 'Team' : 'Teams');
        } elseif ($teamCount > 0) {
            $countLabel = $teamCount.' '.($teamCount === 1 ? 'Team' : 'Teams').' &middot; '.count($allRows).' '.(count($allRows) === 1 ? 'Participant' : 'Participants');
        } else {
            $countLabel = count($allRows).' '.(count($allRows) === 1 ? 'Participant' : 'Participants');
        }
    @endphp
    <div style="margin-bottom: 18px; @if(empty($isPreview) && ($isDomPdf ?? true) && $sectionIndex > 0) page-break-before: always; @endif">
        @php
            $firstRow = $rows[0] ?? null;
            $catLabel = $firstRow['item_category'] ?? null;
            $typeLabel = $firstRow['item_type'] ?? null;
            $genderLabel = $firstRow['item_gender'] ?? null;
            $metaBadges = array_filter([$catLabel, $typeLabel, $genderLabel]);
        @endphp
        @if(!empty($isPreview) || ($isDomPdf ?? true) || empty($singleItemName))
        <div class="item-heading-bar">
            <span>{{ $cleanTitle }}</span>
            @if(!empty($metaBadges))
                <span style="font-weight: normal; font-size: 10px; color: #94a3b8; margin-left: 8px; text-transform: none;">
                    • {{ implode(' • ', $metaBadges) }}
                </span>
            @endif
            <span class="count-badge">{!! $countLabel !!}</span>
        </div>
        @endif
        <table>
            <thead>
                @if($rowsByItem->count() > 1)
                {{-- Backstop for natural page overflow, and needed at all only when this
                     document covers more than one item -- see the matching comment in
                     fest.reports.attendance-sheet. --}}
                <tr class="item-context-row">
                    <th colspan="7" style="background: #ffffff; color: #0f172a; border: none; padding: 0 0 6px; font-weight: bold; font-size: 11px; text-transform: none; letter-spacing: normal;">
                        {{ implode(' · ', array_filter([$cleanTitle, ...$metaBadges])) }}
                    </th>
                </tr>
                @endif
                <tr>
                    <th style="width: 32px;" class="text-center">Sl.No.</th>
                    <th style="width: 60px;" class="text-center">Chest</th>
                    <th style="width: 60px;" class="text-center">Fest ID</th>
                    <th>Name of Participant</th>
                    <th style="width: 90px;" class="text-center">Starting</th>
                    <th style="width: 90px;" class="text-center">Finishing</th>
                    <th style="width: 100px;" class="text-center">Signature</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $lastTeamKey = null;
                @endphp
                @foreach($rows as $i => $row)
                    @php
                        $teamName = $row['team_name'] ?? null;
                        $school = $row['school'] ?? '';
                        $groupId = $row['group_id'] ?? null;
                        $currentTeamKey = $groupId
                            ? 'g_'.$groupId
                            : ($teamName ? 't_'.$teamName.'_'.$school : null);
                    @endphp

                    @if($currentTeamKey && $currentTeamKey !== $lastTeamKey)
                        @php $lastTeamKey = $currentTeamKey; @endphp
                        <tr class="team-divider">
                            <td colspan="7">
                                <strong>TEAM: {{ strtoupper($teamName ?? 'Team Entry') }}</strong>
                                @if(!empty($school))
                                    &bull; <span style="color: #475569;">{{ strtoupper($school) }}</span>
                                @endif
                            </td>
                        </tr>
                    @endif

                    <tr>
                        <td class="text-center">{{ $serialOffset + $i + 1 }}</td>
                        <td class="text-center chest-no">{{ $row['reference'] ?? '—' }}</td>
                        <td class="text-center chest-no">{{ $row['fest_id'] ?? '—' }}</td>
                        <td>
                            <strong style="font-size: 9px;">{{ $row['name'] ?? '' }}</strong>
                            @if(!empty($row['team_name']))
                                <div><span class="team-tag">Team: {{ $row['team_name'] }}</span></div>
                            @endif
                        </td>
                        <td class="text-center"></td>
                        <td class="text-center"></td>
                        <td class="text-center"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @if(empty($isPreview) && ($isDomPdf ?? true))
        <div class="footer-container" style="margin-top: 7px;">
            <span class="footer-left">{{ $sahodaya->name ?? 'SAHODAYA' }} &bull; {{ $event->title }} &bull; Generated {{ now()->format('d M Y, h:i A') }}</span>
        </div>
        @endif
    </div>
@empty
    <p style="text-align: center; margin-top: 40px; color: #64748b;">No participants to display.</p>
@endforelse
</main>

</body>
</html>
