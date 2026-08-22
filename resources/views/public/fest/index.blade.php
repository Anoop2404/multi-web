@extends('layouts.public-event')

@section('content')
<section class="py-10 sm:py-14 px-4 bg-slate-950 text-white min-h-[70vh]">
    <div class="max-w-6xl mx-auto">
        <div class="mb-8">
            @include('public.fest.partials.page-hero', [
                'eyebrow' => 'Public event directory',
                'title' => 'Festival Portal',
                'subtitle' => 'Every venue and operational event has its own schedule, scoreboard and results. Find the event by its name, series, phase or region.',
            ])
        </div>

        @if($events->isNotEmpty())
        <div class="sticky top-16 z-20 rounded-2xl border border-slate-800 bg-slate-900/90 backdrop-blur shadow-xl p-3 sm:p-4 mb-9">
            <div class="flex flex-col lg:flex-row lg:items-center gap-3">
                <label class="relative flex-1">
                    <span class="sr-only">Search public events</span>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="absolute left-3.5 top-1/2 -translate-y-1/2 w-5 h-5 text-white/30"><circle cx="11" cy="11" r="7" stroke-width="2"/><path d="m20 20-3.5-3.5" stroke-width="2" stroke-linecap="round"/></svg>
                    <input id="event-catalogue-search" type="search" autocomplete="off" placeholder="Search event, venue, phase or region"
                           class="w-full rounded-xl border-slate-700 bg-slate-950 text-white placeholder:text-white/30 pl-11 pr-4 py-3 text-sm focus:border-amber-500 focus:ring-amber-500">
                </label>
                <div class="flex gap-2 overflow-x-auto pb-1 lg:pb-0" aria-label="Filter events by status">
                    @foreach(['all' => 'All', 'live' => 'Live & Open', 'upcoming' => 'Upcoming', 'completed' => 'Completed'] as $key => $label)
                    <button type="button" data-event-status-filter="{{ $key }}" aria-pressed="{{ $key === 'live' ? 'true' : 'false' }}"
                            class="event-filter whitespace-nowrap rounded-xl border px-3.5 py-2.5 text-xs font-bold transition {{ $key === 'live' ? 'bg-amber-500 border-amber-500 text-slate-950' : 'bg-transparent border-slate-700 text-white/60 hover:border-amber-500/50' }}">
                        {{ $label }}
                    </button>
                    @endforeach
                </div>
                @if($eventTypes->isNotEmpty())
                <label class="shrink-0">
                    <span class="sr-only">Filter by event type</span>
                    <select id="event-catalogue-type" class="rounded-xl border-slate-700 bg-slate-950 text-white text-xs font-bold focus:border-amber-500 focus:ring-amber-500">
                        <option value="">All types</option>
                        @foreach($eventTypes as $type)
                        <option value="{{ $type['value'] }}">{{ $type['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                @endif
            </div>
            <p id="event-filter-summary" class="text-xs text-white/40 mt-2 px-1" aria-live="polite">Showing ongoing events</p>
        </div>

        <div id="event-catalogue" class="space-y-12">
            @foreach($eventGroups as $group)
            <section data-event-status-section="{{ $group['key'] }}">
                <div class="flex items-end justify-between gap-4 mb-5">
                    <div>
                        <h2 class="text-xl sm:text-2xl font-bold text-white">{{ $group['label'] }}</h2>
                        <p class="text-sm text-white/50 mt-1">{{ $group['description'] }}</p>
                    </div>
                </div>

                <div class="space-y-7">
                    @foreach($group['series'] as $series)
                    <div data-event-series>
                        @if($series['events']->count() > 1 || $series['events']->first()?->parent_event_id)
                        <div class="flex items-center gap-3 mb-3">
                            <h3 class="text-xs font-extrabold uppercase tracking-[0.16em] text-white/40">{{ $series['label'] }}</h3>
                            <span class="h-px bg-slate-800 flex-1"></span>
                            <span class="text-[11px] text-white/30">{{ $series['events']->count() }} {{ Str::plural('event', $series['events']->count()) }}</span>
                        </div>
                        @endif

                        <div class="grid md:grid-cols-2 gap-4">
                            @foreach($series['events'] as $listedEvent)
                            @php
                                $searchText = collect([
                                    $listedEvent->title, $listedEvent->resolvedVenueName(), $listedEvent->parentEvent?->title,
                                    $listedEvent->sourcePhase?->name, $listedEvent->region?->name,
                                    str_replace('_', ' ', $listedEvent->event_type),
                                ])->filter()->implode(' ');
                                $statusClass = match($listedEvent->status) {
                                    'ongoing' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
                                    'registration_open' => 'border-sky-500/30 bg-sky-500/10 text-sky-300',
                                    'completed' => 'border-white/10 bg-white/5 text-white/50',
                                    default => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
                                };
                            @endphp
                            <a href="{{ route('tenant.fest.show', $listedEvent->id) }}" data-event-card data-search="{{ Str::lower($searchText) }}" data-event-type="{{ $listedEvent->event_type }}"
                               class="group flex flex-col min-h-56 p-5 sm:p-6 bg-slate-900/60 border border-slate-800 rounded-2xl hover:border-amber-500/50 hover:bg-slate-900 hover:-translate-y-0.5 transition focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 focus:ring-offset-slate-950">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-amber-400">{{ ucfirst(str_replace('_', ' ', $listedEvent->event_type)) }}</p>
                                        <h3 class="font-bold text-lg leading-snug mt-1 text-white group-hover:text-amber-300 transition">{{ $listedEvent->title }}</h3>
                                    </div>
                                    <span class="shrink-0 inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-bold {{ $statusClass }}">
                                        @if($listedEvent->status === 'ongoing')<span class="relative flex h-1.5 w-1.5" aria-hidden="true"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span><span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-400"></span></span>@endif
                                        {{ ucfirst(str_replace('_', ' ', $listedEvent->status)) }}
                                    </span>
                                </div>

                                <dl class="mt-5 grid gap-2 text-sm text-white/60">
                                    <div class="flex gap-2.5"><dt class="sr-only">Date</dt><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-4 h-4 mt-0.5 shrink-0"><rect x="3" y="5" width="18" height="16" rx="2" stroke-width="2"/><path d="M8 3v4M16 3v4M3 10h18" stroke-width="2"/></svg><dd>{{ $listedEvent->event_start?->format('d M Y') ?? 'Date to be announced' }}@if($listedEvent->event_start && $listedEvent->event_end && !$listedEvent->event_end->isSameDay($listedEvent->event_start)) – {{ $listedEvent->event_end->format('d M Y') }}@endif</dd></div>
                                    @if($listedEvent->resolvedVenueName())<div class="flex gap-2.5"><dt class="sr-only">Venue</dt><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-4 h-4 mt-0.5 shrink-0"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z" stroke-width="2"/><circle cx="12" cy="10" r="2.5" stroke-width="2"/></svg><dd>{{ $listedEvent->resolvedVenueName() }}</dd></div>@endif
                                    @if($listedEvent->sourcePhase || $listedEvent->region)
                                    <div class="flex gap-2.5"><dt class="sr-only">Event context</dt><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" class="w-4 h-4 mt-0.5 shrink-0"><path d="M5 4v16M5 7h11l-2.5 4L16 15H5" stroke-width="2" stroke-linejoin="round"/></svg><dd>{{ collect([$listedEvent->sourcePhase?->name, $listedEvent->region?->name])->filter()->implode(' · ') }}</dd></div>
                                    @endif
                                </dl>

                                <div class="mt-auto pt-5 flex items-center justify-between gap-3">
                                    <div class="flex flex-wrap gap-2 text-xs font-bold">
                                        @if($listedEvent->schedule_published)<span class="inline-flex items-center gap-1 rounded-full border border-sky-500/30 bg-sky-500/10 text-sky-300 px-2.5 py-1">📅 Schedule</span>@endif
                                        @if($listedEvent->results_published)<span class="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 text-amber-300 px-2.5 py-1">🏆 Results</span>@endif
                                    </div>
                                    <span class="text-sm font-bold text-amber-400 group-hover:translate-x-1 transition">Open event →</span>
                                </div>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </section>
            @endforeach
        </div>

        <div id="event-filter-empty" class="hidden rounded-2xl border border-dashed border-slate-700 bg-slate-900/40 p-10 text-center">
            <h2 class="font-bold text-white">No matching events</h2>
            <p class="text-sm text-white/40 mt-2">Try another event name, venue, phase or status.</p>
        </div>
        @else
        <div class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/40 p-10 text-center">
            <p class="text-white font-bold">No festivals are public yet</p>
            <p class="text-sm text-white/40 mt-2">Published schedules and results will appear here when your Sahodaya opens the fest portal.</p>
        </div>
        @endif
    </div>
</section>

@if($events->isNotEmpty())
<script>
(() => {
    const search = document.getElementById('event-catalogue-search');
    const typeSelect = document.getElementById('event-catalogue-type');
    const cards = [...document.querySelectorAll('[data-event-card]')];
    const sections = [...document.querySelectorAll('[data-event-status-section]')];
    const filters = [...document.querySelectorAll('[data-event-status-filter]')];
    const summary = document.getElementById('event-filter-summary');
    const empty = document.getElementById('event-filter-empty');
    // Defaults to "Live & Open" (ongoing/accepting registrations) rather than "All" —
    // that's what a visitor almost always wants first; "All" is one click away.
    let status = 'live';

    const apply = () => {
        const query = search.value.trim().toLocaleLowerCase();
        const type = typeSelect ? typeSelect.value : '';
        let visible = 0;
        cards.forEach(card => {
            const section = card.closest('[data-event-status-section]');
            const matchesStatus = status === 'all' || section.dataset.eventStatusSection === status;
            const matchesType = !type || card.dataset.eventType === type;
            const matchesText = !query || card.dataset.search.includes(query);
            card.hidden = !(matchesStatus && matchesType && matchesText);
            if (!card.hidden) visible++;
        });
        document.querySelectorAll('[data-event-series]').forEach(series => {
            series.hidden = !series.querySelector('[data-event-card]:not([hidden])');
        });
        sections.forEach(section => {
            section.hidden = !section.querySelector('[data-event-card]:not([hidden])');
        });
        summary.textContent = `Showing ${visible} ${visible === 1 ? 'event' : 'events'}`;
        empty.classList.toggle('hidden', visible !== 0);
    };

    filters.forEach(button => button.addEventListener('click', () => {
        status = button.dataset.eventStatusFilter;
        filters.forEach(candidate => {
            const active = candidate === button;
            candidate.setAttribute('aria-pressed', active ? 'true' : 'false');
            candidate.classList.toggle('bg-amber-500', active);
            candidate.classList.toggle('border-amber-500', active);
            candidate.classList.toggle('text-slate-950', active);
            candidate.classList.toggle('bg-transparent', !active);
            candidate.classList.toggle('border-slate-700', !active);
            candidate.classList.toggle('text-white/60', !active);
        });
        apply();
    }));
    search.addEventListener('input', apply);
    if (typeSelect) typeSelect.addEventListener('change', apply);

    apply();
})();
</script>
@endif
@endsection
