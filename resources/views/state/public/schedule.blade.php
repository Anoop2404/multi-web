@extends('layouts.portal')

@section('title', $event->name.' — Schedule')

@section('content')
<div class="portal-wrap">
    <div class="portal-page" style="max-width:60rem;">
        <div class="portal-card">
            <div class="portal-card-header" style="flex-direction:column;align-items:flex-start;gap:.25rem;">
                <h1 class="portal-card-title">{{ $event->name }} — Schedule</h1>
                <p class="portal-card-sub">Report at the reporting time, not the start time.</p>
            </div>
            <div class="portal-card-body">
                @include('state.public._nav')

                @forelse ($days as $day => $rows)
                    <h2 style="font-size:.95rem;color:#1e3a5f;margin:1.25rem 0 .4rem;">{{ $day }}</h2>
                    <div style="overflow-x:auto;">
                        <table class="state-table">
                            <thead>
                                <tr><th>Report</th><th>Start</th><th>Item</th><th>Stage / venue</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td>{{ $row['reporting_at'] ?? '—' }}</td>
                                        <td>{{ $row['starts_at'] ?? '—' }}</td>
                                        <td>{{ $row['item'] ?? $row['item_code'] }}</td>
                                        <td>{{ $row['venue'] ?? 'To be announced' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @empty
                    <p class="portal-hint">No items have been scheduled yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
