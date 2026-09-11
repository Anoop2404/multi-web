<!DOCTYPE html>
<html><head><meta charset="utf-8"><title>Schedule Clashes</title>
@include('partials.pdf-report-styles')
<style>.item-tags{margin-top:3px}</style>
</head><body>
@php($participantByDate = collect($participant)->groupBy(fn ($c) => $c['date'] ?? 'Unscheduled'))
@php($stageByDate = collect($stage)->groupBy(fn ($c) => $c['date'] ?? 'Unscheduled'))
@php($firstPage = true)
@php($slNo = 0)

@forelse($participantByDate as $date => $rows)
<div class="day-page" @if(!$firstPage) style="page-break-before:always" @endif>
    @php($firstPage = false)
    @include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

    <h2 class="report-title">{{ $event->title }} — Schedule Clashes{{ $school ? ' — '.$school->name : '' }}</h2>
    <p class="report-meta">
        <span class="badge badge-dark">{{ count($participant) }} participant clash(es)</span>
        <span class="badge">{{ count($stage) }} stage conflict(s)</span>
        <span class="badge">Generated {{ now()->format('d M Y, h:i A') }}</span>
    </p>

    <div class="day-band">
        Participant clashes — {{ $date === 'Unscheduled' ? 'Unscheduled' : \Carbon\Carbon::createFromFormat('d M Y', $date)->format('l, d F Y') }}
        <span class="count">· {{ count($rows) }} clash{{ count($rows) === 1 ? '' : 'es' }}</span>
    </div>

    <table>
    <thead><tr><th>Sl No</th><th>Student</th><th>School</th><th>Item 1</th><th>Item 2</th></tr></thead>
    <tbody>
    @foreach($rows as $c)
    @php($slNo++)
    <tr class="{{ $slNo % 2 === 0 ? 'row-even' : '' }}">
        <td>{{ $slNo }}</td>
        <td>{{ $c['student_name'] }}</td>
        <td>{{ $c['school_name'] }}</td>
        <td>{{ $c['event1'] }}<div class="item-tags"><small>{{ implode(' · ', array_filter([$c['item1_category'] ?? null, $c['item1_gender'] ?? null, $c['item1_type'] ?? null, $c['item1_stage'] ?? null, $c['item1_time'] ?? null])) }}</small></div></td>
        <td>{{ $c['event2'] }}<div class="item-tags"><small>{{ implode(' · ', array_filter([$c['item2_category'] ?? null, $c['item2_gender'] ?? null, $c['item2_type'] ?? null, $c['item2_stage'] ?? null, $c['item2_time'] ?? null])) }}</small></div></td>
    </tr>
    @endforeach
    </tbody>
    </table>
</div>
@empty
<div class="day-page">
    @include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])
    @php($firstPage = false)
    <h2 class="report-title">{{ $event->title }} — Schedule Clashes{{ $school ? ' — '.$school->name : '' }}</h2>
    <p class="report-meta"><span class="badge">No participant clashes detected.</span></p>
</div>
@endforelse

@php($stageSlNo = 0)
@forelse($stageByDate as $date => $rows)
<div class="day-page" @if(!$firstPage) style="page-break-before:always" @endif>
    @php($firstPage = false)
    @include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])

    <h2 class="report-title">{{ $event->title }} — Schedule Clashes{{ $school ? ' — '.$school->name : '' }}</h2>
    <p class="report-meta">
        <span class="badge">{{ count($participant) }} participant clash(es)</span>
        <span class="badge badge-dark">{{ count($stage) }} stage conflict(s)</span>
        <span class="badge">Generated {{ now()->format('d M Y, h:i A') }}</span>
    </p>

    <div class="day-band">
        Stage conflicts — {{ $date === 'Unscheduled' ? 'Unscheduled' : \Carbon\Carbon::createFromFormat('d M Y', $date)->format('l, d F Y') }}
        <span class="count">· {{ count($rows) }} conflict{{ count($rows) === 1 ? '' : 's' }}</span>
    </div>

    <table>
    <thead><tr><th>Sl No</th><th>Stage</th><th>Item 1</th><th>Item 2</th></tr></thead>
    <tbody>
    @foreach($rows as $c)
    @php($stageSlNo++)
    <tr class="{{ $stageSlNo % 2 === 0 ? 'row-even' : '' }}">
        <td>{{ $stageSlNo }}</td>
        <td>{{ $c['stage'] }}{{ $c['venue'] ? ' · '.$c['venue'] : '' }}</td>
        <td>{{ $c['item1'] }}<div class="item-tags"><small>{{ implode(' · ', array_filter([$c['item1_category'] ?? null, $c['item1_gender'] ?? null, $c['item1_type'] ?? null, $c['item1_time'] ?? null])) }}</small></div></td>
        <td>{{ $c['item2'] }}<div class="item-tags"><small>{{ implode(' · ', array_filter([$c['item2_category'] ?? null, $c['item2_gender'] ?? null, $c['item2_type'] ?? null, $c['item2_time'] ?? null])) }}</small></div></td>
    </tr>
    @endforeach
    </tbody>
    </table>
</div>
@empty
<div class="day-page" @if(!$firstPage) style="page-break-before:always" @endif>
    @php($firstPage = false)
    @include('partials.pdf-branding-header', ['orgName' => $orgName ?? ($sahodaya->name ?? 'Sahodaya'), 'logoSrc' => $logoSrc ?? null])
    <h2 class="report-title">{{ $event->title }} — Schedule Clashes{{ $school ? ' — '.$school->name : '' }}</h2>
    <p class="report-meta"><span class="badge">No stage conflicts detected.</span></p>
</div>
@endforelse
</body></html>
