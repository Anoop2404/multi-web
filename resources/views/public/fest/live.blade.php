@extends('layouts.public-event')

@section('content')
@php
    $medalImg = fn ($rank) => $rank >= 1 && $rank <= 3
        ? '<img src="'.asset('images/fest/medals/rank-'.$rank.'.webp').'" alt="Rank '.$rank.'" class="inline-block w-5 h-5 align-middle">'
        : '<span class="font-mono">#'.$rank.'</span>';
@endphp
<section class="py-8 sm:py-12 px-4 bg-slate-950 text-white min-h-screen" id="fest-live-root" data-live-url="{{ route('tenant.fest.live.data', ['event' => $event->id]) }}">
    <div class="max-w-2xl mx-auto">

        <header class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-slate-950 to-slate-900 border border-red-500/20 p-6 sm:p-8 text-center shadow-2xl">
            <div aria-hidden="true" class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-red-500/10 blur-3xl"></div>
            <div aria-hidden="true" class="absolute -left-16 -bottom-16 h-56 w-56 rounded-full bg-amber-500/10 blur-3xl"></div>
            <div class="relative">
                <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-red-500/15 text-red-300 border border-red-500/30">
                    <span class="relative flex h-2 w-2" aria-hidden="true"><span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span><span class="relative inline-flex rounded-full h-2 w-2 bg-red-400"></span></span>
                    LIVE
                </span>
                <h1 class="text-2xl sm:text-3xl font-extrabold font-heading mt-3 text-white">{{ $event->title }}</h1>
                @if($event->resolvedVenueName())<p class="text-sm text-white/50 mt-2">📍 {{ $event->resolvedVenueName() }}</p>@endif
                <p id="live-refresh-badge" class="text-[11px] text-white/30 mt-3" aria-live="polite">Auto-refreshing every 30 seconds</p>
            </div>
        </header>

        <div id="now-performing" class="mt-5 p-5 rounded-2xl bg-amber-500/10 border-2 border-amber-500/40 text-center shadow-[0_0_30px_-5px_rgba(245,158,11,0.35)] @if(!$nowPerforming) hidden @endif">
            @if($nowPerforming)
            <span class="text-xs font-bold uppercase tracking-widest text-amber-400 block mb-2">● Now performing</span>
            <strong class="text-2xl font-extrabold text-white block leading-tight uppercase">{{ $nowPerforming['item_title'] ?? '—' }}</strong>
            @if(!empty($nowPerforming['category_label']))
            <span class="text-amber-300/70 text-xs font-semibold uppercase tracking-wide mt-1 block">{{ $nowPerforming['category_label'] }}</span>
            @endif
            @if(!empty($nowPerforming['show_name']) && !empty($nowPerforming['name']))
            <span class="text-amber-200 text-lg font-semibold mt-1 block">{{ $nowPerforming['name'] }}</span>
            @endif
            @endif
        </div>

        <div class="flex items-center justify-between gap-3 mt-10 mb-3">
            <h2 class="text-xs font-bold uppercase tracking-widest text-amber-400">Event School Standings</h2>
            <span id="school-scoreboard-provisional-badge" class="text-[10px] font-bold uppercase tracking-wider text-amber-300 border border-amber-500/30 bg-amber-500/10 rounded-full px-2 py-0.5 @unless($standingsProvisional ?? false) hidden @endunless">Provisional</span>
        </div>
        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 overflow-hidden">
            <div class="grid grid-cols-[2rem_1fr_repeat(3,2.25rem)_3.5rem] gap-1.5 px-3 py-2 bg-white/5 border-b border-slate-800 text-[10px] font-extrabold uppercase tracking-wider text-slate-400">
                <span>#</span>
                <span>School</span>
                <span class="flex items-center justify-center"><img src="{{ asset('images/fest/medals/rank-1.webp') }}" alt="Gold" class="w-4 h-4"></span>
                <span class="flex items-center justify-center"><img src="{{ asset('images/fest/medals/rank-2.webp') }}" alt="Silver" class="w-4 h-4"></span>
                <span class="flex items-center justify-center"><img src="{{ asset('images/fest/medals/rank-3.webp') }}" alt="Bronze" class="w-4 h-4"></span>
                <span class="text-right">Pts</span>
            </div>
            <ol id="school-scoreboard" class="divide-y divide-slate-800">
                @forelse($scoreboard as $row)
                <li class="grid grid-cols-[2rem_1fr_repeat(3,2.25rem)_3.5rem] gap-1.5 items-center px-3 py-2.5">
                    <span class="flex items-center">{!! $medalImg($row['rank']) !!}</span>
                    <span class="text-white font-semibold text-sm truncate uppercase">{{ $row['school_name'] }}</span>
                    <span class="text-center font-mono font-bold text-amber-300 text-sm">{{ $row['gold'] ?? 0 }}</span>
                    <span class="text-center font-mono font-bold text-slate-300 text-sm">{{ $row['silver'] ?? 0 }}</span>
                    <span class="text-center font-mono font-bold text-amber-600 text-sm">{{ $row['bronze'] ?? 0 }}</span>
                    <span class="text-right font-mono font-extrabold text-white text-sm">{{ $row['total_points'] }}</span>
                </li>
                @empty
                <li class="text-white/40 text-center py-6 font-semibold">{{ $standingsPublished ? 'No scores published yet' : '🔒 Public scoreboard & standings are disabled for this event.' }}</li>
                @endforelse
            </ol>
        </div>

        @if(count($houseScoreboard))
        <h2 class="text-xs font-bold uppercase tracking-widest text-amber-400 mt-10 mb-3">House Standings</h2>
        <ol id="house-scoreboard" class="space-y-2">
            @foreach($houseScoreboard as $row)
            <li class="flex justify-between items-center bg-slate-900/60 border border-slate-800 rounded-xl px-4 py-3">
                <span class="text-white flex items-center gap-2"><span class="inline-block w-3 h-3 rounded-full" style="background:{{ $row['color'] ?? '#fbbf24' }}"></span>{!! $medalImg($row['rank']) !!} {{ $row['house_name'] }}</span>
                <span class="font-mono font-bold text-white">{{ $row['total_points'] }}</span>
            </li>
            @endforeach
        </ol>
        @endif

        @if(count($athleticRecords ?? []))
        <h2 class="text-xs font-bold uppercase tracking-widest text-amber-400 mt-10 mb-3">Athletic Records</h2>
        <ol id="athletic-records" class="space-y-2 text-sm">
            @foreach($athleticRecords as $r)
            <li class="bg-slate-900/60 border border-slate-800 rounded-xl px-4 py-3 text-white/80">
                <span class="font-medium text-white">{{ $r['item'] }}</span>
                <span class="text-white/40"> · {{ $r['class_group'] }} {{ $r['gender'] }}</span>
                <span class="float-right font-mono text-amber-300">{{ $r['value'] }} {{ $r['unit'] }}</span>
            </li>
            @endforeach
        </ol>
        @endif

        @if(count($recentBreaks ?? []))
        <h2 class="text-xs font-bold uppercase tracking-widest text-amber-400 mt-10 mb-3">Recent Record Breaks</h2>
        <ul id="recent-breaks" class="space-y-2 text-sm">
            @foreach($recentBreaks as $b)
            <li class="bg-amber-500/10 border border-amber-500/30 rounded-xl px-4 py-3">
                <strong class="text-white">{{ $b['name'] ?? 'Participant' }}</strong> <span class="text-white/60">— {{ $b['item'] }}</span>
                <span class="block text-amber-300 font-mono mt-1">{{ $b['new_value'] }} {{ $b['unit'] }} · {{ $b['prize_label'] }}</span>
            </li>
            @endforeach
        </ul>
        @endif

        @if(count($categoryLinks ?? []))
        <h2 class="text-xs font-bold uppercase tracking-widest text-amber-400 mt-10 mb-3">Category Scoreboards</h2>
        <div id="category-links" class="flex flex-wrap gap-2">
            @foreach($categoryLinks as $link)
            <a href="{{ $link['url'] }}" class="px-3 py-1.5 rounded-full text-xs font-semibold bg-slate-900/60 border border-slate-800 text-white/70 hover:border-amber-500/50 hover:text-white transition">{{ $link['label'] }}</a>
            @endforeach
        </div>
        @endif

        <p class="mt-10 pt-6 border-t border-slate-800 text-center flex flex-wrap justify-center gap-5 text-xs">
            <a href="{{ route('tenant.fest.records', $event->id) }}" class="text-amber-400 font-semibold hover:underline">All records →</a>
            <a href="{{ route('tenant.fest.show', ['event' => $event->id]) }}" class="text-white/40 hover:text-white">← Event page</a>
        </p>
    </div>
</section>
<script>
(function () {
    const root = document.getElementById('fest-live-root');
    if (!root) return;
    const url = root.dataset.liveUrl;
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const medalFor = (rank) => (rank >= 1 && rank <= 3)
        ? `<img src="/images/fest/medals/rank-${rank}.webp" alt="Rank ${rank}" class="inline-block w-5 h-5 align-middle">`
        : `<span class="font-mono">#${rank}</span>`;

    function renderSchool(rows, published, provisional) {
        const el = document.getElementById('school-scoreboard');
        const badge = document.getElementById('school-scoreboard-provisional-badge');
        if (badge) badge.classList.toggle('hidden', !provisional);
        if (!el) return;
        if (!rows.length) {
            el.innerHTML = `<li class="text-white/30 text-center py-6">${published ? 'No scores yet' : 'Standings appear here once at least one item is published'}</li>`;
            return;
        }
        el.innerHTML = rows.map(r => `<li class="grid grid-cols-[2rem_1fr_repeat(3,2.25rem)_3.5rem] gap-1.5 items-center px-3 py-2.5">
            <span class="flex items-center">${medalFor(r.rank)}</span>
            <span class="text-white font-semibold text-sm truncate uppercase">${esc(r.school_name)}</span>
            <span class="text-center font-mono font-bold text-amber-300 text-sm">${esc(r.gold || 0)}</span>
            <span class="text-center font-mono font-bold text-slate-300 text-sm">${esc(r.silver || 0)}</span>
            <span class="text-center font-mono font-bold text-amber-600 text-sm">${esc(r.bronze || 0)}</span>
            <span class="text-right font-mono font-extrabold text-white text-sm">${esc(r.total_points)}</span></li>`).join('');
    }

    function renderHouse(rows) {
        const el = document.getElementById('house-scoreboard');
        if (!el) return;
        el.innerHTML = rows.map(r => `<li class="flex justify-between items-center bg-slate-900/60 border border-slate-800 rounded-xl px-4 py-3">
            <span class="text-white flex items-center gap-2"><span class="inline-block w-3 h-3 rounded-full" style="background:${esc(r.color || '#fbbf24')}"></span>${medalFor(r.rank)} ${esc(r.house_name)}</span>
            <span class="font-mono font-bold text-white">${esc(r.total_points)}</span></li>`).join('');
    }

    function renderNow(p) {
        const el = document.getElementById('now-performing');
        if (!el) return;
        if (!p) { el.classList.add('hidden'); return; }
        el.classList.remove('hidden');
        let html = `<span class="text-xs font-bold uppercase tracking-widest text-amber-400 block mb-2">● Now performing</span><strong class="text-2xl font-extrabold text-white block leading-tight uppercase">${esc(p.item_title || '—')}</strong>`;
        if (p.category_label) html += `<span class="text-amber-300/70 text-xs font-semibold uppercase tracking-wide mt-1 block">${esc(p.category_label)}</span>`;
        if (p.show_name && p.name) html += `<span class="text-amber-200 text-lg font-semibold mt-1 block">${esc(p.name)}</span>`;
        el.innerHTML = html;
    }

    async function refresh() {
        try {
            const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            renderSchool(data.scoreboard || [], Boolean(data.standingsPublished), Boolean(data.standingsProvisional));
            renderHouse(data.houseScoreboard || []);
            renderNow(data.nowPerforming);
            const badge = document.getElementById('live-refresh-badge');
            if (badge && data.refreshedAt) badge.textContent = 'Updated ' + new Date(data.refreshedAt).toLocaleTimeString();
        } catch (e) { /* silent */ }
    }

    setInterval(refresh, 30000);
})();
</script>
@endsection
