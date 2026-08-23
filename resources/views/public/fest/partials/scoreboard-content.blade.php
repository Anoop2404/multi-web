@php
    $maxPoints = collect($scoreboard ?? [])->max('total_points') ?: 0;
@endphp

@if($cumulativeStanding ?? null)
<div class="mb-6 rounded-2xl border border-emerald-400/20 bg-emerald-500/10 px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
    <div><p class="text-sm font-extrabold text-emerald-200">Championship standing after {{ $cumulativeStanding['phase']->name }}</p><p class="text-xs text-emerald-100/70 mt-0.5">Opening balance is carried forward; every regional contribution is counted exactly once.</p></div>
    <span class="shrink-0 text-[11px] font-bold uppercase tracking-wider text-emerald-200 border border-emerald-300/20 rounded-full px-3 py-1">Official snapshot v{{ $cumulativeStanding['version'] }}</span>
</div>
@endif

@unless($isPublished)
<div class="rounded-3xl bg-slate-900 border border-slate-800 p-10 sm:p-12 text-center shadow-xl">
    <span class="w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/20 mx-auto mb-4 flex items-center justify-center text-xl font-extrabold text-amber-300" aria-hidden="true">🔒</span>
    <h2 class="text-xl font-bold text-white">Public Scoreboard & Standings Disabled</h2>
    <p class="text-sm text-slate-400 mt-2 max-w-md mx-auto">Public scoreboard and results visibility has been disabled for this event by the event committee.</p>
</div>
@else
@if($isProvisional ?? false)
<div class="mb-6 rounded-2xl border border-amber-400/20 bg-amber-500/10 px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
    <div><p class="text-sm font-extrabold text-amber-200">Provisional standing</p><p class="text-xs text-amber-100/70 mt-0.5">Computed from items published so far — the official standing appears once the event committee publishes final results.</p></div>
    <span class="shrink-0 text-[11px] font-bold uppercase tracking-wider text-amber-200 border border-amber-300/20 rounded-full px-3 py-1">Not yet official</span>
</div>
@endif
<div class="grid lg:grid-cols-[1.2fr_.8fr] gap-6">
    <section class="space-y-4" aria-labelledby="leading-schools-title">
        <div class="flex items-center justify-between gap-3"><h2 id="leading-schools-title" class="text-lg font-extrabold text-white">Leading Schools</h2><span class="text-xs text-slate-400 font-semibold">{{ ($cumulativeStanding ?? null) ? 'Cumulative Points' : 'Total Points' }}</span></div>
        <ol class="space-y-3">
            @forelse($scoreboard as $row)
            @php
                $pct = $maxPoints > 0 ? max(6, round(($row['total_points'] / $maxPoints) * 100)) : 0;
                $podium = (int) $row['rank'] <= 3;
                $rankClass = match((int) $row['rank']) { 1 => 'border-amber-400/60 bg-gradient-to-r from-amber-500/10 to-slate-900', 2 => 'border-slate-400/40 bg-gradient-to-r from-slate-400/10 to-slate-900', 3 => 'border-amber-700/40 bg-gradient-to-r from-amber-700/10 to-slate-900', default => 'border-slate-800' };
            @endphp
            @if($podium)
            <li class="relative overflow-hidden rounded-2xl bg-slate-900 border {{ $rankClass }} p-4 shadow-lg">
                <div class="absolute inset-y-0 left-0 bg-slate-800/40 pointer-events-none" style="width: {{ $pct }}%" aria-hidden="true"></div>
                <div class="relative flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0"><span class="shrink-0 w-10 h-10 rounded-xl {{ $podium ? 'bg-amber-400/20' : 'bg-slate-800 text-slate-300 border border-slate-700' }} flex items-center justify-center font-extrabold">@if($podium)<img src="{{ asset('images/fest/medals/rank-'.$row['rank'].'.webp') }}" alt="Rank {{ $row['rank'] }}" class="w-8 h-8">@else{{ $row['rank'] }}@endif</span><span class="font-bold text-white text-sm sm:text-base truncate">{{ $row['school_name'] }}</span></div>
                    <div class="flex items-center gap-2 shrink-0">
                        <div class="text-right"><span class="font-mono font-extrabold text-xl text-amber-400">{{ $row['total_points'] }}</span><span class="text-[10px] text-slate-400 uppercase block font-bold">PTS</span></div>
                        <a href="{{ route('tenant.fest.results.school', array_filter(['event' => $event->id, 'school' => $row['school_id'], 'category' => $category ?? null])) }}"
                           title="View {{ $row['school_name'] }}'s {{ ($category ?? null) ? 'roster for this category' : 'full roster' }}"
                           aria-label="View {{ $row['school_name'] }}'s {{ ($category ?? null) ? 'roster for this category' : 'full roster' }}"
                           class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-white/40 hover:text-amber-400 hover:bg-white/5 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </a>
                    </div>
                </div>
                @if($cumulativeStanding ?? null)
                <dl class="relative grid {{ $showPhasePoints ? 'grid-cols-2 sm:grid-cols-4' : 'grid-cols-3' }} gap-2 mt-4 pt-3 border-t border-slate-700/60 text-center">
                    <div><dt class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">Opening</dt><dd class="font-mono font-bold text-slate-200">{{ $row['opening_points'] }}</dd></div>
                    <div><dt class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">This Event</dt><dd class="font-mono font-bold text-sky-300">+{{ $row['event_points'] }}</dd></div>
                    @if($showPhasePoints)<div><dt class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">Phase Total</dt><dd class="font-mono font-bold text-indigo-300">+{{ $row['phase_points'] }}</dd></div>@endif
                    <div><dt class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">Closing</dt><dd class="font-mono font-extrabold text-amber-400">{{ $row['closing_points'] }}</dd></div>
                </dl>
                @endif
            </li>
            @else
            <li class="relative rounded-xl bg-slate-900 border border-slate-800 overflow-hidden">
                <details class="group">
                    <summary class="list-none cursor-pointer flex items-center gap-3 px-4 py-3 pr-11 hover:bg-slate-800/60 focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-amber-400">
                        <span class="shrink-0 w-8 h-8 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 flex items-center justify-center text-xs font-extrabold">{{ $row['rank'] }}</span>
                        <span class="font-semibold text-sm text-white truncate flex-1">{{ $row['school_name'] }}</span>
                        <span class="font-mono font-extrabold text-amber-400">{{ $row['total_points'] }}</span>
                        @if($cumulativeStanding ?? null)<span class="text-slate-500 group-open:rotate-180 transition" aria-hidden="true">⌄</span>@endif
                    </summary>
                    @if($cumulativeStanding ?? null)
                    <dl class="grid {{ $showPhasePoints ? 'grid-cols-2 sm:grid-cols-4' : 'grid-cols-3' }} gap-2 px-4 pb-4 text-center">
                        <div><dt class="text-[10px] uppercase text-slate-500 font-bold">Opening</dt><dd class="font-mono font-bold text-slate-200">{{ $row['opening_points'] }}</dd></div>
                        <div><dt class="text-[10px] uppercase text-slate-500 font-bold">This Event</dt><dd class="font-mono font-bold text-sky-300">+{{ $row['event_points'] }}</dd></div>
                        @if($showPhasePoints)<div><dt class="text-[10px] uppercase text-slate-500 font-bold">Phase Total</dt><dd class="font-mono font-bold text-indigo-300">+{{ $row['phase_points'] }}</dd></div>@endif
                        <div><dt class="text-[10px] uppercase text-slate-500 font-bold">Closing</dt><dd class="font-mono font-bold text-amber-400">{{ $row['closing_points'] }}</dd></div>
                    </dl>
                    @endif
                </details>
                {{-- Sibling of <details>, not nested in <summary> — keeps this click from
                     also toggling the details open/closed. --}}
                <a href="{{ route('tenant.fest.results.school', array_filter(['event' => $event->id, 'school' => $row['school_id'], 'category' => $category ?? null])) }}"
                   title="View {{ $row['school_name'] }}'s {{ ($category ?? null) ? 'roster for this category' : 'full roster' }}"
                   aria-label="View {{ $row['school_name'] }}'s {{ ($category ?? null) ? 'roster for this category' : 'full roster' }}"
                   class="absolute top-2 right-2 inline-flex items-center justify-center w-8 h-8 rounded-lg text-white/40 hover:text-amber-400 hover:bg-white/5 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="w-4 h-4">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                </a>
            </li>
            @endif
            @empty
            <li class="rounded-2xl bg-slate-900 border border-slate-800 text-slate-400 text-center py-12">No scores published yet for this event.</li>
            @endforelse
        </ol>
    </section>

    <section class="space-y-4" aria-labelledby="latest-winners-title">
        <div class="flex items-center justify-between"><h2 id="latest-winners-title" class="text-lg font-extrabold text-white">Latest Item Winners</h2><span class="text-xs text-amber-400 font-semibold">Recent results</span></div>
        <div class="space-y-3 max-h-[44rem] overflow-y-auto pr-1">
            @forelse($latestWinners ?? [] as $itemGroup)
                @include('public.fest.partials.fest-winner-item-card')
            @empty
            <div class="rounded-2xl bg-slate-900 border border-slate-800 text-slate-400 text-center py-12">No winners announced yet.</div>
            @endforelse
        </div>
    </section>
</div>
@endunless
