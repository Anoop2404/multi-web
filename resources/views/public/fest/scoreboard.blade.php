@extends('layouts.public')

@section('content')
@if($isPublished)
<script>
    setTimeout(() => window.location.reload(), 30000);
</script>
@endif
<section class="py-12 px-4">
    <div class="max-w-6xl mx-auto">
        <p class="text-xs text-amber-600 font-bold uppercase">Scoreboard</p>
        <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold font-heading mb-1">{{ $event->title }}</h1>
                @if(!empty($scoreboardTitle))
                <p class="text-gray-500 text-sm">{{ $scoreboardTitle }} · auto-refreshes every 30 seconds</p>
                @endif
            </div>
            @if($isPublished)
            <a href="{{ route('tenant.fest.results', ['event' => $event->id, 'scope' => $selectedScope['key']]) }}" class="text-sm font-semibold text-amber-700 hover:underline">All results →</a>
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
               class="px-3 py-1.5 rounded-full text-sm border {{ !$category ? 'bg-slate-700 text-white border-slate-700' : 'bg-white hover:border-amber-400' }}">
                All categories
            </a>
            @foreach($categories as $cat)
            <a href="{{ route('tenant.fest.scoreboard', ['event' => $event->id, 'scope' => $selectedScope['key'], 'category' => $cat]) }}"
               class="px-3 py-1.5 rounded-full text-sm border {{ ($category ?? '') === $cat ? 'bg-amber-500 text-white border-amber-500' : 'bg-white hover:border-amber-400' }}">
                {{ $categoryLabels[$cat] ?? strtoupper($cat) }}
            </a>
            @endforeach
        </nav>
        @endif

        @unless($isPublished)
        <div class="rounded-2xl border border-dashed border-amber-200 bg-amber-50 p-10 text-center">
            <h2 class="font-bold text-gray-800">Standings are not published yet</h2>
            <p class="text-sm text-gray-500 mt-2">The official {{ $selectedScope['label'] }} scoreboard will appear here after results are published.</p>
        </div>
        @else
        <div class="grid lg:grid-cols-[1.1fr_.9fr] gap-6">
            <section>
                <h2 class="font-bold mb-3">Leading schools</h2>
                <ol class="space-y-2">
                    @forelse($scoreboard as $row)
                    <li class="flex justify-between bg-white border rounded-xl px-4 py-3 shadow-sm">
                        <span><span class="text-amber-600 font-bold mr-2">#{{ $row['rank'] }}</span>{{ $row['school_name'] }}</span>
                        <span class="font-mono font-bold">{{ $row['total_points'] }}</span>
                    </li>
                    @empty
                    <li class="text-gray-400 text-center py-8 border rounded-xl">No scores yet for this view.</li>
                    @endforelse
                </ol>
            </section>

            <section>
                <h2 class="font-bold mb-3">Latest winners</h2>
                <div class="space-y-2 max-h-[32rem] overflow-hidden">
                    @forelse($latestWinners ?? [] as $winner)
                        <article class="bg-white border rounded-xl px-4 py-3 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-semibold">{{ $winner['participant'] }}</p>
                                    <p class="text-xs text-gray-500">{{ $winner['school'] }}</p>
                                    <p class="text-xs text-gray-400 mt-1">{{ $winner['item'] }}</p>
                                </div>
                                <span class="shrink-0 rounded-full bg-amber-100 text-amber-800 text-xs font-bold px-2 py-1">
                                    #{{ $winner['position'] }}
                                </span>
                            </div>
                        </article>
                    @empty
                        <div class="text-gray-400 text-center py-8 border rounded-xl">No winners yet.</div>
                    @endforelse
                </div>
            </section>
        </div>
        @endunless

        <p class="mt-8 text-center space-x-4">
            <a href="{{ route('tenant.fest.live', ['event' => $event->id, 'scope' => $selectedScope['key']]) }}" class="text-amber-700 text-sm">Live event →</a>
            <a href="{{ route('tenant.fest.show', ['event' => $event->id, 'scope' => $selectedScope['key']]) }}" class="text-amber-700 text-sm">← Festival hub</a>
        </p>
    </div>
</section>
@endsection
