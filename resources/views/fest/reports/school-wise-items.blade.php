<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $event->title }} — School-wise participants</title>
    <style>
        @page {
            {{-- With the Chromium header/footer template the converter honours this rule, so the
                 top/bottom margins must leave room for that header (~24mm) and the page-number
                 footer; otherwise the body starts at the very top and the header is drawn over it. --}}
            margin: {{ (($isDomPdf ?? true) || ($preview ?? false)) ? '25px 30px 25px 30px' : '32mm 10mm 14mm 10mm' }};
        }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10.5px; color: #1e293b; line-height: 1.4; }
        h2 { text-align: center; margin: 8px 0 2px; font-size: 15px; color: #0f172a; }
        .meta { text-align: center; color: #64748b; margin-bottom: 10px; font-size: 10px; }
        .school-heading { background-color: #0f172a; color: #ffffff; padding: 8px 12px; border-radius: 4px; margin: 0 0 8px; }
        .school-name { font-size: 14px; font-weight: bold; }
        .school-sub { font-size: 10px; color: #cbd5e1; margin-top: 2px; }
        tr.item-row td { background-color: #e2e8f0; padding: 4px 8px; border-color: #cbd5e1; page-break-after: avoid; break-after: avoid; }
        .item-title { font-size: 10.5px; font-weight: bold; color: #0f172a; }
        .item-meta { font-weight: normal; color: #475569; font-size: 9.5px; }
        table { width: 100%; border-collapse: collapse; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background-color: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 4px 6px; font-size: 9px; text-transform: uppercase; }
        td { border: 1px solid #e2e8f0; padding: 4px 6px; font-size: 10px; vertical-align: middle; }
        .c { text-align: center; }
        .idx { color: #94a3b8; }
        .mono { font-family: monospace; }
        .cap { text-transform: capitalize; }
    </style>
</head>
<body>
    {{-- In-page title/branding only where the Chromium header/footer isn't used (local dev /
         dompdf / on-screen preview); the converter path repeats them on every page itself. --}}
    @if(($isDomPdf ?? true) || ($preview ?? false))
        @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
        @include('partials.pdf-branding-header', ['orgName' => $orgName ?? 'Sahodaya', 'logoSrc' => $logoSrc ?? null])
        <h2>School-wise participants</h2>
        <div class="meta">{{ $event->title }}</div>
    @endif

    @php
        $showMarks = $showMarks ?? true;
        $cols = $showMarks ? 7 : 4;
        $schools = collect($rows)->groupBy(fn ($r) => strtoupper($r['school_name'] ?? '—'))->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);
    @endphp

    @forelse($schools as $schoolName => $schoolRows)
        @php
            $items = $schoolRows->groupBy('item_id');
        @endphp
        <div class="school-heading" @if(!$loop->first) style="page-break-before: always;" @endif>
            <div class="school-name">{{ $schoolName }}</div>
            <div class="school-sub">{{ $schoolRows->count() }} participant(s) &middot; {{ $items->count() }} item(s)</div>
        </div>

        {{-- One table per school with a single (repeating) header; each item is a sub-heading row
             followed by its participants -- an item with one entrant no longer costs a whole
             heading + table header of its own. --}}
        @php $n = 0; @endphp
        <table>
            <thead>
                <tr>
                    <th style="width: 6%;">#</th>
                    <th style="width: {{ $showMarks ? 40 : 56 }}%;">Participant</th>
                    <th style="width: {{ $showMarks ? 18 : 24 }}%;" class="c">Reg No</th>
                    <th style="width: {{ $showMarks ? 12 : 14 }}%;" class="c">Status</th>
                    @if($showMarks)
                        <th style="width: 8%;" class="c">Grade</th>
                        <th style="width: 8%;" class="c">Rank</th>
                        <th style="width: 8%;" class="c">Score</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @foreach($items as $itemRows)
                    @php
                        $first = $itemRows->first();
                        $typeLabel = $first['type_label'] ?? 'Individual';
                        $typeText = $typeLabel === 'Individual' ? 'Individual' : ($typeLabel === 'Group' ? 'Group' : 'Group ('.$typeLabel.')');
                    @endphp
                    <tr class="item-row">
                        <td colspan="{{ $cols }}">
                            <span class="item-title">{{ $first['item_title'] }}@if(!empty($first['item_code'])) ({{ $first['item_code'] }})@endif</span>
                            <span class="item-meta">
                                &nbsp;|&nbsp; {{ $first['category_label'] }}
                                @if(!empty($first['gender_label'])) &nbsp;|&nbsp; {{ $first['gender_label'] }} @endif
                                &nbsp;|&nbsp; {{ $typeText }}
                                @if(!empty($first['phase_name'])) &nbsp;|&nbsp; {{ $first['phase_name'] }} @endif
                                @if(!empty($first['region_name'])) &nbsp;|&nbsp; {{ $first['region_name'] }} @endif
                                &nbsp;|&nbsp; {{ $itemRows->count() }}
                            </span>
                        </td>
                    </tr>
                    @foreach($itemRows as $r)
                        @php $n++; @endphp
                        <tr>
                            <td class="c idx">{{ $n }}</td>
                            <td><strong>{{ $r['participant'] }}</strong></td>
                            <td class="c mono">{{ $r['reg_no'] ?? '—' }}</td>
                            <td class="c cap">{{ $r['status'] }}</td>
                            @if($showMarks)
                                <td class="c"><strong>{{ $r['grade'] ?? '—' }}</strong></td>
                                <td class="c">{{ $r['position'] ?? '—' }}</td>
                                <td class="c">{{ $r['score'] ?? '—' }}</td>
                            @endif
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    @empty
        <p class="meta" style="padding: 20px;">No participants found for the selected filter.</p>
    @endforelse
</body>
</html>
