{{-- Only the sections that are actually published are linked: a dead tab reads as a broken site. --}}
<div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;">
    @if ($visibility['schedule'])
        <a href="/state/kalotsav/{{ $event->id }}/schedule" class="portal-chip">Schedule</a>
    @endif
    @if ($visibility['results'])
        <a href="/state/kalotsav/{{ $event->id }}/results" class="portal-chip">Results</a>
    @endif
    @if ($visibility['ranking'])
        <a href="/state/kalotsav/{{ $event->id }}/ranking" class="portal-chip">Sahodaya Ranking</a>
    @endif
    <a href="/state/certificates/verify" class="portal-chip">Verify a certificate</a>
</div>
@include('state.public._styles')
