<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>House Results</title>
<style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}table{width:100%;border-collapse:collapse}th{background:#1d3557;color:#fff;padding:5px}td{border:1px solid #ccc;padding:5px}</style>
</head><body>
@include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

<h2 style="text-align:center">{{ $event->title }} — House-wise Results</h2>
<table><thead><tr><th>Rank</th><th>House</th><th>Points</th></tr></thead>
<tbody>
@foreach($board as $row)
<tr><td>#{{ $row['rank'] }}</td><td>{{ $row['house_name'] }}</td><td>{{ $row['total_points'] }}</td></tr>
@endforeach
</tbody></table></body></html>
