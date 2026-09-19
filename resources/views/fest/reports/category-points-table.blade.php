<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $categoryLabel }} — Category Points Table</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px}
table{width:100%;border-collapse:collapse;margin-top:10px}
th,td{border:1px solid #ccc;padding:3px 4px;text-align:center}
th{background:#1d3557;color:#fff;font-size:8px;font-weight:bold;white-space:nowrap}
.school-col{text-align:left;min-width:120px}
.rank-col{width:28px}
.item-col{height:120px;vertical-align:bottom}
.item-col span{writing-mode:vertical-rl;transform:rotate(180deg);display:inline-block;white-space:nowrap}
.subtotal-col{background:#1d3557;color:#fff;font-weight:bold}
tbody tr:nth-child(even){background:#f7f9fc}
tbody td.subtotal-col{background:#c8d6ea;color:#1d3557}
</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? 'Sahodaya', 'logoSrc' => $logoSrc ?? null])

@php
$itemGenderLabel = fn ($g) => (! $g || $g === 'open') ? 'Mixed' : ucfirst($g);
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
                <th class="item-col" title="{{ $item['title'] }}"><span>{{ $itemHeaderLabel($item) }}</span></th>
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
