@extends('layouts.public-event')

@section('content')
<section class="py-8 sm:py-12 px-4 bg-slate-950 text-white min-h-screen">
    <div class="max-w-[100rem] mx-auto">
        @php
            $heroBadges = array_values(array_filter([$eventContext['phase'], $eventContext['region'], $event->resolvedVenueName()]));
            $itemCategories = $allItems->map(fn ($item) => $item->class_group ?: $item->age_group ?: $item->category)->filter()->unique()->sort()->values();
            $itemModes = $allItems->pluck('participant_type')->filter()->unique()->sort()->values();
        @endphp
        @include('public.fest.partials.page-hero', [
            'eyebrow' => 'Item Finder',
            'title' => $event->title,
            'subtitle' => 'Search every competition item and jump straight to its schedule or results.',
            'badges' => $heroBadges,
        ])

        <div class="flex justify-end mt-4">
            <a href="{{ route('tenant.fest.results', ['event' => $event->id, 'tab' => 'item']) }}" class="text-sm font-semibold text-amber-400 hover:underline shrink-0">Item-wise results →</a>
        </div>

        <section class="mt-6" aria-labelledby="item-finder-title">
            <h2 id="item-finder-title" class="sr-only">Search schedules and results</h2>
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
                        @if($genderLabel = \App\Support\FestSportsAgeGroup::genderLabel($item->gender))<span class="rounded-full border border-slate-700 px-2 py-1">{{ $genderLabel }}</span>@endif
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
    </div>
</section>

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
@endsection
