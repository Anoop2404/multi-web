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
<section class="py-12 px-4">
    <div class="max-w-6xl mx-auto">
        <p class="text-xs text-accent font-bold uppercase tracking-widest">Scoreboard</p>
        <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold font-heading mb-1">{{ $event->title }}</h1>
                @if(!empty($scoreboardTitle))
                <p class="text-gray-500 text-sm flex items-center gap-2">
                    @if($isPublished)
                    <span class="relative flex h-2 w-2" aria-hidden="true">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-accent opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-accent"></span>
                    </span>
                    @endif
                    {{ $scoreboardTitle }} · auto-refreshes every 30 seconds
                </p>
                @endif
            </div>
            @if($isPublished)
            <a href="{{ route('tenant.fest.results', ['event' => $event->id, 'scope' => $selectedScope['key']]) }}"
               class="v2-btn-primary inline-flex items-center gap-1.5 text-sm font-bold px-4 py-2 rounded-xl shadow-sm">
                All results →
            </a>
            @endif
        </div>

        @include('public.fest.partials.scope-nav', [
            'routeName' => 'tenant.fest.scoreboard',
            'routeQuery' => array_filter(['category' => $category]),
            'class' => 'mb-4',
        ])

        @if(count($categories ?? []))
        <nav class="flex flex-wrap gap-2 mb-8">
            <a href="{{ route('tenant.fest.scoreboard', ['event' => $event->id, 'scope' => $selectedScope['key']]) }}"
               class="px-3 py-1 rounded-full text-xs font-semibold border {{ !$category ? 'bg-slate-700 text-white border-slate-700' : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50' }}">
                All categories
            </a>
            @foreach($categories as $cat)
            <a href="{{ route('tenant.fest.scoreboard', ['event' => $event->id, 'scope' => $selectedScope['key'], 'category' => $cat]) }}"
               class="px-3 py-1 rounded-full text-xs font-semibold border {{ ($category ?? '') === $cat ? 'v2-badge-accent border-transparent' : 'bg-white text-gray-500 border-gray-200 hover:bg-gray-50' }}">
                {{ $categoryLabels[$cat] ?? strtoupper($cat) }}
            </a>
            @endforeach
        </nav>
        @endif

        @unless($isPublished)
        <div class="v2-card p-10 text-center">
            <div class="w-12 h-12 rounded-full v2-badge-primary mx-auto mb-4 flex items-center justify-center text-xl" aria-hidden="true">⏳</div>
            <h2 class="font-bold text-gray-800">Standings are not published yet</h2>
            <p class="text-sm text-gray-500 mt-2">The official {{ $selectedScope['label'] }} scoreboard will appear here after results are published.</p>
        </div>
        @else
        <div class="grid lg:grid-cols-[1.1fr_.9fr] gap-6">
            <section>
                <h2 class="font-bold mb-3">Leading schools</h2>
                <ol class="space-y-2">
                    @forelse($scoreboard as $row)
                    @php $pct = $maxPoints > 0 ? max(4, round(($row['total_points'] / $maxPoints) * 100)) : 0; @endphp
                    <li class="v2-card relative overflow-hidden px-4 py-3">
                        <div class="absolute inset-y-0 left-0" style="width: {{ $pct }}%; background-color: var(--color-accent-light);" aria-hidden="true"></div>
                        <div class="relative flex items-center justify-between gap-3">
                            <span class="flex items-center gap-3 min-w-0">
                                <span class="shrink-0 w-8 h-8 rounded-full flex items-center justify-center font-bold {{ isset($medals[$row['rank']]) ? 'text-lg' : 'v2-badge-primary text-sm' }}">
                                    {{ $medals[$row['rank']] ?? $row['rank'] }}
                                </span>
                                <span class="font-semibold truncate">{{ $row['school_name'] }}</span>
                            </span>
                            <span class="font-mono font-bold shrink-0">{{ $row['total_points'] }}</span>
                        </div>
                    </li>
                    @empty
                    <li class="v2-card text-gray-400 text-center py-8">No scores yet for this view.</li>
                    @endforelse
                </ol>
            </section>

            <section>
                <h2 class="font-bold mb-3">Latest winners</h2>
                <div class="space-y-2 max-h-[32rem] overflow-y-auto pr-1">
                    @forelse($latestWinners ?? [] as $winner)
                        <article class="v2-card px-4 py-3">
                            <div class="flex items-start gap-3">
                                @if($winner['photo'] ?? null)
                                <img src="{{ $winner['photo'] }}" alt="" class="w-10 h-10 rounded-full object-cover border shrink-0">
                                @else
                                <span class="w-10 h-10 rounded-full v2-badge-primary flex items-center justify-center text-xs font-bold shrink-0">{{ strtoupper(substr($winner['participant'] ?? '?', 0, 1)) }}</span>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold truncate">{{ $winner['participant'] }}</p>
                                    <p class="text-xs text-gray-500 truncate">{{ $winner['school'] }}</p>
                                    <p class="text-xs text-gray-400 mt-1 truncate">{{ $winner['item'] }}</p>
                                </div>
                                <span class="shrink-0 rounded-full v2-badge-accent text-xs font-bold w-7 h-7 flex items-center justify-center" title="Position {{ $winner['position'] }}">
                                    {{ $medals[$winner['position']] ?? '#'.$winner['position'] }}
                                </span>
                            </div>
                        </article>
                    @empty
                        <div class="v2-card text-gray-400 text-center py-8">No winners yet.</div>
                    @endforelse
                </div>
            </section>
        </div>
        @endunless

        <p class="mt-8 text-center space-x-4">
            <a href="{{ route('tenant.fest.live', ['event' => $event->id, 'scope' => $selectedScope['key']]) }}" class="text-primary text-sm font-semibold hover:underline">Live event →</a>
            <a href="{{ route('tenant.fest.show', ['event' => $event->id, 'scope' => $selectedScope['key']]) }}" class="text-primary text-sm font-semibold hover:underline">← Festival hub</a>
        </p>
    </div>
</section>
@endsection
