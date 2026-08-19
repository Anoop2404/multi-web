@extends('layouts.public')

@section('content')
@if($isPublished)
<script>
    setTimeout(() => window.location.reload(), 30000);
</script>
@endif
@php
    $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
    $maxPoints = collect($scoreboard ?? [])->max('total_points') ?: 0;
@endphp

<section class="py-8 px-4 bg-slate-950 text-white min-h-screen">
    <div class="max-w-7xl mx-auto space-y-6">

        {{-- Premium Sahodaya Event Header --}}
        <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-slate-850 to-slate-900 border border-amber-500/20 p-6 md:p-8 shadow-2xl">
            <div class="absolute top-0 right-0 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 w-80 h-80 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10 flex flex-col lg:flex-row lg:items-center justify-between gap-6">
                {{-- Left: Sahodaya Branding & Event Name --}}
                <div class="space-y-3">
                    <div class="flex items-center gap-3">
                        @if($tenant->logo ?? null)
                        <img src="{{ $tenant->logo }}" alt="{{ $tenant->name }}" class="w-12 h-12 rounded-2xl object-contain bg-white/10 p-1.5 border border-white/20 shadow-md shrink-0">
                        @else
                        <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-amber-400 via-amber-500 to-amber-600 flex items-center justify-center text-xl font-bold text-slate-950 shadow-lg shadow-amber-500/20 shrink-0">
                            🏆
                        </div>
                        @endif
                        <div>
                            <span class="text-xs uppercase tracking-widest text-amber-400 font-extrabold block">
                                {{ $tenant->name ?? 'Sahodaya Complex' }}
                            </span>
                            <span class="text-[11px] text-slate-400 font-medium">Official Public Live Scoreboard</span>
                        </div>
                    </div>

                    <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight font-heading text-white">
                        {{ $event->title }}
                    </h1>

                    @if(!empty($scoreboardTitle) || $event->venue || $event->event_start)
                    <p class="text-xs md:text-sm text-slate-400 flex items-center gap-3 flex-wrap">
                        @if(!empty($scoreboardTitle))
                        <span class="text-amber-300 font-semibold">📍 Scope: {{ $scoreboardTitle }}</span>
                        @endif
                        @if($event->venue)
                        <span>🏟️ {{ $event->venue }}</span>
                        @endif
                        @if($event->event_start)
                        <span>📅 {{ \Carbon\Carbon::parse($event->event_start)->format('d M Y') }}</span>
                        @endif
                    </p>
                    @endif
                </div>

                {{-- Right: Live Digital Clock & Controls --}}
                <div class="flex flex-col items-start lg:items-end gap-3 shrink-0 border-t lg:border-t-0 lg:border-l border-slate-800/80 pt-4 lg:pt-0 lg:pl-8">
                    <div class="flex items-center gap-2">
                        @if($isPublished)
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                            <span class="relative flex h-2 w-2">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-400"></span>
                            </span>
                            LIVE STANDINGS
                        </span>
                        @else
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-slate-800 text-slate-400 border border-slate-700">
                            STANDINGS UNPUBLISHED
                        </span>
                        @endif
                    </div>

                    {{-- Digital Real-time Clock --}}
                    <div class="text-left lg:text-right">
                        <div id="scoreboard-live-clock" class="text-3xl md:text-4xl font-mono font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-amber-200 via-amber-400 to-amber-300 tracking-wider">
                            --:--:--
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1 flex items-center gap-1 justify-start lg:justify-end">
                            <span>⏱️ Auto-refreshes every 30s</span>
                        </p>
                    </div>

                    @if($isPublished)
                    <a href="{{ route('tenant.fest.results', ['event' => $event->id, 'scope' => $selectedScope['key']]) }}"
                       class="inline-flex items-center gap-2 text-xs font-extrabold bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 px-4 py-2.5 rounded-xl transition shadow-lg shadow-amber-500/20">
                        View All Detailed Results →
                    </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Scope Navigation & Category Filters --}}
        <div class="bg-slate-900/60 rounded-2xl p-4 border border-slate-800 space-y-3">
            @include('public.fest.partials.scope-nav', [
                'routeName' => 'tenant.fest.scoreboard',
                'routeQuery' => array_filter(['category' => $category]),
                'class' => '',
            ])

            @if(count($categories ?? []))
            <nav class="flex flex-wrap gap-2 pt-2 border-t border-slate-800/60">
                <a href="{{ route('tenant.fest.scoreboard', ['event' => $event->id, 'scope' => $selectedScope['key']]) }}"
                   class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition border {{ !$category ? 'bg-amber-500 text-slate-950 border-amber-500 shadow-md shadow-amber-500/20' : 'bg-slate-800/80 text-slate-300 border-slate-700 hover:bg-slate-800' }}">
                    All Categories
                </a>
                @foreach($categories as $cat)
                <a href="{{ route('tenant.fest.scoreboard', ['event' => $event->id, 'scope' => $selectedScope['key'], 'category' => $cat]) }}"
                   class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition border {{ ($category ?? '') === $cat ? 'bg-amber-500 text-slate-950 border-amber-500 shadow-md shadow-amber-500/20' : 'bg-slate-800/80 text-slate-300 border-slate-700 hover:bg-slate-800' }}">
                    {{ $categoryLabels[$cat] ?? strtoupper($cat) }}
                </a>
                @endforeach
            </nav>
            @endif
        </div>

        @unless($isPublished)
        <div class="rounded-3xl bg-slate-900 border border-slate-800 p-12 text-center shadow-xl">
            <div class="w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/20 mx-auto mb-4 flex items-center justify-center text-3xl" aria-hidden="true">⏳</div>
            <h2 class="text-xl font-bold text-white">Official Standings Not Published Yet</h2>
            <p class="text-sm text-slate-400 mt-2 max-w-md mx-auto">The official {{ $selectedScope['label'] }} scoreboard will appear here once results are certified and published by the event committee.</p>
        </div>
        @else
        <div class="grid lg:grid-cols-[1.2fr_.8fr] gap-6">
            {{-- School Leaderboard --}}
            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-extrabold text-white flex items-center gap-2">
                        <span>🏆</span> Leading Schools
                    </h2>
                    <span class="text-xs text-slate-400 font-semibold">Total Points</span>
                </div>

                <ol class="space-y-3">
                    @forelse($scoreboard as $row)
                    @php 
                        $pct = $maxPoints > 0 ? max(6, round(($row['total_points'] / $maxPoints) * 100)) : 0;
                        $isGold = $row['rank'] == 1;
                        $isSilver = $row['rank'] == 2;
                        $isBronze = $row['rank'] == 3;
                    @endphp
                    <li class="relative overflow-hidden rounded-2xl bg-slate-900 border transition-all duration-300 p-4 shadow-lg
                        {{ $isGold ? 'border-amber-400/60 shadow-amber-500/10 bg-gradient-to-r from-amber-500/10 via-slate-900 to-slate-900' : '' }}
                        {{ $isSilver ? 'border-slate-400/40 bg-gradient-to-r from-slate-400/10 via-slate-900 to-slate-900' : '' }}
                        {{ $isBronze ? 'border-amber-700/40 bg-gradient-to-r from-amber-700/10 via-slate-900 to-slate-900' : '' }}
                        {{ !$isGold && !$isSilver && !$isBronze ? 'border-slate-800/80 hover:border-slate-700' : '' }}">
                        
                        {{-- Proportional Progress Bar --}}
                        <div class="absolute inset-y-0 left-0 rounded-2xl pointer-events-none transition-all duration-500
                            {{ $isGold ? 'bg-amber-500/15' : 'bg-slate-800/40' }}" 
                            style="width: {{ $pct }}%;" aria-hidden="true"></div>

                        <div class="relative z-10 flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3.5 min-w-0">
                                <span class="shrink-0 w-10 h-10 rounded-xl flex items-center justify-center font-extrabold text-base shadow-md
                                    {{ $isGold ? 'bg-gradient-to-br from-amber-300 to-amber-500 text-slate-950 shadow-amber-500/30' : '' }}
                                    {{ $isSilver ? 'bg-gradient-to-br from-slate-200 to-slate-400 text-slate-950 shadow-slate-400/20' : '' }}
                                    {{ $isBronze ? 'bg-gradient-to-br from-amber-600 to-amber-800 text-white shadow-amber-700/20' : '' }}
                                    {{ !$isGold && !$isSilver && !$isBronze ? 'bg-slate-800 text-slate-300 border border-slate-700' : '' }}">
                                    {{ $medals[$row['rank']] ?? '#'.$row['rank'] }}
                                </span>
                                <span class="font-bold text-white md:text-base text-sm truncate">{{ $row['school_name'] }}</span>
                            </div>

                            <div class="text-right shrink-0">
                                <span class="font-mono font-extrabold text-xl text-amber-400">{{ $row['total_points'] }}</span>
                                <span class="text-[10px] text-slate-400 uppercase tracking-widest block font-bold">PTS</span>
                            </div>
                        </div>
                    </li>
                    @empty
                    <li class="rounded-2xl bg-slate-900 border border-slate-800 text-slate-400 text-center py-12">No scores published yet for this scope.</li>
                    @endforelse
                </ol>
            </section>

            {{-- Recent Item Winners Stream --}}
            <section class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-extrabold text-white flex items-center gap-2">
                        <span>✨</span> Latest Item Winners
                    </h2>
                    <span class="text-xs text-amber-400 font-semibold">Live Feed</span>
                </div>

                <div class="space-y-3 max-h-[36rem] overflow-y-auto pr-1">
                    @forelse($latestWinners ?? [] as $winner)
                        <article class="rounded-2xl bg-slate-900 border border-slate-800/80 p-4 hover:border-slate-700 transition shadow-md">
                            <div class="flex items-center gap-3.5">
                                @if($winner['photo'] ?? null)
                                <img src="{{ $winner['photo'] }}" alt="{{ $winner['participant'] }}" class="w-11 h-11 rounded-xl object-cover border border-amber-500/30 shrink-0 shadow-md">
                                @else
                                <span class="w-11 h-11 rounded-xl bg-gradient-to-br from-slate-800 to-slate-700 border border-slate-600 flex items-center justify-center text-sm font-extrabold text-amber-400 shrink-0">
                                    {{ strtoupper(substr($winner['participant'] ?? '?', 0, 1)) }}
                                </span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <p class="font-bold text-white text-sm truncate">{{ $winner['participant'] }}</p>
                                        <span class="shrink-0 rounded-lg bg-amber-500/20 text-amber-300 border border-amber-500/30 text-xs font-bold px-2 py-0.5" title="Position {{ $winner['position'] }}">
                                            {{ $medals[$winner['position']] ?? 'Rank '.$winner['position'] }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-400 truncate mt-0.5">{{ $winner['school'] }}</p>
                                    <p class="text-xs text-amber-400/90 font-medium truncate mt-1">🎭 {{ $winner['item'] }}</p>
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-2xl bg-slate-900 border border-slate-800 text-slate-400 text-center py-12">No winners announced yet.</div>
                    @endforelse
                </div>
            </section>
        </div>
        @endunless

        <footer class="pt-6 border-t border-slate-800/80 text-center space-x-6 text-xs text-slate-400">
            <a href="{{ route('tenant.fest.live', ['event' => $event->id, 'scope' => $selectedScope['key']]) }}" class="text-amber-400 font-semibold hover:underline">🔴 Live Stream View →</a>
            <a href="{{ route('tenant.fest.show', ['event' => $event->id, 'scope' => $selectedScope['key']]) }}" class="text-slate-400 hover:text-white">← Festival Hub</a>
        </footer>
    </div>
</section>

{{-- Real-time Digital Clock Script --}}
<script>
function updateScoreboardClock() {
    const clock = document.getElementById('scoreboard-live-clock');
    if (clock) {
        const now = new Date();
        clock.innerText = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
    }
}
setInterval(updateScoreboardClock, 1000);
updateScoreboardClock();
</script>
@endsection
