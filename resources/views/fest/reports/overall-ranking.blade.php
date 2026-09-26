<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>{{ $title ?? 'Overall Ranking' }}</title>
<style>
    @page { margin: {{ ($isDomPdf ?? true) ? '22px 28px 24px 28px' : '32mm 10mm 14mm 10mm' }}; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #0f172a; }
    h2 { text-align: center; font-size: 15px; margin: 4px 0 2px; }
    .meta { text-align: center; font-size: 10px; color: #64748b; margin: 0 0 8px; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    thead { display: table-header-group; }
    tr { page-break-inside: avoid; }
    th { background: #1d3557; color: #fff; padding: 0 8px; height: 28px; font-size: 11px; text-transform: uppercase; }
    td { border-bottom: 1px solid #cbd5e1; padding: 0 8px; height: 28px; font-size: 11.5px; text-align: center; }
    td.school { text-align: left; font-weight: bold; }
    th.school { text-align: left; }
    td.pts { font-weight: bold; }
    tr.top3 td { font-weight: bold; background: #fef9e7; }
</style>
</head><body>
@if($isDomPdf ?? true)
    @include('partials.pdf-generated-footer', ['generatedAt' => $generatedAt ?? null])
    @include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])
    <h2>{{ $event->title }} — {{ $title ?? 'Overall School Ranking' }}</h2>
    <div class="meta">Generated on {{ now()->format('d M Y, h:i A') }}</div>
@endif
<table><thead><tr><th>#</th><th class="school">School</th><th>Gold</th><th>Silver</th><th>Bronze</th><th>Total Pts</th></tr></thead>
<tbody>
@foreach($schools as $i => $s)
@php $rank = $s->rank ?? ($i + 1); @endphp
<tr class="{{ $rank <= 3 ? 'top3' : '' }}"><td>{{ $rank }}</td><td class="school">{{ strtoupper($s->name) }}</td><td>{{ $s->gold }}</td><td>{{ $s->silver }}</td><td>{{ $s->bronze }}</td><td class="pts">{{ $s->total_points }}</td></tr>
@endforeach
</tbody></table></body></html>
