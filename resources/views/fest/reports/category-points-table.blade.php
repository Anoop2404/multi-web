<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $categoryLabel }} — Category Points Table</title>
@php
$itemColWidth = $itemColWidth ?? 24;
$fontSize = $fontSize ?? 8;
$innerHeight = max(9, $itemColWidth - 4);
$innerLeft = max(1, (int) round($itemColWidth / 8));
@endphp
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:{{ $fontSize }}px}
table{width:100%;border-collapse:collapse;margin-top:10px}
th,td{border:1px solid #ccc;padding:2px 3px;text-align:center}
th{background:#1d3557;color:#fff;font-size:{{ max(6, $fontSize - 1) }}px;font-weight:bold;white-space:nowrap}
.school-col{text-align:left;width:62px;max-width:62px}
.rank-col{width:16px}
/* No left/right border on the item header cell itself -- it sat directly behind the
   rotated text and read as a stray line struck through the letters. The column's own
   background/spacing already separates one item from the next without it. */
.item-col{height:210px;width:{{ $itemColWidth }}px;min-width:{{ $itemColWidth }}px;max-width:{{ $itemColWidth }}px;position:relative;vertical-align:bottom;padding:0;border-left:none;border-right:none}
.item-col-inner{display:block;position:absolute;bottom:2px;left:{{ $innerLeft }}px;width:230px;height:{{ $innerHeight }}px;line-height:{{ $innerHeight }}px;transform:rotate(-90deg);transform-origin:bottom left;white-space:nowrap}
.subtotal-col{background:#1d3557;color:#fff;font-weight:bold;width:32px;max-width:32px}
tbody tr:nth-child(even){background:#f7f9fc}
tbody td.subtotal-col{background:#c8d6ea;color:#1d3557}
</style>
</head><body>
    @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? 'Sahodaya', 'logoSrc' => $logoSrc ?? null])

@php
$itemGenderLabel = fn ($g) => (! $g || $g === 'open') ? 'Mixed' : (['male' => 'Boys', 'female' => 'Girls'][$g] ?? ucfirst($g));
$itemTypeAbbr = fn ($t) => in_array($t, ['team', 'group', 'pair', 'trio'], true) ? 'Grp' : 'Ind';
$itemHeaderLabel = fn ($item) => ($item['item_code'] ? $item['item_code'].' — '.$item['title'] : $item['title']).' · '.$itemGenderLabel($item['gender'] ?? null).' · '.$itemTypeAbbr($item['participant_type'] ?? null);
@endphp

<h2 style="text-align:center">{{ $event->title }} — {{ $categoryLabel }} Points Table</h2>
<p style="text-align:center;font-size:10px;color:#64748b;margin-top:2px">Generated on {{ $generatedAt }}</p>

<table>
    <thead>
        <tr>
            <th class="rank-col">Rank</th>
            <th class="school-col">School</th>
            @foreach($items as $item)
                <th class="item-col" title="{{ $item['title'] }}"><div class="item-col-inner">{{ $itemHeaderLabel($item) }}</div></th>
            @endforeach
            <th class="subtotal-col">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse($schools as $school)
        <tr>
            <td>{{ $school['rank'] }}</td>
            <td class="school-col">{{ strtoupper($school['school_name']) }}</td>
            @foreach($items as $item)
                <td>{{ $analytics->formatMatrixCell($school, $item['id']) }}</td>
            @endforeach
            <td class="subtotal-col">{{ $school['subtotal'] }}</td>
        </tr>
        @empty
        <tr><td colspan="100%">No results recorded yet.</td></tr>
        @endforelse
    </tbody>
</table>
</body></html>
