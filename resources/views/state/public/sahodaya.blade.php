@extends('layouts.portal')

@section('title', $sahodaya['name'].' — '.$event->name)

@section('content')
<div class="portal-wrap">
    <div class="portal-page" style="max-width:56rem;">
        <div class="portal-card">
            <div class="portal-card-header" style="flex-direction:column;align-items:flex-start;gap:.25rem;">
                <h1 class="portal-card-title">{{ $sahodaya['name'] }}</h1>
                <p class="portal-card-sub">
                    {{ $event->name }}
                    @if ($sahodaya['district']) · {{ $sahodaya['district'] }} @endif
                    @if ($standing) · Rank {{ $standing['rank'] }} with {{ $standing['points'] }} points @endif
                </p>
            </div>
            <div class="portal-card-body">
                @include('state.public._nav')
                <p style="margin:0 0 1rem;"><a href="/state/kalotsav/{{ $event->id }}/ranking" style="color:#1e3a5f;font-size:.85rem;">← Full ranking</a></p>

                <h2 style="font-size:.95rem;color:#1e3a5f;margin:0 0 .4rem;">Points by school</h2>
                @if ($schools->isEmpty())
                    <p class="portal-hint">No released results for this Sahodaya yet.</p>
                @else
                    <table class="state-table">
                        <thead><tr><th>School</th><th style="text-align:right;">1st</th><th style="text-align:right;">Placings</th><th style="text-align:right;">Points</th></tr></thead>
                        <tbody>
                            @foreach ($schools as $school)
                                <tr>
                                    <td>{{ $school['school'] }}</td>
                                    <td style="text-align:right;">{{ $school['firsts'] }}</td>
                                    <td style="text-align:right;">{{ $school['entries'] }}</td>
                                    <td style="text-align:right;"><strong>{{ $school['points'] }}</strong></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                @if ($placings->isNotEmpty())
                    <h2 style="font-size:.95rem;color:#1e3a5f;margin:1.5rem 0 .4rem;">Placings</h2>
                    <table class="state-table">
                        <thead><tr><th style="width:4rem;">Pos</th><th>Item</th><th>Participant</th><th>School</th></tr></thead>
                        <tbody>
                            @foreach ($placings as $placing)
                                <tr>
                                    <td><span class="pos-badge pos-{{ $placing['position'] }}">{{ $placing['position'] }}</span></td>
                                    <td>{{ $placing['item'] }}</td>
                                    <td>{{ $placing['participants'] }}</td>
                                    <td>{{ $placing['school'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
