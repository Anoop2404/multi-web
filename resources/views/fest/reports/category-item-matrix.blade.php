<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Category & Item-wise Consolidated Report</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px}
table{width:100%;border-collapse:collapse;margin-top:10px}
th,td{border:1px solid #ccc;padding:3px 4px;text-align:center}
th{background:#1d3557;color:#fff;font-size:8px;font-weight:bold;white-space:nowrap}
.school-col{text-align:left;min-width:120px}
.head-col{background:#3d5a80;font-size:8px;padding:4px}
.item-col{height:120px;vertical-align:bottom}
.item-col span{writing-mode:vertical-rl;transform:rotate(180deg);display:inline-block;white-space:nowrap}
.subtotal-col{background:#eef2f8;font-weight:bold}
.overall-col{background:#1d3557;color:#fff;font-weight:bold}
tbody tr:nth-child(even){background:#f7f9fc}
tbody td.subtotal-col{background:#dde6f2}
tbody td.overall-col{background:#c8d6ea;color:#1d3557}
</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }} — Category & Item-wise Consolidated Report</h2>
<p style="text-align:center;font-size:10px;color:#64748b;margin-top:2px">Generated on {{ now()->format('d M Y, h:i A') }}</p>

<table>
    <thead>
        <tr>
            <th class="school-col" rowspan="3">School</th>
            @foreach($categories as $category)
                @php $categoryItemCount = collect($category['heads'])->sum(fn ($h) => count($h['items'])); @endphp
                <th colspan="{{ $categoryItemCount + 1 }}">{{ $category['label'] }}</th>
            @endforeach
            <th rowspan="3" class="overall-col">OVERALL</th>
        </tr>
        <tr>
            @foreach($categories as $category)
                @foreach($category['heads'] as $head)
                    <th colspan="{{ count($head['items']) }}" class="head-col">{{ $head['head_label'] }}</th>
                @endforeach
                <th class="subtotal-col" rowspan="2">Sub</th>
            @endforeach
        </tr>
        <tr>
            @foreach($categories as $category)
                @foreach($category['heads'] as $head)
                    @foreach($head['items'] as $item)
                        <th class="item-col" title="{{ $item['title'] }}"><span>{{ $item['item_code'] ? $item['item_code'].' — '.$item['title'] : $item['title'] }}</span></th>
                    @endforeach
                @endforeach
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($schools as $school)
        <tr>
            <td class="school-col">{{ strtoupper($school['school_name']) }}</td>
            @foreach($categories as $category)
                @foreach($category['heads'] as $head)
                    @foreach($head['items'] as $item)
                        <td>{{ $analytics->formatMatrixCell($school, $item['id']) }}</td>
                    @endforeach
                @endforeach
                <td class="subtotal-col">{{ $school['category_totals'][$category['key']] ?? 0 }}</td>
            @endforeach
            <td class="overall-col">{{ $school['overall'] }}</td>
        </tr>
        @empty
        <tr><td colspan="100%">No results recorded yet.</td></tr>
        @endforelse
    </tbody>
</table>
</body></html>
