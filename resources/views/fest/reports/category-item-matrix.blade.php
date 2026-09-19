<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Category & Item-wise Consolidated Report</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px}
table{width:100%;border-collapse:collapse;margin-top:10px}
th,td{border:1px solid #ccc;padding:3px 4px;text-align:center}
th{background:#1d3557;color:#fff;font-size:8px;font-weight:bold;white-space:nowrap}
.school-col{text-align:left;min-width:120px}
.rank-col{width:28px}
.head-col{background:#3d5a80;font-size:8px;padding:4px}
.item-col{height:210px;width:24px;min-width:24px;max-width:24px;position:relative;vertical-align:bottom;padding:0}
.item-col-inner{display:block;position:absolute;bottom:2px;left:3px;width:230px;height:14px;line-height:14px;transform:rotate(-90deg);transform-origin:bottom left;white-space:nowrap}
.subtotal-col{background:#eef2f8;font-weight:bold}
.overall-col{background:#1d3557;color:#fff;font-weight:bold}
tbody tr:nth-child(even){background:#f7f9fc}
tbody td.subtotal-col{background:#dde6f2}
tbody td.overall-col{background:#c8d6ea;color:#1d3557}
.page-block{page-break-after:always}
.page-block:last-child{page-break-after:auto}
.page-note{text-align:right;font-size:8px;color:#64748b;margin:0 0 2px}
</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }} — Category & Item-wise Consolidated Report</h2>
<p style="text-align:center;font-size:10px;color:#64748b;margin-top:2px">Generated on {{ now()->format('d M Y, h:i A') }}</p>
@php
$excludedLabels = collect($pages)->flatMap(fn ($p) => $p['categories'])->where('excluded_from_overall', true)->pluck('label')->unique();
$itemGenderLabel = fn ($g) => (! $g || $g === 'open') ? 'Mixed' : (['male' => 'Boys', 'female' => 'Girls'][$g] ?? ucfirst($g));
$itemTypeAbbr = fn ($t) => in_array($t, ['team', 'group', 'pair', 'trio'], true) ? 'Grp' : 'Ind';
$itemHeaderLabel = fn ($item) => ($item['item_code'] ? $item['item_code'].' — '.$item['title'] : $item['title']).' · '.$itemGenderLabel($item['gender'] ?? null).' · '.$itemTypeAbbr($item['participant_type'] ?? null);
@endphp
@if($excludedLabels->isNotEmpty())
<p style="text-align:center;font-size:9px;color:#b45309;margin-top:2px">† {{ $excludedLabels->implode(', ') }} excluded from OVERALL (still totalled in its own Sub column)</p>
@endif

@foreach($pages as $pageIndex => $page)
@php $categories = $page['categories']; @endphp
<div class="page-block">
@if(count($pages) > 1)
<p class="page-note">Page {{ $pageIndex + 1 }} of {{ count($pages) }}{{ $pageIndex > 0 ? ' — continued' : '' }}</p>
@endif
<table>
    <thead>
        <tr>
            <th class="rank-col" rowspan="3">Rank</th>
            <th class="school-col" rowspan="3">School</th>
            @foreach($categories as $category)
                @php $categoryItemCount = collect($category['heads'])->sum(fn ($h) => count($h['items'])); @endphp
                <th colspan="{{ $categoryItemCount + ($category['is_complete_here'] ? 1 : 0) }}">{{ $category['label'] }}{{ $category['is_continuation'] ? ' (cont\'d)' : '' }}{{ $category['excluded_from_overall'] ? ' †' : '' }}</th>
            @endforeach
            @if($page['is_last_page'])
                <th rowspan="3" class="overall-col">OVERALL</th>
            @endif
        </tr>
        <tr>
            @foreach($categories as $category)
                @foreach($category['heads'] as $head)
                    <th colspan="{{ count($head['items']) }}" class="head-col">{{ $head['head_label'] }}</th>
                @endforeach
                @if($category['is_complete_here'])
                    <th class="subtotal-col" rowspan="2">Sub</th>
                @endif
            @endforeach
        </tr>
        <tr>
            @foreach($categories as $category)
                @foreach($category['heads'] as $head)
                    @foreach($head['items'] as $item)
                        <th class="item-col" title="{{ $item['title'] }}"><div class="item-col-inner">{{ $itemHeaderLabel($item) }}</div></th>
                    @endforeach
                @endforeach
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($schools as $school)
        <tr>
            <td class="rank-col">{{ $school['rank'] }}</td>
            <td class="school-col">{{ strtoupper($school['school_name']) }}</td>
            @foreach($categories as $category)
                @foreach($category['heads'] as $head)
                    @foreach($head['items'] as $item)
                        <td>{{ $analytics->formatMatrixCell($school, $item['id']) }}</td>
                    @endforeach
                @endforeach
                @if($category['is_complete_here'])
                    <td class="subtotal-col">{{ $school['category_totals'][$category['key']] ?? 0 }}</td>
                @endif
            @endforeach
            @if($page['is_last_page'])
                <td class="overall-col">{{ $school['overall'] }}</td>
            @endif
        </tr>
        @empty
        <tr><td colspan="100%">No results recorded yet.</td></tr>
        @endforelse
    </tbody>
</table>
</div>
@endforeach
</body></html>
