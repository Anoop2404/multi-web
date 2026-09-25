<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $event->title }} — Item-wise Report</title>
    <style>
        @page {
            margin: 25px 30px 25px 30px;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #1e293b;
            line-height: 1.4;
        }
        .page-break {
            page-break-before: always;
        }
        .item-header {
            background-color: #0f172a;
            color: #ffffff;
            padding: 8px 12px;
            border-radius: 4px;
            margin-top: 8px;
            margin-bottom: 10px;
        }
        .item-title {
            font-size: 14px;
            font-weight: bold;
        }
        .item-meta {
            font-size: 10px;
            color: #cbd5e1;
            margin-top: 3px;
        }
        .meta {
            text-align: center;
            color: #64748b;
            margin-bottom: 4px;
            font-size: 10px;
        }
        .meta strong { color: #334155; }
        .for-whom {
            text-align: center;
            color: #334155;
            margin-bottom: 10px;
            font-size: 10px;
            font-style: italic;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        th {
            background-color: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }
        td {
            border: 1px solid #e2e8f0;
            padding: 5px 6px;
            font-size: 10px;
            color: #334155;
            vertical-align: middle;
        }
        tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-center { text-align: center; }
        .c-idx { color: #94a3b8; }
        .c-mono { font-family: monospace; }
        .c-cap { text-transform: capitalize; }
        tr.school-row td {
            background-color: #e2e8f0;
            color: #0f172a;
            font-weight: bold;
            font-size: 10px;
            padding: 4px 6px;
            letter-spacing: 0.3px;
        }
        .school-count { font-weight: normal; color: #475569; }
        .font-bold { font-weight: bold; }
        .footer {
            margin-top: 12px;
            text-align: right;
            color: #94a3b8;
            font-size: 9px;
        }
    </style>
</head>
<body>
    @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
    @php
        $groupedByItem = collect($rows)->groupBy('item_id');
    @endphp

    @forelse($groupedByItem as $itemId => $itemRows)
        @php
            $firstRow = $itemRows->first();
            // Individual, or Group (a team/pair/trio keeps its own name in brackets).
            $typeLabel = $firstRow['type_label'] ?? 'Individual';
            $typeText = $typeLabel === 'Individual' ? 'Individual' : ($typeLabel === 'Group' ? 'Group' : 'Group ('.$typeLabel.')');
        @endphp
        @if(!$loop->first)
            <div class="page-break"></div>
        @endif

        {{-- The logo is a base64 data URI (hundreds of KB) and this header repeats once per ITEM
             -- a big event has hundreds of items, so embedding it every time built a ~98 MB
             HTML string and exhausted memory before the PDF renderer even started. Org name
             still heads every item; the logo only the first. --}}
        @include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $loop->first ? ($logoSrc ?? null) : null])

        <div class="item-header">
            <div class="item-title">
                {{ $firstRow['item_title'] }}
                @if(!empty($firstRow['item_code']))
                    <span style="font-size: 12px; font-weight: normal; opacity: 0.9;">({{ $firstRow['item_code'] }})</span>
                @endif
            </div>
            <div class="item-meta">
                Category: <strong>{{ $firstRow['category_label'] }}</strong>
                @if(!empty($firstRow['gender_label']))
                    &nbsp;|&nbsp; Gender: <strong>{{ $firstRow['gender_label'] }}</strong>
                @endif
                &nbsp;|&nbsp; Type: <strong>{{ $typeText }}</strong>
                @if(!empty($firstRow['phase_name']))
                    &nbsp;|&nbsp; Phase: <strong>{{ $firstRow['phase_name'] }}</strong>
                @endif
                @if(!empty($firstRow['region_name']))
                    &nbsp;|&nbsp; Region: <strong>{{ $firstRow['region_name'] }}</strong>
                @endif
            </div>
        </div>

        @if($forWhom && $loop->first)
            <p class="for-whom">Prepared for: {{ $forWhom }}</p>
        @endif

        @php
            // false = a plain participant list with no Grade/Rank/Score columns (print option).
            $showMarks = $showMarks ?? true;
            $cols = $showMarks ? 8 : 5;
            // Grouped by school inside each item: a school heading row, then that school's
            // participants (already in name order), so a school's entrants read together.
            $bySchool = $itemRows->groupBy(fn ($r) => strtoupper($r['school_name'] ?? '—'))->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);
            $n = 0;
        @endphp
        <table>
            <thead>
                <tr>
                    <th style="width: {{ $showMarks ? 5 : 6 }}%;">#</th>
                    <th style="width: {{ $showMarks ? 35 : 46 }}%;">Participant</th>
                    <th style="width: {{ $showMarks ? 13 : 20 }}%;" class="text-center">Reg No</th>
                    <th style="width: {{ $showMarks ? 9 : 14 }}%;" class="text-center">Chest</th>
                    <th style="width: {{ $showMarks ? 10 : 14 }}%;" class="text-center">Status</th>
                    @if($showMarks)
                        <th style="width: 8%;" class="text-center">Grade</th>
                        <th style="width: 8%;" class="text-center">Rank</th>
                        <th style="width: 12%;" class="text-center">{{ ($showPoints ?? false) ? 'Points' : 'Score' }}</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($bySchool as $schoolName => $schoolRows)
                <tr class="school-row">
                    <td colspan="{{ $cols }}">{{ $schoolName }} <span class="school-count">&middot; {{ $schoolRows->count() }}</span></td>
                </tr>
                @foreach($schoolRows as $r)
                @php $n++; @endphp
                <tr>
                    <td class="text-center c-idx">{{ $n }}</td>
                    <td><strong>{{ $r['participant'] }}</strong></td>
                    <td class="text-center c-mono">{{ $r['reg_no'] ?? '—' }}</td>
                    <td class="text-center font-bold">{{ $r['chest_no'] ?? '—' }}</td>
                    <td class="text-center c-cap">{{ $r['status'] }}</td>
                    @if($showMarks)
                        <td class="text-center font-bold">{{ $r['grade'] ?? '—' }}</td>
                        <td class="text-center">{{ $r['position'] ?? '—' }}</td>
                        <td class="text-center">{{ (($showPoints ?? false) ? $r['points'] : $r['score']) ?? '—' }}</td>
                    @endif
                </tr>
                @endforeach
                @endforeach
            </tbody>
        </table>

        <p class="footer">{{ count($itemRows) }} participant(s) &middot; {{ $event->title }} &middot; {{ $generatedAt }}</p>
    @empty
        @include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])
        <h2>{{ $event->title }} — Mark Entry Report</h2>
        <p class="meta">Generated on <strong>{{ $generatedAt }}</strong></p>
        <table>
            <tbody>
                <tr><td colspan="9" class="text-center" style="padding: 20px; color: #94a3b8;">No registrations found for the selected filter.</td></tr>
            </tbody>
        </table>
    @endforelse
</body>
</html>
