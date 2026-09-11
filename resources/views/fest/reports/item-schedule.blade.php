<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Item Schedule</title>
@include('partials.pdf-report-styles')
</head><body>
@php($rowsByDate = collect($rows)->groupBy(fn ($r) => $r['scheduled_date'] ?? 'Not scheduled'))
@php($slNo = 0)
@forelse($rowsByDate as $dateKey => $dateRows)
<div class="day-page" @if(!$loop->first) style="page-break-before:always" @endif>
    @include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

    <h2 class="report-title">{{ $event->title }} — Item schedule</h2>
    <p class="report-meta">
        <span class="badge badge-dark">{{ $summary['scheduled'] }} scheduled</span>
        <span class="badge">{{ $summary['unscheduled'] }} not scheduled</span>
        <span class="badge">{{ $summary['total'] }} items total</span>
        @if($date)<span class="badge badge-warn">Date filter: {{ $date }}</span>@endif
        <span class="badge">Generated {{ now()->format('d M Y, h:i A') }}</span>
    </p>

    <div class="day-band">
        {{ $dateKey === 'Not scheduled' ? 'Not scheduled' : \Carbon\Carbon::parse($dateKey)->format('l, d F Y') }}
        <span class="count">· {{ count($dateRows) }} item{{ count($dateRows) === 1 ? '' : 's' }}</span>
    </div>

    <table>
    <thead><tr><th>Sl No</th><th>Item</th><th>Category</th><th>Gender</th><th>Time</th><th>Venue</th><th>Stage</th></tr></thead>
    <tbody>
    @php($prevStage = '__none__')
    @foreach($dateRows as $row)
    @if(($row['stage'] ?? null) !== $prevStage)
    <tr class="stage-band"><td colspan="7">{{ $row['stage'] ?? 'No stage assigned' }}</td></tr>
    @php($prevStage = $row['stage'] ?? null)
    @endif
    @php($slNo++)
    <tr class="{{ $slNo % 2 === 0 ? 'row-even' : '' }}">
        <td>{{ $slNo }}</td>
        <td>{{ $row['title'] }}</td>
        <td>{{ $row['category_label'] ?? '—' }}</td>
        <td>{{ $row['gender_label'] ?? '—' }}</td>
        <td>{{ $row['scheduled_time_12h'] ?? '—' }}</td>
        <td>{{ $row['venue'] ?? '—' }}</td>
        <td>{{ $row['stage'] ?? '—' }}</td>
    </tr>
    @endforeach
    </tbody>
    </table>
</div>
@empty
<div class="day-page">
    @include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])
    <h2 class="report-title">{{ $event->title }} — Item schedule</h2>
    <p class="report-meta"><span class="badge">No items match the current filters.</span></p>
</div>
@endforelse
</body></html>
