@extends('layouts.public-event')

@section('content')
<section class="py-8 sm:py-12 px-4 bg-slate-950 text-white min-h-screen">
    <div class="max-w-5xl mx-auto">
        @include('public.fest.partials.page-hero', [
            'eyebrow' => $event->title,
            'title' => $school->name,
            'subtitle' => $activeCategoryLabel
                ? $activeCategoryLabel.' results only — every item entered in this category, with rank, grade, and points.'
                : 'Full results roster — every item entered, with rank, grade, and points.',
            'badges' => [],
            'meta' => null,
        ])

        <div class="flex flex-wrap items-center justify-between gap-3 mt-4 mb-8">
            @if($activeCategory)
                <a href="{{ route('tenant.fest.scoreboard', ['event' => $event->id, 'category' => $activeCategory]) }}" class="text-sm font-semibold text-amber-400 hover:underline">← Back to {{ $activeCategoryLabel }}</a>
            @else
                <a href="{{ route('tenant.fest.results', ['event' => $event->id, 'tab' => 'school']) }}" class="text-sm font-semibold text-amber-400 hover:underline">← Back to all schools</a>
            @endif
            <span class="text-2xl font-mono font-extrabold text-amber-400">{{ $schoolRow['total_points'] }} <small class="text-xs text-white/40 font-sans font-bold uppercase tracking-wide">pts total</small></span>
        </div>

        @if($activeCategory)
        <div class="mb-8 rounded-xl border border-amber-500/20 bg-amber-500/5 px-4 py-3 flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs text-amber-200">Showing <strong>{{ $activeCategoryLabel }}</strong> items only.</p>
            <a href="{{ route('tenant.fest.results.school', ['event' => $event->id, 'school' => $school->id]) }}" class="text-xs font-bold text-amber-400 hover:underline shrink-0">View full roster (all categories) →</a>
        </div>
        @endif

        @php
            $grouped = collect($roster)->groupBy('category');
            $rosterCount = collect($roster)->count();
        @endphp
        <div class="mb-8 rounded-2xl border border-slate-800 bg-slate-900/80 p-3 sm:p-4 grid sm:grid-cols-[1fr_auto] gap-3 sticky top-16 z-10 backdrop-blur shadow-xl">
            <label>
                <span class="sr-only">Search this school's results</span>
                <input id="school-roster-search" type="search" placeholder="Search participant or item" class="w-full rounded-xl border-slate-700 bg-slate-950 text-white placeholder:text-white/30 text-sm focus:border-amber-500 focus:ring-amber-500">
            </label>
            @unless($activeCategory)
            <label>
                <span class="sr-only">Filter roster category</span>
                <select id="school-roster-category" class="w-full rounded-xl border-slate-700 bg-slate-950 text-white text-sm focus:border-amber-500 focus:ring-amber-500">
                    <option value="">All categories</option>
                    @foreach($grouped->keys() as $category)<option value="{{ Str::slug($category) }}">{{ $category }}</option>@endforeach
                </select>
            </label>
            @endunless
        </div>
        <p id="school-roster-summary" class="text-xs text-white/45 -mt-5 mb-7" aria-live="polite">Showing {{ $rosterCount }} {{ Str::plural('result', $rosterCount) }}</p>

        <div class="space-y-10">
            @foreach($grouped as $category => $items)
            @php $categoryPoints = collect($items)->sum('points'); @endphp
            <section data-school-roster-section data-category="{{ Str::slug($category) }}" aria-labelledby="cat-{{ Str::slug($category) }}">
                <div class="flex items-center justify-between gap-4 mb-4 pb-2 border-b border-slate-800/80">
                    <h2 id="cat-{{ Str::slug($category) }}" class="text-sm font-bold uppercase tracking-widest text-amber-400">{{ $category }}</h2>
                    <span class="text-xs font-mono font-bold text-amber-300 bg-amber-500/10 border border-amber-500/30 px-3 py-1 rounded-full">{{ $categoryPoints }} <small class="text-white/50 font-sans font-semibold">PTS</small></span>
                </div>
                <div class="grid sm:grid-cols-2 gap-4">
                    @foreach($items as $winner)
                    @php
                        $roster2 = ($winner['team'] ?? []) ?: [['name' => $winner['participant'], 'photo' => $winner['photo'] ?? null]];
                    @endphp
                    <article data-school-roster-card data-search="{{ Str::lower(collect([$winner['item'], collect($roster2)->pluck('name')->implode(' ')])->filter()->implode(' ')) }}" class="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden">
                        <div class="flex items-center justify-between gap-3 px-4 py-3 bg-white/5 border-b border-slate-800">
                            <div class="min-w-0">
                                <p class="font-bold text-white">{{ $winner['item'] }}</p>
                                <p class="text-xs text-white/40">{{ collect([$winner['gender'] ?? null, $winner['participant_type'] ?? null])->filter()->implode(' · ') }}</p>
                            </div>
                            @if($winner['position'] && $winner['position'] <= 3)
                                <img src="{{ asset('images/fest/medals/rank-'.$winner['position'].'.webp') }}" alt="Rank {{ $winner['position'] }}" class="w-10 h-10 shrink-0">
                            @elseif($winner['position'])
                                <span class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-500/30 flex items-center justify-center text-sm font-extrabold shrink-0">#{{ $winner['position'] }}</span>
                            @endif
                        </div>
                        <div class="p-4">
                            <div class="grid grid-cols-[repeat(auto-fill,minmax(88px,1fr))] gap-3 mb-4">
                                @foreach($roster2 as $member)
                                <div class="flex flex-col items-center gap-1.5">
                                    @if($member['photo'] ?? null)
                                        <img src="{{ $member['photo'] }}" alt="" class="w-20 h-20 rounded-xl object-cover object-top border-2 border-slate-700/60 shadow-md shadow-black/30">
                                    @else
                                        <span class="w-20 h-20 rounded-xl bg-amber-500/15 text-amber-300 flex items-center justify-center text-xl font-bold border-2 border-slate-700/60">{{ strtoupper(substr($member['name'] ?? '?', 0, 1)) }}</span>
                                    @endif
                                    <span class="text-xs font-semibold text-white text-center leading-tight">{{ $member['name'] ?? '—' }}</span>
                                </div>
                                @endforeach
                            </div>
                            <div class="flex items-center justify-between pt-3 border-t border-slate-800">
                                @if(!empty($winner['grade']))
                                    <span class="text-xs font-bold text-amber-400 bg-amber-500/10 px-2 py-1 rounded border border-amber-500/30">
                                        Grade {{ $winner['grade'] }}{{ $winner['grade_points'] !== null ? ' · '.$winner['grade_points'].' pts' : '' }}
                                    </span>
                                @else
                                    <span></span>
                                @endif
                                <span class="font-mono font-bold text-white">{{ $winner['points'] }} <small class="text-xs text-white/40">PTS</small></span>
                            </div>
                        </div>
                    </article>
                    @endforeach
                </div>
            </section>
            @endforeach
        </div>
        <p id="school-roster-empty" class="hidden rounded-2xl border border-dashed border-slate-700 p-10 text-center text-white/40">No roster results match that search.</p>
        <div class="mt-8 text-center"><button id="school-roster-load" type="button" class="hidden rounded-xl border border-amber-500/30 bg-amber-500/10 px-5 py-2.5 text-sm font-bold text-amber-300 hover:bg-amber-500/15">Load 25 more</button></div>
    </div>
</section>
<script>
(() => {
    const search = document.getElementById('school-roster-search');
    const category = document.getElementById('school-roster-category');
    const sections = [...document.querySelectorAll('[data-school-roster-section]')];
    const cards = [...document.querySelectorAll('[data-school-roster-card]')];
    const summary = document.getElementById('school-roster-summary');
    const empty = document.getElementById('school-roster-empty');
    const loadMore = document.getElementById('school-roster-load');
    if (!search) return;
    let limit = 25;
    const initialUrl = new URL(window.location.href);
    search.value = initialUrl.searchParams.get('roster_q') || '';
    if (category && [...category.options].some(option => option.value === initialUrl.searchParams.get('roster_category'))) {
        category.value = initialUrl.searchParams.get('roster_category') || '';
    }

    const apply = () => {
        const query = search.value.trim().toLocaleLowerCase();
        const selectedCategory = category?.value || '';
        const filtering = Boolean(query || selectedCategory);
        let matched = 0;
        let shown = 0;
        sections.forEach(section => {
            let shownInSection = 0;
            section.querySelectorAll('[data-school-roster-card]').forEach(card => {
                const matches = (!query || card.dataset.search.includes(query))
                    && (!selectedCategory || section.dataset.category === selectedCategory);
                if (matches) matched++;
                const display = matches && (filtering || shown < limit);
                card.hidden = !display;
                if (display) { shown++; shownInSection++; }
            });
            section.hidden = shownInSection === 0;
        });
        summary.textContent = shown === matched
            ? `Showing ${shown} ${shown === 1 ? 'result' : 'results'}`
            : `Showing ${shown} of ${matched} results`;
        empty.classList.toggle('hidden', matched !== 0);
        loadMore.classList.toggle('hidden', filtering || shown >= matched);
        const nextUrl = new URL(window.location.href);
        query ? nextUrl.searchParams.set('roster_q', query) : nextUrl.searchParams.delete('roster_q');
        selectedCategory ? nextUrl.searchParams.set('roster_category', selectedCategory) : nextUrl.searchParams.delete('roster_category');
        history.replaceState(null, '', nextUrl);
    };

    search.addEventListener('input', () => { limit = 25; apply(); });
    category?.addEventListener('change', () => { limit = 25; apply(); });
    loadMore.addEventListener('click', () => { limit += 25; apply(); });
    apply();
})();
</script>
@endsection
