@extends('layouts.public-event')

@section('content')
<section id="scoreboard-live-root" class="py-6 sm:py-8 px-4 bg-slate-950 text-white min-h-screen"
         data-base-url="{{ route('tenant.fest.scoreboard.data', ['event' => $event->id]) }}"
         data-categories="{{ json_encode(($event->tv_show_overall_standings ?? true) ? array_merge([''], $categories) : $categories) }}"
         data-category-labels="{{ json_encode($categoryLabels) }}"
         data-base-label="{{ $selectedScope['label'] }}"
         data-initial-category="{{ $category ?? '' }}">
    <div class="max-w-[100rem] mx-auto space-y-6">
        <header class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-slate-950 to-slate-900 border border-amber-500/20 p-6 md:p-8 shadow-2xl">
            <div aria-hidden="true" class="absolute -top-32 right-0 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl"></div>
            <div aria-hidden="true" class="absolute -bottom-32 left-0 w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl"></div>
            <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                <div class="space-y-4 min-w-0">
                    <div class="flex items-center gap-3">
                        @if($logo ?? null)
                        <img src="{{ $logo }}" alt="{{ $tenant->name }}" class="w-12 h-12 rounded-2xl object-contain bg-white/10 p-1.5 border border-white/20 shrink-0">
                        @else
                        <span class="w-12 h-12 rounded-2xl bg-amber-400 text-slate-950 flex items-center justify-center font-extrabold shrink-0">#1</span>
                        @endif
                        <div class="min-w-0"><p class="text-xs uppercase tracking-widest text-amber-400 font-extrabold">{{ $tenant->name ?? 'Sahodaya Complex' }}</p><p class="text-[11px] text-slate-400">Official public scoreboard</p></div>
                    </div>
                    <div>
                        <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight font-heading text-white">{{ $event->title }}</h1>
                        <div class="text-xs md:text-sm text-slate-400 flex items-center gap-2 flex-wrap mt-3">
                            <span id="scoreboard-title-category" class="text-amber-300 font-semibold">{{ $scoreboardTitle }}</span>
                            @if($eventContext['phase'])<span class="rounded-full border border-white/10 px-2.5 py-1">{{ $eventContext['phase'] }}</span>@endif
                            @if($eventContext['region'])<span class="rounded-full border border-white/10 px-2.5 py-1">{{ $eventContext['region'] }}</span>@endif
                            @if($event->resolvedVenueName())<span class="rounded-full border border-white/10 px-2.5 py-1">{{ $event->resolvedVenueName() }}</span>@endif
                            @if($event->event_start)<span class="rounded-full border border-white/10 px-2.5 py-1">{{ $event->event_start->format('d M Y') }}</span>@endif
                        </div>
                    </div>
                </div>

                <div class="flex flex-col items-start lg:items-end gap-3 shrink-0 border-t lg:border-t-0 lg:border-l border-slate-800 pt-4 lg:pt-0 lg:pl-8">
                    <span id="scoreboard-publication-badge" class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold {{ $isPublished ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-rose-500/20 text-rose-300 border border-rose-500/30' }}">
                        @if($isPublished)<span class="relative flex h-2 w-2" aria-hidden="true"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-amber-400"></span></span> LIVE STANDINGS @else 🔒 SCOREBOARD DISABLED @endif
                    </span>
                    <div class="text-left lg:text-right">
                        <div id="scoreboard-live-clock" class="text-3xl md:text-4xl font-mono font-extrabold text-amber-400 tracking-wider">--:--:--</div>
                        <p id="scoreboard-refresh-status" class="text-[11px] text-slate-400 mt-1" aria-live="polite">Updates in the background every 30 seconds</p>
                    </div>
                    @if($isPublished)
                    <div class="flex flex-wrap gap-2 justify-start lg:justify-end">
                        <a href="{{ route('tenant.fest.results', ['event' => $event->id, 'tab' => 'toppers']) }}" class="inline-flex text-xs font-extrabold bg-amber-500 hover:bg-amber-400 text-slate-950 px-4 py-2.5 rounded-xl transition">View topper highlights →</a>
                        <a href="{{ route('tenant.fest.results', ['event' => $event->id, 'tab' => 'championship']) }}" class="inline-flex text-xs font-extrabold bg-slate-800 border border-slate-700 hover:border-amber-500 text-white px-4 py-2.5 rounded-xl transition">🏅 Individual Champions →</a>
                    </div>
                    @endif
                </div>
            </div>
        </header>

        @if(\App\Support\FestSchoolGenderStandings::categories($event))
        <div class="flex flex-wrap gap-2 my-4" aria-label="School standings gender">
            @foreach(['' => 'Combined', 'male' => 'Boys', 'female' => 'Girls'] as $genderKey => $genderLabel)
                <a href="{{ route('tenant.fest.scoreboard', array_filter(['event' => $event->id, 'category' => $category, 'gender' => $genderKey])) }}" class="px-4 py-2 rounded-xl border text-sm {{ request('gender', '') === $genderKey ? 'bg-amber-500 text-slate-950 border-amber-500' : 'border-slate-700 text-white' }}">{{ $genderLabel }}</a>
            @endforeach
            <p class="w-full text-xs text-slate-400">Boys/Girls apply to categories enabled by the event administrator.</p>
        </div>
        @endif

        @if(count($categories ?? []))
        <nav id="scoreboard-category-nav" class="flex gap-2 overflow-x-auto rounded-2xl p-3 border border-slate-800 bg-slate-900/70" aria-label="Event category">
            @if($event->tv_show_overall_standings ?? true)
            <a href="{{ route('tenant.fest.scoreboard', array_filter(['event' => $event->id, 'gender' => request('gender')])) }}" data-category=""
               class="shrink-0 px-3.5 py-2 rounded-xl text-xs font-bold border {{ !$category ? 'bg-amber-500 text-slate-950 border-amber-500' : 'bg-slate-800 text-slate-300 border-slate-700 hover:border-amber-500' }}">All Categories</a>
            @endif
            @foreach($categories as $cat)
            <a href="{{ route('tenant.fest.scoreboard', array_filter(['event' => $event->id, 'category' => $cat, 'gender' => request('gender')])) }}" data-category="{{ $cat }}"
               class="shrink-0 px-3.5 py-2 rounded-xl text-xs font-bold border {{ ($category ?? '') === $cat ? 'bg-amber-500 text-slate-950 border-amber-500' : 'bg-slate-800 text-slate-300 border-slate-700 hover:border-amber-500' }}">{{ $categoryLabels[$cat] ?? strtoupper($cat) }}</a>
            @endforeach
        </nav>
        @endif

        <div class="flex justify-end">
            <button type="button" id="scoreboard-refresh-current" class="px-4 py-2 rounded-xl border border-slate-700 bg-slate-800 text-sm font-semibold hover:border-amber-500 disabled:opacity-50">Refresh this tab</button>
        </div>

        <div id="scoreboard-dynamic-content" aria-live="off">
            @include('public.fest.partials.scoreboard-content')
        </div>

        <footer class="pt-6 border-t border-slate-800 text-center flex flex-wrap justify-center gap-5 text-xs">
            <a href="{{ route('tenant.fest.show', ['event' => $event->id]) }}" class="text-slate-400 hover:text-white">← Event page</a>
        </footer>
    </div>
</section>

<script>
(() => {
    const root = document.getElementById('scoreboard-live-root');
    const clock = document.getElementById('scoreboard-live-clock');
    const status = document.getElementById('scoreboard-refresh-status');
    const content = document.getElementById('scoreboard-dynamic-content');
    const titleEl = document.getElementById('scoreboard-title-category');
    const nav = document.getElementById('scoreboard-category-nav');
    const refreshButton = document.getElementById('scoreboard-refresh-current');

    const categories = JSON.parse(root.dataset.categories || '[]'); // '' entry means the fest-wide combined view
    const categoryLabels = JSON.parse(root.dataset.categoryLabels || '{}');
    const baseLabel = root.dataset.baseLabel || '';

    let refreshing = false;
    let lastUpdated = Date.now();
    let current = root.dataset.initialCategory || '';

    const updateClock = () => { clock.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true}); };

    const urlFor = (cat) => {
        const url = new URL(root.dataset.baseUrl, window.location.origin);
        if (cat) url.searchParams.set('category', cat);
        const gender = new URL(window.location.href).searchParams.get('gender');
        if (gender) url.searchParams.set('gender', gender);
        return url.toString();
    };

    const setActiveTab = (cat) => {
        if (!nav) return;
        nav.querySelectorAll('[data-category]').forEach((a) => {
            const active = a.dataset.category === cat;
            a.classList.toggle('bg-amber-500', active);
            a.classList.toggle('text-slate-950', active);
            a.classList.toggle('border-amber-500', active);
            a.classList.toggle('bg-slate-800', !active);
            a.classList.toggle('text-slate-300', !active);
            a.classList.toggle('border-slate-700', !active);
        });
    };

    const setTitle = (cat) => {
        if (!titleEl) return;
        titleEl.textContent = cat ? `${baseLabel} · ${categoryLabels[cat] ?? cat.toUpperCase()}` : baseLabel;
    };

    // Background refreshes avoid a "Loading…" flicker; a manual tab click still shows
    // it because that is an immediate action the visitor is watching.
    const loadCategory = async (cat, {silent = false, updateHistory = false} = {}) => {
        if (refreshing) return;
        refreshing = true;
        refreshButton.disabled = true;
        root.setAttribute('aria-busy', 'true');
        if (!silent) status.textContent = 'Loading…';
        try {
            const response = await fetch(urlFor(cat), {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('Refresh failed');
            const data = await response.json();
            content.innerHTML = data.contentHtml;
            current = cat;
            if (updateHistory) {
                const pageUrl = new URL(window.location.href);
                if (cat) pageUrl.searchParams.set('category', cat);
                else pageUrl.searchParams.delete('category');
                if (pageUrl.href !== window.location.href) history.pushState(null, '', pageUrl);
            }
            setActiveTab(cat);
            setTitle(cat);
            lastUpdated = Date.now();
            status.textContent = 'Updated ' + new Date(data.refreshedAt).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit'});
        } catch (error) {
            status.textContent = 'Update delayed — showing the last confirmed standings';
        } finally {
            refreshing = false;
            refreshButton.disabled = false;
            root.removeAttribute('aria-busy');
        }
    };

    if (nav) {
        nav.addEventListener('click', (e) => {
            const link = e.target.closest('[data-category]');
            if (!link || e.ctrlKey || e.metaKey || e.shiftKey) return; // let modified clicks (open in new tab, etc.) behave normally
            e.preventDefault();
            loadCategory(link.dataset.category, {updateHistory: true});
        });
    }

    refreshButton.addEventListener('click', () => loadCategory(current));
    // Restore the server-rendered category when navigating browser history.
    window.addEventListener('popstate', () => window.location.reload());

    updateClock();
    setInterval(updateClock, 1000);

    // Browsing pages preserve the visitor's chosen category. Rotation belongs only on
    // the dedicated TV display; this page refreshes the current category in place.
    setInterval(() => {
        if (!document.hidden) loadCategory(current, {silent: true});
    }, 60000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && Date.now() - lastUpdated > 60000) loadCategory(current, {silent: true});
    });
})();
</script>
@endsection
