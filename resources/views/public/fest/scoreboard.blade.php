@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-3xl mx-auto">
        <p class="text-xs text-amber-600 font-bold uppercase">Scoreboard</p>
        <h1 class="text-2xl font-bold font-heading mb-1">{{ $event->title }}</h1>
        @if(!empty($scoreboardTitle))
        <p class="text-gray-500 text-sm mb-6">{{ $scoreboardTitle }}</p>
        @endif

        @if(count($clusters ?? []))
        <nav class="flex flex-wrap gap-2 mb-4">
            <a href="{{ route('tenant.fest.scoreboard', ['event' => $event->id, 'cluster' => 'combined']) }}"
               class="px-3 py-1.5 rounded-full text-sm border {{ ($cluster ?? '') === 'combined' ? 'bg-amber-500 text-white border-amber-500' : 'bg-white hover:border-amber-400' }}">
                Combined
            </a>
            @foreach($clusters as $cl)
            <a href="{{ route('tenant.fest.scoreboard', ['event' => $event->id, 'cluster' => $cl]) }}"
               class="px-3 py-1.5 rounded-full text-sm border {{ ($cluster ?? '') === $cl ? 'bg-amber-500 text-white border-amber-500' : 'bg-white hover:border-amber-400' }}">
                {{ $clusterLabels[$cl] ?? strtoupper($cl) }}
            </a>
            @endforeach
        </nav>
        @elseif(count($categories ?? []))
        <nav class="flex flex-wrap gap-2 mb-8">
            @foreach($categories as $cat)
            <a href="{{ route('tenant.fest.scoreboard', ['event' => $event->id, 'category' => $cat]) }}"
               class="px-3 py-1.5 rounded-full text-sm border {{ ($category ?? '') === $cat ? 'bg-amber-500 text-white border-amber-500' : 'bg-white hover:border-amber-400' }}">
                {{ $categoryLabels[$cat] ?? strtoupper($cat) }}
            </a>
            @endforeach
        </nav>
        @endif

        <ol class="space-y-2">
            @forelse($scoreboard as $row)
            <li class="flex justify-between bg-white border rounded-lg px-4 py-3">
                <span><span class="text-amber-600 font-bold mr-2">#{{ $row['rank'] }}</span>{{ $row['school_name'] }}</span>
                <span class="font-mono">{{ $row['total_points'] }}</span>
            </li>
            @empty
            <li class="text-gray-400 text-center py-8 border rounded-xl">No scores yet for this view.</li>
            @endforelse
        </ol>

        <p class="mt-8 text-center space-x-4">
            <a href="{{ route('tenant.fest.live', $event->id) }}" class="text-amber-700 text-sm">Live scoreboard →</a>
            <a href="{{ route('tenant.fest.show', $event->id) }}" class="text-amber-700 text-sm">← Festival hub</a>
        </p>
    </div>
</section>
@endsection
