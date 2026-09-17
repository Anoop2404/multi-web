@extends('layouts.public-event')

@php
    $scope = ($usesPhases && request('scope') !== 'phase') ? 'cumulative' : 'phase';
    $activeRows = ($usesPhases && $scope === 'cumulative') ? $cumulativeLeaderboard : $leaderboard;
    $genderLabels = ['male' => 'Boys', 'female' => 'Girls', 'open' => 'Open'];
    $grouped = collect($activeRows)
        ->groupBy(fn (array $row) => $row['category'].'|'.$row['gender'])
        ->sortKeys();
@endphp

@section('content')
<section class="py-6 sm:py-8 px-4 bg-slate-950 text-white min-h-screen">
    <div class="max-w-[72rem] mx-auto space-y-6">
        <header class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-slate-950 to-slate-900 border border-amber-500/20 p-6 md:p-8 shadow-2xl">
            <div aria-hidden="true" class="absolute -top-32 right-0 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl"></div>
            <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-xs uppercase tracking-widest text-amber-400 font-extrabold">{{ $tenant->name ?? 'Sahodaya Complex' }}</p>
                    <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight font-heading text-white mt-1">{{ $event->title }}</h1>
                    <p class="text-sm text-slate-400 mt-2">Individual Champions — category &amp; gender wise{{ $usesPhases ? ', combined across every phase' : '' }}</p>
                </div>
                <span class="shrink-0 text-[11px] font-bold uppercase tracking-wider {{ $isPublished ? 'text-emerald-200 border-emerald-300/20' : 'text-rose-200 border-rose-300/20' }} border rounded-full px-3 py-1">
                    {{ $isPublished ? 'Live' : '🔒 Not published' }}
                </span>
            </div>
        </header>

        @if($usesPhases)
        <nav class="flex gap-2" aria-label="Championship scope">
            <a href="{{ route('tenant.fest.champions', ['event' => $event->id]) }}"
               class="px-3.5 py-2 rounded-xl text-xs font-bold border {{ $scope === 'cumulative' ? 'bg-amber-500 text-slate-950 border-amber-500' : 'bg-slate-800 text-slate-300 border-slate-700 hover:border-amber-500' }}">Combined — all phases</a>
            <a href="{{ route('tenant.fest.champions', ['event' => $event->id, 'scope' => 'phase']) }}"
               class="px-3.5 py-2 rounded-xl text-xs font-bold border {{ $scope === 'phase' ? 'bg-amber-500 text-slate-950 border-amber-500' : 'bg-slate-800 text-slate-300 border-slate-700 hover:border-amber-500' }}">This phase</a>
        </nav>
        @endif

        @unless($isPublished)
        <div class="rounded-3xl bg-slate-900 border border-slate-800 p-10 sm:p-12 text-center shadow-xl">
            <span class="w-16 h-16 rounded-2xl bg-amber-500/10 border border-amber-500/20 mx-auto mb-4 flex items-center justify-center text-xl font-extrabold text-amber-300" aria-hidden="true">🔒</span>
            <h2 class="text-xl font-bold text-white">Not Published Yet</h2>
            <p class="text-sm text-slate-400 mt-2 max-w-md mx-auto">The event committee has not published this event's official standings yet.</p>
        </div>
        @else

        @if($grouped->isEmpty())
        <div class="rounded-2xl bg-slate-900 border border-slate-800 text-slate-400 text-center py-12">No champions to show yet.</div>
        @endif

        <div class="grid sm:grid-cols-2 gap-5">
            @foreach($grouped as $key => $rows)
                @php [$categoryKey, $genderKey] = explode('|', $key); @endphp
                <section class="rounded-2xl bg-slate-900 border border-slate-800 overflow-hidden">
                    <div class="px-4 py-3 border-b border-slate-800 bg-slate-900/80">
                        <h2 class="text-sm font-extrabold text-white">{{ $categoryLabels[$categoryKey] ?? strtoupper($categoryKey) }}</h2>
                        <p class="text-xs text-amber-400 font-semibold">{{ $genderLabels[$genderKey] ?? ucfirst($genderKey) }}</p>
                    </div>
                    <ol class="divide-y divide-slate-800">
                        @foreach($rows->take(3) as $row)
                        <li class="flex items-center gap-3 px-4 py-3">
                            <span class="shrink-0 w-9 h-9 rounded-xl {{ $row['rank'] <= 3 ? 'bg-amber-400/20' : 'bg-slate-800 border border-slate-700' }} flex items-center justify-center font-extrabold text-sm">
                                @if($row['rank'] <= 3)
                                    <img src="{{ asset('images/fest/medals/rank-'.$row['rank'].'.webp') }}" alt="Rank {{ $row['rank'] }}" class="w-7 h-7">
                                @else
                                    {{ $row['rank'] }}
                                @endif
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="font-bold text-white text-sm truncate">{{ $row['student']['name'] }}</p>
                                <p class="text-xs text-slate-400 truncate">{{ $row['school'] }}</p>
                            </div>
                            <span class="font-mono font-extrabold text-amber-400 text-base shrink-0">{{ $row['points'] }}</span>
                        </li>
                        @endforeach
                    </ol>
                </section>
            @endforeach
        </div>
        @endunless

        <footer class="pt-6 border-t border-slate-800 text-center flex flex-wrap justify-center gap-5 text-xs">
            <a href="{{ route('tenant.fest.scoreboard', ['event' => $event->id]) }}" class="text-amber-400 font-semibold hover:underline">School scoreboard →</a>
            <a href="{{ route('tenant.fest.show', ['event' => $event->id]) }}" class="text-slate-400 hover:text-white">← Event page</a>
        </footer>
    </div>
</section>
@endsection
