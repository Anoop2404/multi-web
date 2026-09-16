<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Category & Item-wise Consolidated Report</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px}
table{width:100%;border-collapse:collapse;margin-top:10px}
th,td{border:1px solid #ccc;padding:3px 4px;text-align:center}
th{background:#1d3557;color:#fff;font-size:8px;font-weight:bold;white-space:nowrap}
.item-col{text-align:left;min-width:140px}
.category-row td{background:#1d3557;color:#fff;font-weight:bold;text-align:left;font-size:9.5px}
.head-row td{background:#3d5a80;color:#fff;font-weight:600;text-align:left;font-size:8.5px;padding-left:14px}
.subtotal-row td{background:#dde6f2;font-weight:bold}
.overall-row td{background:#1d3557;color:#fff;font-weight:bold}
tbody tr.item-row:nth-child(even){background:#f7f9fc}
</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }} — Category & Item-wise Consolidated Report</h2>
<p style="text-align:center;font-size:10px;color:#64748b;margin-top:2px">Generated on {{ now()->format('d M Y, h:i A') }}</p>

<table>
    <thead>
        <tr>
            <th class="item-col">Item</th>
            @foreach($schools as $school)
                <th>{{ strtoupper($school['school_name']) }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($categories as $category)
            <tr class="category-row"><td colspan="{{ count($schools) + 1 }}">{{ $category['label'] }}</td></tr>
            @foreach($category['heads'] as $head)
                <tr class="head-row"><td colspan="{{ count($schools) + 1 }}">{{ $head['head_label'] }}</td></tr>
                @foreach($head['items'] as $item)
                <tr class="item-row">
                    <td class="item-col" title="{{ $item['title'] }}">{{ $item['item_code'] ? $item['item_code'].' — '.$item['title'] : $item['title'] }}</td>
                    @foreach($schools as $school)
                        <td>{{ $analytics->formatMatrixCell($school, $item['id']) }}</td>
                    @endforeach
                </tr>
                @endforeach
            @endforeach
            <tr class="subtotal-row">
                <td class="item-col">{{ $category['label'] }} — Subtotal</td>
                @foreach($schools as $school)
                    <td>{{ $school['category_totals'][$category['key']] ?? 0 }}</td>
                @endforeach
            </tr>
        @empty
        <tr><td colspan="100%">No results recorded yet.</td></tr>
        @endforelse
        @if(count($schools))
        <tr class="overall-row">
            <td class="item-col">OVERALL</td>
            @foreach($schools as $school)
                <td>{{ $school['overall'] }}</td>
            @endforeach
        </tr>
        @endif
    </tbody>
</table>
</body></html>
