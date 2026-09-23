@extends('layouts.portal')

@section('title', 'State Kalotsav')

@section('content')
<div class="portal-wrap">
    <div class="portal-page" style="max-width:52rem;">
        <div class="portal-card">
            <div class="portal-card-header" style="flex-direction:column;align-items:flex-start;gap:.25rem;">
                <p class="portal-card-sub" style="text-transform:uppercase;letter-spacing:.06em;font-size:.7rem;">Sahodaya Central School Complex</p>
                <h1 class="portal-card-title">State Kalotsav</h1>
                <p class="portal-card-sub">Schedules, published results and Sahodaya standings.</p>
            </div>
            <div class="portal-card-body">
                @include('state.public._styles')

                @forelse ($events as $event)
                    <div style="border:1px solid #e2e8f0;border-radius:.75rem;padding:1rem;margin-bottom:.75rem;">
                        <h2 style="margin:0 0 .15rem;font-size:1.05rem;color:#1e3a5f;">{{ $event['name'] }}</h2>
                        <p class="portal-card-sub" style="margin:0 0 .75rem;">
                            @if ($event['starts_on']){{ $event['starts_on'] }}@endif
                            @if ($event['ends_on'] && $event['ends_on'] !== $event['starts_on']) – {{ $event['ends_on'] }} @endif
                        </p>
                        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
                            @if ($event['visibility']['schedule'])
                                <a href="/state/kalotsav/{{ $event['id'] }}/schedule" class="portal-chip">Schedule</a>
                            @endif
                            @if ($event['visibility']['results'])
                                <a href="/state/kalotsav/{{ $event['id'] }}/results" class="portal-chip">Results</a>
                            @endif
                            @if ($event['visibility']['ranking'])
                                <a href="/state/kalotsav/{{ $event['id'] }}/ranking" class="portal-chip">Sahodaya Ranking</a>
                            @endif
                        </div>
                    </div>
                @empty
                    {{-- Nothing published is the ordinary state for most of the year, and it should
                         read as "not yet", not as an error. --}}
                    <p class="portal-hint">Nothing has been published yet. Schedules and results appear here once the State office releases them.</p>
                @endforelse

                <p style="margin-top:1.25rem;">
                    <a href="/state/certificates/verify" class="portal-chip">Verify a certificate →</a>
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
