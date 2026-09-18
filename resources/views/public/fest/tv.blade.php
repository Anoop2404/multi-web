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
    @media (max-height: 800px) {
        #tv-root { padding-top: .75rem; padding-bottom: .75rem; }
        [data-tv-header] { margin-bottom: .75rem; padding-bottom: .5rem; }
        [data-tv-title] { font-size: 2.25rem; margin-top: .125rem; }
        [data-tv-controls] { margin-top: .5rem; }
    }
</style>
<div class="px-10 py-5 h-full flex flex-col" id="tv-root" data-interval-ms="8000">
    <header data-tv-header class="flex items-start justify-between gap-8 mb-5 pb-4 border-b border-slate-800 shrink-0">
        <div class="min-w-0">
            <p class="text-amber-400 font-extrabold uppercase tracking-widest text-lg">{{ $tenant->name ?? 'Sahodaya' }} · Results Display</p>
            <h1 data-tv-title class="text-5xl font-extrabold font-heading text-white mt-1 leading-tight line-clamp-2">{{ $event->title }}</h1>
        </div>
        <div class="text-right shrink-0">
            <div id="tv-clock" class="text-4xl font-mono font-extrabold text-amber-400 tracking-wider">--:--:--</div>
            <p class="text-lg text-slate-400 mt-1">{{ $event->status === 'completed' ? 'Final results' : ($isPublished ? 'Published results' : 'Provisional — not yet published') }}</p>
        </div>
    </header>

    <div class="flex-1 relative overflow-hidden">
        @foreach($slides as $index => $slide)
        <section data-tv-slide aria-hidden="{{ $index === 0 ? 'false' : 'true' }}" style="transform: translateX({{ $index * 100 }}%)">
            @if($slide['type'] !== 'waiting')
            <div class="flex items-baseline justify-between gap-4 mb-4">
                <h2 class="text-4xl font-extrabold text-white">{{ $slide['title'] }}</h2>
                @if($slide['subtitle'])<span class="text-lg text-slate-400 font-semibold shrink-0">{{ $slide['subtitle'] }}</span>@endif
            </div>
            @endif

            @if($slide['type'] === 'board')
                @include('public.fest.partials.fest-medal-board', ['rows' => $slide['rows']])
            @elseif($slide['type'] === 'schools')
                @include('public.fest.partials.fest-medal-board', ['rows' => $slide['rows'], 'showMedalRank' => false])
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

    <div data-tv-controls class="flex items-center justify-center gap-3 mt-4 shrink-0" aria-label="Display controls">
        @if(count($slides) > 1)
        <button type="button" data-tv-prev class="w-9 h-9 rounded-xl border border-slate-700 bg-slate-900 text-white/70 hover:text-white hover:border-amber-500/50" aria-label="Previous slide">←</button>
        <span id="tv-slide-count" class="min-w-20 text-center text-xs font-mono font-bold text-slate-400">1 / {{ count($slides) }}</span>
        <button type="button" data-tv-pause class="min-w-24 h-9 rounded-xl border border-slate-700 bg-slate-900 px-3 text-xs font-bold text-white/70 hover:text-white hover:border-amber-500/50" aria-pressed="false">Pause</button>
        @endif
        <button type="button" data-tv-fullscreen class="min-w-24 h-9 rounded-xl border border-slate-700 bg-slate-900 px-3 text-xs font-bold text-white/70 hover:text-white hover:border-amber-500/50">Fullscreen</button>
        @if(count($slides) > 1)
        <button type="button" data-tv-next class="w-9 h-9 rounded-xl border border-slate-700 bg-slate-900 text-white/70 hover:text-white hover:border-amber-500/50" aria-label="Next slide">→</button>
        @endif
    </div>
</div>

<script>
(() => {
    const root = document.getElementById('tv-root');
    const clock = document.getElementById('tv-clock');
    const slides = Array.from(document.querySelectorAll('[data-tv-slide]'));
    const counter = document.getElementById('tv-slide-count');
    const previous = document.querySelector('[data-tv-prev]');
    const next = document.querySelector('[data-tv-next]');
    const pause = document.querySelector('[data-tv-pause]');
    const fullscreen = document.querySelector('[data-tv-fullscreen]');
    const intervalMs = parseInt(root.dataset.intervalMs, 10) || 8000;
    let current = 0;
    let paused = false;
    let rotationTimer = null;

    const updateClock = () => {
        clock.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true});
    };

    const showSlide = (index) => {
        current = (index + slides.length) % slides.length;
        slides.forEach((el, i) => {
            el.style.transform = `translateX(${(i - current) * 100}%)`;
            el.setAttribute('aria-hidden', i === current ? 'false' : 'true');
        });
        if (counter) counter.textContent = `${current + 1} / ${slides.length}`;
    };

    const scheduleRotation = () => {
        clearTimeout(rotationTimer);
        if (paused || slides.length <= 1) return;
        rotationTimer = setTimeout(() => {
            if (current >= slides.length - 1) {
                window.location.reload();
                return;
            }
            showSlide(current + 1);
            scheduleRotation();
        }, intervalMs);
    };

    const moveManually = (step) => {
        showSlide(current + step);
        scheduleRotation();
    };

    updateClock();
    setInterval(updateClock, 1000);

    fullscreen?.addEventListener('click', async () => {
        if (document.fullscreenElement) await document.exitFullscreen();
        else await document.documentElement.requestFullscreen();
    });
    document.addEventListener('fullscreenchange', () => {
        if (fullscreen) fullscreen.textContent = document.fullscreenElement ? 'Exit full' : 'Fullscreen';
    });
    document.addEventListener('keydown', event => {
        if (event.key.toLocaleLowerCase() === 'f') fullscreen?.click();
        if (slides.length <= 1) return;
        if (event.key === 'ArrowLeft') moveManually(-1);
        if (event.key === 'ArrowRight') moveManually(1);
        if (event.key === ' ') { event.preventDefault(); pause?.click(); }
    });

    if (slides.length > 1) {
        previous?.addEventListener('click', () => moveManually(-1));
        next?.addEventListener('click', () => moveManually(1));
        pause?.addEventListener('click', () => {
            paused = !paused;
            pause.textContent = paused ? 'Resume' : 'Pause';
            pause.setAttribute('aria-pressed', paused ? 'true' : 'false');
            scheduleRotation();
        });
        scheduleRotation();
    } else {
        // Nothing to rotate through (often the pre-publish "waiting" card) — just poll
        // for new data at a similar cadence so the screen notices results appearing.
        setTimeout(() => window.location.reload(), 30000);
    }
})();
</script>
@endsection
