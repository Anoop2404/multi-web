@extends('layouts.public')

@section('content')
<section class="py-12 px-4 bg-slate-950 text-white min-h-screen">
    <div class="max-w-3xl mx-auto">
        <p class="text-amber-400 text-xs uppercase tracking-widest text-center">Live</p>
        <h1 class="text-3xl font-bold text-center mt-1">{{ $event->title }}</h1>
        @if($nowSlot)
        <div class="mt-6 p-4 bg-white/10 rounded-xl text-center text-sm">
            Now: <strong>{{ $nowSlot->item?->title }}</strong>
            @if($nowSlot->participant) — #{{ $nowSlot->participant->chest_no }} {{ $nowSlot->participant->student?->name }} @endif
        </div>
        @endif
        <h2 class="font-semibold mt-10 mb-3">School Standings</h2>
        <ol class="space-y-2">
            @forelse($scoreboard as $row)
            <li class="flex justify-between bg-white/5 border border-white/10 rounded-lg px-4 py-3">
                <span><span class="text-amber-400 font-bold mr-2">#{{ $row['rank'] }}</span>{{ $row['school_name'] }}</span>
                <span class="font-mono">{{ $row['total_points'] }}</span>
            </li>
            @empty
            <li class="text-white/40 text-center py-6">No scores yet</li>
            @endforelse
        </ol>
        @if(count($houseScoreboard))
        <h2 class="font-semibold mt-10 mb-3">House Standings</h2>
        <ol class="space-y-2">
            @foreach($houseScoreboard as $row)
            <li class="flex justify-between bg-white/5 border border-white/10 rounded-lg px-4 py-3">
                <span><span class="inline-block w-3 h-3 rounded-full mr-2" style="background:{{ $row['color'] ?? '#fbbf24' }}"></span>#{{ $row['rank'] }} {{ $row['house_name'] }}</span>
                <span class="font-mono">{{ $row['total_points'] }}</span>
            </li>
            @endforeach
        </ol>
        @endif
        <p class="mt-8 text-center"><a href="{{ route('tenant.fest.show', $event->id) }}" class="text-amber-400 text-sm">← Festival hub</a></p>
    </div>
</section>
@endsection
