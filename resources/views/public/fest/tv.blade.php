@extends('layouts.public-event-tv')

@section('content')
<style>
    /* Slides sit stacked at the same position, each pushed off-screen left/right by its
       distance from the active index — moving between them is a horizontal slide, not an
       instant swap. JS only ever changes this transform value; the transition here is what
       animates it. */
    [data-tv-slide] {
        position: absolute;
        inset: 0;
        transition: transform 0.7s cubic-bezier(0.65, 0, 0.35, 1);
    }
</style>
<div class="px-10 py-5 h-full flex flex-col" id="tv-root" data-interval-ms="8000">
    <header class="flex items-start justify-between gap-8 mb-5 pb-4 border-b border-slate-800 shrink-0">
        <div class="min-w-0">
            <p class="text-amber-400 font-extrabold uppercase tracking-widest text-sm">{{ $tenant->name ?? 'Sahodaya' }} · Live Screen</p>
            <h1 class="text-3xl font-extrabold font-heading text-white mt-1 leading-tight truncate">{{ $event->title }}</h1>
        </div>
        <div class="text-right shrink-0">
            <div id="tv-clock" class="text-2xl font-mono font-extrabold text-amber-400 tracking-wider">--:--:--</div>
            <p class="text-sm text-slate-400 mt-1">{{ $isPublished ? 'Official results' : 'Provisional — not yet published' }}</p>
        </div>
    </header>

    <div class="flex-1 relative overflow-hidden">
        @foreach($slides as $index => $slide)
        <section data-tv-slide style="transform: translateX({{ $index * 100 }}%)">
            @if($slide['type'] !== 'waiting')
            <div class="flex items-baseline justify-between gap-4 mb-4">
                <h2 class="text-2xl font-extrabold text-white">{{ $slide['title'] }}</h2>
                @if($slide['subtitle'])<span class="text-sm text-slate-400 font-semibold shrink-0">{{ $slide['subtitle'] }}</span>@endif
            </div>
            @endif

            @if($slide['type'] === 'board')
                @include('public.fest.partials.fest-medal-board', ['rows' => $slide['rows']])
            @elseif($slide['type'] === 'schools')
                @include('public.fest.partials.fest-school-list', ['schools' => $slide['schools']])
            @elseif($slide['type'] === 'winners')
            <div class="grid grid-cols-1 gap-4">
                @foreach($slide['items'] as $itemGroup)
                    @include('public.fest.partials.fest-winner-item-card', ['rosterLimit' => 14])
                @endforeach
            </div>
            @else
            <div class="rounded-3xl bg-slate-900 border border-slate-800 p-14 text-center shadow-xl">
                <span class="w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/20 mx-auto mb-4 flex items-center justify-center text-sm font-extrabold text-amber-300" aria-hidden="true">WAIT</span>
                <h2 class="text-xl font-bold text-white">Results Coming Soon</h2>
                <p class="text-sm text-slate-400 mt-2 max-w-md mx-auto">Standings and winners will appear here as soon as the event committee publishes results.</p>
            </div>
            @endif
        </section>
        @endforeach
    </div>

    @if(count($slides) > 1)
    <div class="flex items-center justify-center gap-2 mt-5 shrink-0" role="presentation">
        @foreach($slides as $index => $slide)
        <span data-tv-dot class="h-1.5 rounded-full transition-all duration-500 {{ $index === 0 ? 'w-8 bg-amber-400' : 'w-1.5 bg-slate-700' }}"></span>
        @endforeach
    </div>
    @endif
</div>

<script>
(() => {
    const root = document.getElementById('tv-root');
    const clock = document.getElementById('tv-clock');
    const slides = Array.from(document.querySelectorAll('[data-tv-slide]'));
    const dots = Array.from(document.querySelectorAll('[data-tv-dot]'));
    const intervalMs = parseInt(root.dataset.intervalMs, 10) || 8000;
    let current = 0;

    const updateClock = () => {
        clock.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true});
    };

    const showSlide = (index) => {
        slides.forEach((el, i) => { el.style.transform = `translateX(${(i - index) * 100}%)`; });
        dots.forEach((el, i) => {
            el.classList.toggle('w-8', i === index);
            el.classList.toggle('bg-amber-400', i === index);
            el.classList.toggle('w-1.5', i !== index);
            el.classList.toggle('bg-slate-700', i !== index);
        });
    };

    updateClock();
    setInterval(updateClock, 1000);

    if (slides.length > 1) {
        // Reload instead of wrapping back to slide 0 — ties "how often we refetch" to
        // "how much there is to show" (more slides = longer cycle) and means the reload
        // always lands at a slide boundary instead of interrupting one mid-view.
        setInterval(() => {
            if (current >= slides.length - 1) {
                window.location.reload();
                return;
            }
            current += 1;
            showSlide(current);
        }, intervalMs);
    } else {
        // Nothing to rotate through (often the pre-publish "waiting" card) — just poll
        // for new data at a similar cadence so the screen notices results appearing.
        setTimeout(() => window.location.reload(), 30000);
    }
})();
</script>
@endsection
