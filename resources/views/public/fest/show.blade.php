@extends('layouts.public-event')

@section('content')
<section class="py-8 sm:py-12 px-4 bg-slate-950 text-white min-h-screen">
    <div class="max-w-6xl mx-auto">

        <header class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-slate-950 to-slate-900 border border-amber-500/20 p-6 sm:p-9 shadow-2xl">
            <div aria-hidden="true" class="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-amber-500/15 blur-3xl"></div>
            <div aria-hidden="true" class="absolute -bottom-32 left-0 w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl"></div>
            <div class="relative max-w-4xl">
                <div class="flex flex-wrap items-center gap-2 text-[11px] font-bold uppercase tracking-wider">
                    <span class="rounded-full bg-amber-400 text-slate-950 px-3 py-1">{{ ucfirst(str_replace('_', ' ', $event->event_type)) }}</span>
                    <span class="rounded-full border border-white/20 bg-white/10 px-3 py-1">{{ $eventContext['status_label'] }}</span>
                    @if($eventContext['phase'])<span class="rounded-full border border-white/20 px-3 py-1">{{ $eventContext['phase'] }}</span>@endif
                    @if($eventContext['region'])<span class="rounded-full border border-white/20 px-3 py-1">{{ $eventContext['region'] }}</span>@endif
                </div>
                <h1 class="text-3xl sm:text-5xl font-extrabold font-heading leading-tight mt-5 text-white">{{ $event->title }}</h1>
                @if($event->description)<p class="text-white/60 mt-3 max-w-3xl leading-relaxed">{{ $event->description }}</p>@endif

                <dl class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mt-7 text-sm">
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4"><dt class="text-white/40 text-xs">Date</dt><dd class="font-bold mt-1">{{ $event->event_start?->format('d M Y') ?? 'To be announced' }}@if($event->event_start && $event->event_end && !$event->event_end->isSameDay($event->event_start)) – {{ $event->event_end->format('d M Y') }}@endif</dd></div>
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4"><dt class="text-white/40 text-xs">Venue</dt><dd class="font-bold mt-1">{{ $event->resolvedVenueName() ?: 'To be announced' }}</dd></div>
                    @if($eventContext['series'])<div class="rounded-2xl border border-white/10 bg-white/5 p-4"><dt class="text-white/40 text-xs">Festival series</dt><dd class="font-bold mt-1">{{ $eventContext['series'] }}</dd></div>@endif
                    <div class="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <dt class="text-white/40 text-xs">Results</dt>
                        <dd class="font-bold mt-1 {{ ($scopeResultsPublished || ($publishedItemCount ?? 0) > 0) ? 'text-amber-300' : '' }}">
                            @if($scopeResultsPublished)
                                Official results published
                            @elseif(($publishedItemCount ?? 0) > 0)
                                {{ $publishedItemCount }} item results published
                            @else
                                Awaiting publication
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>
        </header>

        @unless($scopeResultsPublished || ($publishedItemCount ?? 0) > 0)
        <p class="rounded-xl border border-amber-500/30 bg-amber-500/10 text-sm text-amber-200 px-4 py-3 mt-5">Participant names remain protected during live competition and appear after official publication or item result release.</p>
        @endunless

        <section class="mt-10" aria-labelledby="event-actions-title">
            <h2 id="event-actions-title" class="text-xs font-bold uppercase tracking-widest text-amber-400 mb-3">Event services</h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                @if($scopeSchedulePublished || ($isAdminPreview ?? false))
                <a href="{{ route('tenant.fest.schedule', ['event' => $event->id]) }}" class="p-4 bg-slate-900/60 border border-slate-800 rounded-2xl hover:border-amber-500/50 hover:bg-slate-900 transition font-semibold">Event Schedule <span class="float-right text-amber-400">→</span></a>
                @else
                <div class="p-4 bg-slate-900/30 border border-slate-800/60 rounded-2xl text-sm text-white/40">Schedule not published</div>
                @endif
                @if($event->status === 'completed')
                <a href="{{ route('tenant.fest.live', ['event' => $event->id]) }}" class="p-4 bg-slate-900/60 border border-slate-800 rounded-2xl hover:border-amber-500/50 hover:bg-slate-900 transition font-semibold">Final Standings <span class="float-right text-white/30">→</span></a>
                @else
                <a href="{{ route('tenant.fest.live', ['event' => $event->id]) }}" class="p-4 bg-slate-900/60 border border-slate-800 rounded-2xl hover:border-amber-500/50 hover:bg-slate-900 transition font-semibold">Live Event <span class="float-right text-red-400">●</span></a>
                @endif
                @if($scopeResultsPublished || ($isAdminPreview ?? false))
                <a href="{{ route('tenant.fest.scoreboard', ['event' => $event->id]) }}" class="p-4 bg-slate-900/60 border border-slate-800 rounded-2xl hover:border-amber-500/50 hover:bg-slate-900 transition font-semibold">Event Scoreboard <span class="float-right text-amber-400">→</span></a>
                <a href="{{ route('tenant.fest.results', ['event' => $event->id, 'tab' => 'toppers']) }}" class="p-4 bg-slate-900/60 border border-slate-800 rounded-2xl hover:border-amber-500/50 hover:bg-slate-900 transition font-semibold">Topper Highlights <span class="float-right text-amber-400">→</span></a>
                <a href="{{ route('tenant.fest.results', ['event' => $event->id]) }}" class="p-4 bg-slate-900/60 border border-slate-800 rounded-2xl hover:border-amber-500/50 hover:bg-slate-900 transition font-semibold">Detailed Results <span class="float-right text-amber-400">→</span></a>
                @elseif(($publishedItemCount ?? 0) > 0)
                <a href="{{ route('tenant.fest.scoreboard', ['event' => $event->id]) }}" class="p-4 bg-slate-900/60 border border-slate-800 rounded-2xl hover:border-amber-500/50 hover:bg-slate-900 transition font-semibold">Event Scoreboard <span class="float-right text-amber-400">→</span></a>
                <a href="{{ route('tenant.fest.results', ['event' => $event->id, 'tab' => 'item']) }}" class="p-4 bg-slate-900/60 border border-slate-800 rounded-2xl hover:border-amber-500/50 hover:bg-slate-900 transition font-semibold">Item Results <span class="float-right text-amber-400">→</span></a>
                @else
                <div class="p-4 bg-slate-900/30 border border-slate-800/60 rounded-2xl text-sm text-white/40">🔒 Scoreboard disabled</div>
                @endif
                <a href="{{ route('tenant.fest.search', $event->id) }}" class="p-4 bg-slate-900/60 border border-slate-800 rounded-2xl hover:border-amber-500/50 hover:bg-slate-900 transition font-semibold">Search Participant <span class="float-right text-amber-400">→</span></a>
                @if($event->manual_pdf_path)<a href="{{ route('tenant.fest.manual', $event->id) }}" class="p-4 bg-slate-900/60 border border-slate-800 rounded-2xl hover:border-amber-500/50 hover:bg-slate-900 transition font-semibold">Event Manual <span class="float-right text-amber-400">PDF</span></a>@endif
                @if($event->record_tracking_enabled)<a href="{{ route('tenant.fest.records', $event->id) }}" class="p-4 bg-slate-900/60 border border-slate-800 rounded-2xl hover:border-amber-500/50 hover:bg-slate-900 transition font-semibold">Athletic Records <span class="float-right text-amber-400">→</span></a>@endif
            </div>
        </section>

        @if(($scopeResultsPublished || ($publishedItemCount ?? 0) > 0 || ($isAdminPreview ?? false)) && $recentResults->isNotEmpty())
        <section class="mt-10" aria-labelledby="recent-results-title">
            <div class="flex items-end justify-between gap-4 mb-4">
                <div><p class="text-xs font-bold uppercase tracking-widest text-amber-400">Recently published</p><h2 id="recent-results-title" class="text-2xl font-bold mt-1 text-white">Latest results</h2></div>
                <a href="{{ route('tenant.fest.results', ['event' => $event->id, 'tab' => 'item']) }}" class="text-sm font-bold text-amber-400 hover:underline shrink-0">All item results →</a>
            </div>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($recentResults as $itemGroup)
                <a href="{{ route('tenant.fest.item-results', [$event->id, $itemGroup['item_id']]) }}" class="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden hover:border-amber-500/50 hover:bg-slate-900 transition">
                    <div class="px-4 py-3 border-b border-slate-800/60">
                        <h3 class="font-bold text-white uppercase">{{ $itemGroup['item'] }}</h3>
                    </div>
                    <div class="flex flex-wrap">
                        @foreach($itemGroup['winners'] as $winner)
                        @php $roster = ($winner['team'] ?? []) ?: [['name' => $winner['participant'], 'photo' => $winner['photo'] ?? null]]; @endphp
                        <div class="flex gap-3 p-4 flex-1 min-w-[16rem] border-l border-slate-800/60 first:border-l-0">
                            <div class="shrink-0">
                                @if($winner['position'] <= 3)
                                <img src="{{ asset('images/fest/medals/rank-'.$winner['position'].'.webp') }}" alt="Rank {{ $winner['position'] }}" class="w-12 h-12">
                                @else
                                <span class="w-12 h-12 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center justify-center font-extrabold" aria-label="Rank {{ $winner['position'] }}">#{{ $winner['position'] }}</span>
                                @endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="grid grid-cols-[repeat(auto-fill,minmax(88px,1fr))] gap-2.5">
                                    @foreach($roster as $member)
                                    <div class="flex flex-col items-center gap-1 w-20">
                                        @if($member['photo'] ?? null)
                                        <img src="{{ $member['photo'] }}" alt="" class="w-20 h-20 rounded-xl object-cover object-top border-2 border-slate-700/60 shadow-md shadow-black/30">
                                        @else
                                        <span class="w-20 h-20 rounded-xl bg-amber-500/15 text-amber-300 flex items-center justify-center font-bold text-lg border-2 border-slate-700/60 shadow-md shadow-black/30">{{ strtoupper(substr($member['name'] ?? '?', 0, 1)) }}</span>
                                        @endif
                                        <span class="text-[11px] font-semibold leading-tight text-white/90 text-center line-clamp-2 uppercase">{{ $member['name'] ?? '—' }}</span>
                                    </div>
                                    @endforeach
                                </div>
                                <p class="text-xs text-white/40 mt-3 uppercase">{{ $winner['school'] }}</p>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </a>
                @endforeach
            </div>
        </section>
        @endif

        @if($itemGroups->isNotEmpty())
        @php
            $allItems = $itemGroups->pluck('items')->flatten();
            $itemCategories = $allItems->map(fn ($item) => $item->class_group ?: $item->age_group ?: $item->category)->filter()->unique()->sort()->values();
            $itemModes = $allItems->pluck('participant_type')->filter()->unique()->sort()->values();
        @endphp
        <section class="mt-10" aria-labelledby="item-finder-title">
            <div class="mb-4"><p class="text-xs font-bold uppercase tracking-widest text-amber-400">Event item finder</p><h2 id="item-finder-title" class="text-2xl font-bold mt-1 text-white">Search schedules and results</h2></div>
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-3 sm:p-4 grid md:grid-cols-[1fr_auto_auto] gap-3">
                <label><span class="sr-only">Search event items</span><input id="event-item-search" type="search" placeholder="Search item name or head" class="w-full rounded-xl border-slate-700 bg-slate-950 text-white placeholder:text-white/30 text-sm focus:border-amber-500 focus:ring-amber-500"></label>
                <label><span class="sr-only">Filter by category</span><select id="event-item-category" class="w-full rounded-xl border-slate-700 bg-slate-950 text-white text-sm focus:border-amber-500 focus:ring-amber-500"><option value="">All categories</option>@foreach($itemCategories as $category)<option value="{{ Str::lower($category) }}">{{ $categoryLabels[$category] ?? strtoupper($category) }}</option>@endforeach</select></label>
                <label><span class="sr-only">Filter by participant type</span><select id="event-item-mode" class="w-full rounded-xl border-slate-700 bg-slate-950 text-white text-sm focus:border-amber-500 focus:ring-amber-500"><option value="">Individual & group</option>@foreach($itemModes as $mode)<option value="{{ Str::lower($mode) }}">{{ ucfirst($mode) }}</option>@endforeach</select></label>
            </div>
            <p id="event-item-summary" class="text-xs text-white/40 mt-3" aria-live="polite">Showing {{ $allItems->count() }} items</p>

            <div id="event-item-grid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 mt-4">
                @foreach($allItems as $item)
                @php
                    $itemCategory = $item->class_group ?: $item->age_group ?: $item->category;
                    $isTeam = $item->isTeamItem();
                @endphp
                <article data-event-item data-search="{{ Str::lower(collect([$item->title, $item->head?->name])->filter()->implode(' ')) }}" data-category="{{ Str::lower($itemCategory ?? '') }}" data-mode="{{ Str::lower($item->participant_type ?? 'individual') }}" class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 flex flex-col hover:border-amber-500/40 hover:bg-slate-900 transition">
                    <div class="flex items-start justify-between gap-3">
                        <h3 class="font-bold leading-snug text-white uppercase">{{ $item->title }}</h3>
                        <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold uppercase {{ $isTeam ? 'bg-amber-500/10 text-amber-300 border border-amber-500/30' : 'bg-white/5 text-white/50 border border-slate-700' }}">{{ $item->participant_type ?: 'individual' }}</span>
                    </div>
                    @if($item->head)<p class="text-xs text-white/40 mt-1">{{ $item->head->name }}</p>@endif
                    <div class="flex flex-wrap gap-1.5 mt-3 text-[10px] font-bold uppercase tracking-wide text-white/40">
                        @if($itemCategory)<span class="rounded-full border border-slate-700 px-2 py-1">{{ $categoryLabels[$itemCategory] ?? strtoupper($itemCategory) }}</span>@endif
                        <span class="rounded-full border border-slate-700 px-2 py-1">{{ $item->stage_type === 'on_stage' ? '🎤 On stage' : ($item->stage_type === 'off_stage' ? '📝 Off stage' : 'Stage') }}</span>
                    </div>
                    <div class="mt-auto pt-4 flex gap-2">
                        @if(($scopeSchedulePublished || ($isAdminPreview ?? false)) && $scheduledItemIds->contains($item->id))<a href="{{ route('tenant.fest.item-schedule', [$event->id, $item->id]) }}" class="flex-1 rounded-xl bg-white/10 px-3 py-2 text-center text-xs font-bold text-white hover:bg-white/15">Schedule</a>@endif
                        @if(($item->results_published_at || ($isAdminPreview ?? false)) && !$item->results_hidden)<a href="{{ route('tenant.fest.item-results', [$event->id, $item->id]) }}" class="flex-1 rounded-xl bg-amber-500 px-3 py-2 text-center text-xs font-bold text-slate-950 hover:bg-amber-400">Results</a>@endif
                        @if(!(($scopeSchedulePublished || ($isAdminPreview ?? false)) && $scheduledItemIds->contains($item->id)) && !(($item->results_published_at || ($isAdminPreview ?? false)) && !$item->results_hidden))
                        <span class="flex-1 rounded-xl border border-dashed border-slate-700 px-3 py-2 text-center text-xs font-semibold text-white/30">Not yet published</span>
                        @endif
                    </div>
                </article>
                @endforeach
            </div>
            <div id="event-item-empty" class="hidden rounded-2xl border border-dashed border-slate-700 p-8 text-center text-sm text-white/40 mt-4">No event items match those filters.</div>
        </section>
        @endif
    </div>
</section>

@if($itemGroups->isNotEmpty())
<script>
(() => {
    const search = document.getElementById('event-item-search');
    const category = document.getElementById('event-item-category');
    const mode = document.getElementById('event-item-mode');
    const items = [...document.querySelectorAll('[data-event-item]')];
    const summary = document.getElementById('event-item-summary');
    const empty = document.getElementById('event-item-empty');
    const apply = () => {
        const query = search.value.trim().toLocaleLowerCase();
        let count = 0;
        items.forEach(item => {
            const visible = (!query || item.dataset.search.includes(query)) && (!category.value || item.dataset.category === category.value) && (!mode.value || item.dataset.mode === mode.value);
            item.hidden = !visible;
            if (visible) count++;
        });
        summary.textContent = `Showing ${count} ${count === 1 ? 'item' : 'items'}`;
        empty.classList.toggle('hidden', count !== 0);
    };
    [search, category, mode].forEach(control => control.addEventListener(control === search ? 'input' : 'change', apply));
})();
</script>
@endif
@endsection
