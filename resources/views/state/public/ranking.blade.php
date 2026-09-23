@extends('layouts.portal')

@section('title', $event->name.' — Sahodaya Ranking')

@section('content')
<div class="portal-wrap">
    <div class="portal-page" style="max-width:60rem;">
        <div class="portal-card">
            <div class="portal-card-header" style="flex-direction:column;align-items:flex-start;gap:.25rem;">
                <h1 class="portal-card-title">{{ $event->name }} — Sahodaya Ranking</h1>
                {{-- The State competes Sahodaya against Sahodaya; the school breakdown is a drill-down. --}}
                <p class="portal-card-sub">Points from released results only. The Sahodaya is the competing unit; tap one to see which of its schools earned the points.</p>
            </div>
            <div class="portal-card-body">
                @include('state.public._nav')

                @if ($ranking->isEmpty())
                    <p class="portal-hint">No points have been released yet.</p>
                @else
                    <div style="overflow-x:auto;">
                        <table class="state-table">
                            <thead>
                                <tr><th style="width:3rem;">#</th><th>Sahodaya</th><th>District</th><th style="text-align:right;">1st</th><th style="text-align:right;">2nd</th><th style="text-align:right;">3rd</th><th style="text-align:right;">Points</th></tr>
                            </thead>
                            <tbody>
                                @foreach ($ranking as $row)
                                    <tr>
                                        <td><strong>{{ $row['rank'] }}</strong></td>
                                        <td>
                                            @if ($row['sahodaya_id'] !== 'unattributed')
                                                <a href="/state/kalotsav/{{ $event->id }}/sahodaya/{{ $row['sahodaya_id'] }}" style="color:#1e3a5f;font-weight:600;">{{ $row['sahodaya'] }}</a>
                                            @else
                                                {{ $row['sahodaya'] }}
                                            @endif
                                        </td>
                                        <td>{{ $row['district'] ?? '—' }}</td>
                                        <td style="text-align:right;">{{ $row['firsts'] }}</td>
                                        <td style="text-align:right;">{{ $row['seconds'] }}</td>
                                        <td style="text-align:right;">{{ $row['thirds'] }}</td>
                                        <td style="text-align:right;"><strong>{{ $row['points'] }}</strong></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
