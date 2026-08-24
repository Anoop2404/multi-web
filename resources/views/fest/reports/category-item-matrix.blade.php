<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Category & Item-wise Consolidated Report</title>
<style>
body{font-family:DejaVu Sans,sans-serif;font-size:9px}
table{width:100%;border-collapse:collapse;margin-top:10px}
th,td{border:1px solid #ccc;padding:3px 4px;text-align:center}
th{background:#1d3557;color:#fff;font-size:8px;font-weight:bold}
.school-col{text-align:left;min-width:120px}
.subtotal-col{background:#eef2f8;font-weight:bold}
.overall-col{background:#1d3557;color:#fff;font-weight:bold}
tbody tr:nth-child(even){background:#f7f9fc}
tbody td.subtotal-col{background:#dde6f2}
tbody td.overall-col{background:#c8d6ea;color:#1d3557}
</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }} — Category & Item-wise Consolidated Report</h2>

<table>
    <thead>
        <tr>
            <th class="school-col" rowspan="2">School</th>
            @foreach($categories as $category)
                <th colspan="{{ count($category['items']) + 1 }}">{{ $category['label'] }}</th>
            @endforeach
            <th rowspan="2" class="overall-col">OVERALL</th>
        </tr>
        <tr>
            @foreach($categories as $category)
                @foreach($category['items'] as $item)
                    <th title="{{ $item['title'] }}">{{ $item['item_code'] ?: $item['title'] }}</th>
                @endforeach
                <th class="subtotal-col">Sub</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($schools as $school)
        <tr>
            <td class="school-col">{{ strtoupper($school['school_name']) }}</td>
            @foreach($categories as $category)
                @foreach($category['items'] as $item)
                    <td>{{ $school['points_by_item'][$item['id']] ?? 0 }}</td>
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
