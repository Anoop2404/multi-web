@extends('layouts.public-event-tv')

@section('content')
<style>
    /* The track holds every section stacked in normal flow; JS drives its transform
       continuously (rAF), so no CSS transition here — a transition would lag behind
       per-frame updates and fight the animation instead of smoothing it. */
    #tv-viewport { position: relative; overflow: hidden; }
    #tv-scroll-track { position: absolute; top: 0; left: 0; right: 0; }
    [data-tv-section] { padding-bottom: 2rem; }
    [data-tv-section]:last-child { padding-bottom: 0; }
    @media (max-height: 800px) {
        #tv-root { padding-top: .5rem; padding-bottom: .5rem; }
        [data-tv-header] { margin-bottom: .5rem; padding-bottom: .375rem; }
        [data-tv-title] { font-size: 1.25rem; margin-top: .125rem; }
        [data-tv-controls] { margin-top: .375rem; }
    }
</style>
<div class="px-8 py-3 h-full flex flex-col" id="tv-root" data-scroll-speed="55" data-dwell-ms="2500" data-min-loop-ms="60000" data-max-loop-ms="240000">
    <header data-tv-header class="flex items-start justify-between gap-8 mb-3 pb-2 border-b border-slate-800 shrink-0">
        <div class="min-w-0">
            <p class="text-amber-400 font-extrabold uppercase tracking-widest text-sm">{{ $tenant->name ?? 'Sahodaya' }} · Results Display</p>
            <h1 data-tv-title class="text-2xl font-extrabold font-heading text-white leading-tight line-clamp-1">{{ $event->title }}</h1>
        </div>
        <div class="text-right shrink-0">
            <div id="tv-clock" class="text-2xl font-mono font-extrabold text-amber-400 tracking-wider">--:--:--</div>
            <p class="text-sm text-slate-400">{{ $event->status === 'completed' ? 'Final results' : ($isPublished ? 'Published results' : 'Provisional — not yet published') }}</p>
        </div>
    </header>

    {{-- Fixed above the scrolling viewport (not inside it) — a board can run to many
         rows, and once scrolled a few rows in, the section's own heading would
         otherwise have scrolled away with it, leaving no indication of which board or
         category is on screen. JS keeps this in sync with whichever section is
         currently under the top of the viewport, so the identity stays visible
         throughout the scroll, not just at the moment it first comes into view. --}}
    <div id="tv-section-label" class="mb-2 shrink-0" hidden>
        <div class="flex items-baseline justify-between gap-4 pb-1.5 border-b border-slate-800">
            <h2 id="tv-section-label-title" class="text-xl font-extrabold text-white"></h2>
            <span id="tv-section-label-subtitle" class="text-sm text-slate-400 font-semibold shrink-0"></span>
        </div>
        {{-- Duplicates fest-medal-board.blade.php's own header row markup/grid template
             verbatim (kept in sync by hand, not shared, so this TV-only fixed label
             never risks changing that partial's shared layout) — shown only while a
             board/schools section is active, so it always names what each column
             means even after the board's own header has scrolled out of view below. --}}
        {{-- visibility, not the hidden attribute: this row's SPACE must stay reserved
             even while showing a winners section, or the label bar's own height would
             change as sections change — shrinking/growing the viewport height JS
             measured once at load, and throwing off every waypoint computed from it. --}}
        {{-- Column widths must match fest-medal-board.blade.php's actual row grid
             exactly (grid-cols-[4.5rem_1fr_repeat(4,5rem)_7rem]) or this header
             visually misaligns with the real rows scrolling underneath it — only the
             row's own height/padding shrinks here, not its column widths. --}}
        <div id="tv-section-label-columns" class="grid grid-cols-[4.5rem_1fr_repeat(4,5rem)_7rem] gap-2 px-5 py-1 mt-1.5 bg-white/5 border border-slate-800 rounded-t-xl text-xs font-extrabold uppercase tracking-wider text-slate-400" style="visibility:hidden">
            <span class="self-center">Rank</span>
            <span class="self-center">School</span>
            <span class="flex items-center justify-center gap-1"><img src="{{ asset('images/fest/medals/rank-1.webp') }}" alt="Points from 1st place" class="w-5 h-5"><span class="normal-case text-[0.65rem] font-semibold tracking-normal text-slate-500">pts</span></span>
            <span class="flex items-center justify-center gap-1"><img src="{{ asset('images/fest/medals/rank-2.webp') }}" alt="Points from 2nd place" class="w-5 h-5"><span class="normal-case text-[0.65rem] font-semibold tracking-normal text-slate-500">pts</span></span>
            <span class="flex items-center justify-center gap-1"><img src="{{ asset('images/fest/medals/rank-3.webp') }}" alt="Points from 3rd place" class="w-5 h-5"><span class="normal-case text-[0.65rem] font-semibold tracking-normal text-slate-500">pts</span></span>
            <span class="flex items-center justify-center gap-1"><span>Grade</span><span class="normal-case text-[0.65rem] font-semibold tracking-normal text-slate-500">pts</span></span>
            <span class="self-center text-right">Total Points</span>
        </div>
    </div>

    <div class="flex-1 relative overflow-hidden" id="tv-viewport">
        <div id="tv-scroll-track">
            @foreach($sections as $section)
            <section data-tv-section data-type="{{ $section['type'] }}" data-title="{{ $section['title'] ?? '' }}" data-subtitle="{{ $section['subtitle'] ?? '' }}">
                @if($section['type'] === 'board')
                    @include('public.fest.partials.fest-medal-board', ['rows' => $section['rows'], 'hideHeader' => true])
                @elseif($section['type'] === 'schools')
                    @include('public.fest.partials.fest-medal-board', ['rows' => $section['rows'], 'showMedalRank' => false, 'hideHeader' => true])
                @elseif($section['type'] === 'winners')
                <div class="grid grid-cols-1 gap-4">
                    @foreach($section['items'] as $itemGroup)
                    @include('public.fest.partials.fest-winner-item-card-tv')
                    @endforeach
                </div>
                @else
                <div class="rounded-3xl bg-slate-900 border border-slate-800 p-14 text-center shadow-xl">
                    <span class="w-20 h-20 rounded-2xl bg-amber-500/10 border border-amber-500/20 mx-auto mb-4 flex items-center justify-center text-base font-extrabold text-amber-300" aria-hidden="true">WAIT</span>
                    <h2 class="text-3xl font-bold text-white">Results Coming Soon</h2>
                    <p class="text-lg text-slate-400 mt-2 max-w-md mx-auto">Standings and winners will appear here as soon as the event committee publishes results.</p>
                </div>
                @endif
            </section>
            @endforeach
        </div>
    </div>

    <div data-tv-controls class="flex items-center justify-center gap-2 mt-2 shrink-0" aria-label="Display controls">
        @if(count($sections) > 1)
        <button type="button" data-tv-prev class="w-8 h-8 rounded-lg border border-slate-700 bg-slate-900 text-white/70 hover:text-white hover:border-amber-500/50 text-sm" aria-label="Previous section">←</button>
        <button type="button" data-tv-pause class="min-w-24 h-8 rounded-lg border border-slate-700 bg-slate-900 px-3 text-xs font-bold text-white/70 hover:text-white hover:border-amber-500/50" aria-pressed="false">Pause</button>
        @endif
        <button type="button" data-tv-fullscreen class="min-w-24 h-8 rounded-lg border border-slate-700 bg-slate-900 px-3 text-xs font-bold text-white/70 hover:text-white hover:border-amber-500/50">Fullscreen</button>
        @if(count($sections) > 1)
        <button type="button" data-tv-next class="w-8 h-8 rounded-lg border border-slate-700 bg-slate-900 text-white/70 hover:text-white hover:border-amber-500/50 text-sm" aria-label="Next section">→</button>
        @endif
    </div>
</div>

<script>
(() => {
    const root = document.getElementById('tv-root');
    const clock = document.getElementById('tv-clock');
    const viewport = document.getElementById('tv-viewport');
    const track = document.getElementById('tv-scroll-track');
    const sections = Array.from(document.querySelectorAll('[data-tv-section]'));
    const label = document.getElementById('tv-section-label');
    const labelTitle = document.getElementById('tv-section-label-title');
    const labelSubtitle = document.getElementById('tv-section-label-subtitle');
    const labelColumns = document.getElementById('tv-section-label-columns');
    const prev = document.querySelector('[data-tv-prev]');
    const next = document.querySelector('[data-tv-next]');
    const pause = document.querySelector('[data-tv-pause]');
    const fullscreen = document.querySelector('[data-tv-fullscreen]');

    // Whichever section's rows currently sit at (or just above) the top of the
    // viewport, by scroll position — independent of the dwell/waypoint state machine
    // below, so it stays correct through prev/next jumps too, not just the automatic
    // scroll.
    let activeSection = null;
    const updateLabel = (scrollY) => {
        let candidate = sections[0];
        for (const el of sections) {
            if (el.offsetTop <= scrollY + 1) candidate = el; else break;
        }
        if (candidate === activeSection) return;
        activeSection = candidate;
        const title = candidate?.dataset.title || '';
        const type = candidate?.dataset.type || '';
        if (label) label.hidden = ! title;
        if (labelTitle) labelTitle.textContent = title;
        if (labelSubtitle) labelSubtitle.textContent = candidate?.dataset.subtitle || '';
        if (labelColumns) labelColumns.style.visibility = (type === 'board' || type === 'schools') ? 'visible' : 'hidden';
    };

    const speedPxPerSec = parseFloat(root.dataset.scrollSpeed) || 55;
    const dwellMs = parseInt(root.dataset.dwellMs, 10) || 2500;
    const minLoopMs = parseInt(root.dataset.minLoopMs, 10) || 60000;
    const maxLoopMs = parseInt(root.dataset.maxLoopMs, 10) || 240000;

    const updateClock = () => {
        clock.textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true});
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
    });

    // Nothing worth scrolling through (a single section, often the pre-publish
    // "waiting" card) — just poll for new data at a similar cadence to the old
    // single-slide fallback, so the screen notices results appearing.
    if (sections.length <= 1) {
        updateLabel(0);
        setTimeout(() => window.location.reload(), 30000);
        return;
    }

    // Settle the label bar into its real, final layout (title + reserved column-header
    // row space) BEFORE measuring the viewport below — otherwise the bar's height
    // would still be in its initial hidden/collapsed markup state, and every
    // measurement taken here would go stale the instant setY() first shows it.
    updateLabel(0);

    const maxScrollY = Math.max(0, track.scrollHeight - viewport.clientHeight);

    // Waypoints: the scroll positions where each section's heading sits at the top of
    // the viewport — the pauses here are what stand in for the old fixed-interval
    // slide dwell. The final waypoint is the true bottom of the track (a very tall
    // final section may end up cut off mid-scroll before the loop reloads, same
    // trade-off the old per-page cap made deliberately — see $categoryBoardRowCap in
    // FestPortalController::tv()).
    const waypoints = sections
        .map(el => Math.min(el.offsetTop, maxScrollY))
        .filter((y, i, arr) => i === 0 || y > arr[i - 1]);
    if (waypoints[waypoints.length - 1] < maxScrollY) waypoints.push(maxScrollY);

    if (maxScrollY <= 0) {
        // Every section already fits on screen at once — nothing to scroll, just
        // refresh periodically to pick up new data.
        updateLabel(0);
        setTimeout(() => window.location.reload(), 30000);
        return;
    }

    // Calibrate scroll speed so one full loop (dwells + scrolling) lands within
    // [minLoopMs, maxLoopMs] regardless of how much content this event happens to
    // have — a short event isn't gone in a few seconds, and a huge one doesn't take
    // ten minutes to loop back to "Latest Item Winners".
    let speed = speedPxPerSec;
    const totalDwellMs = waypoints.length * dwellMs;
    const naturalLoopMs = totalDwellMs + (maxScrollY / speed) * 1000;
    if (naturalLoopMs < minLoopMs) {
        speed = maxScrollY / (Math.max(minLoopMs - totalDwellMs, 1000) / 1000);
    } else if (naturalLoopMs > maxLoopMs) {
        speed = maxScrollY / (Math.max(maxLoopMs - totalDwellMs, 1000) / 1000);
    }

    // arrivedIndex: the waypoint we're currently dwelling at (or just departed from).
    // Starts at 0 — the track begins at y=0, i.e. already "arrived" at the first
    // section — so the very first thing that happens is a dwell, not a zero-distance
    // scroll-then-immediately-redwell.
    let arrivedIndex = 0;
    let y = 0;
    let paused = false;
    let dwelling = true;
    let dwellUntil = performance.now() + dwellMs;
    let lastFrame = null;

    const setY = (value) => {
        y = value;
        track.style.transform = `translateY(${-y}px)`;
        updateLabel(y);
    };

    const jumpTo = (index) => {
        arrivedIndex = Math.max(0, Math.min(index, waypoints.length - 1));
        setY(waypoints[arrivedIndex]);
        dwelling = true;
        dwellUntil = performance.now() + dwellMs;
    };

    const tick = (now) => {
        if (!paused) {
            if (dwelling) {
                if (now >= dwellUntil) {
                    if (arrivedIndex >= waypoints.length - 1) {
                        // Reached the bottom and finished dwelling there — reload to
                        // pick up any newly published results and restart the loop.
                        window.location.reload();
                        return;
                    }
                    dwelling = false;
                }
            } else {
                const dt = lastFrame ? now - lastFrame : 0;
                const target = waypoints[arrivedIndex + 1];
                setY(Math.min(y + speed * (dt / 1000), target));
                if (y >= target - 0.5) {
                    arrivedIndex++;
                    dwelling = true;
                    dwellUntil = now + dwellMs;
                }
            }
        }
        lastFrame = now;
        requestAnimationFrame(tick);
    };

    setY(0);
    lastFrame = performance.now();
    requestAnimationFrame(tick);

    prev?.addEventListener('click', () => jumpTo(arrivedIndex - 1));
    next?.addEventListener('click', () => jumpTo(arrivedIndex + 1));
    pause?.addEventListener('click', () => {
        paused = !paused;
        pause.textContent = paused ? 'Resume' : 'Pause';
        pause.setAttribute('aria-pressed', paused ? 'true' : 'false');
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'ArrowLeft') jumpTo(arrivedIndex - 1);
        if (event.key === 'ArrowRight') jumpTo(arrivedIndex + 1);
        if (event.key === ' ') { event.preventDefault(); pause?.click(); }
    });
})();
</script>
@endsection
