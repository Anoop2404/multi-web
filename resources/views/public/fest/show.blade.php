@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold font-heading">{{ $event->title }}</h1>
        @if($event->venue)<p class="text-gray-500 mt-1">{{ $event->venue }}</p>@endif
        <div class="grid sm:grid-cols-2 gap-3 mt-8">
            <a href="{{ route('tenant.fest.schedule', $event->id) }}" class="p-4 bg-white border rounded-xl hover:border-amber-400">📅 Schedule</a>
            <a href="{{ route('tenant.fest.live', $event->id) }}" class="p-4 bg-white border rounded-xl hover:border-amber-400">🔴 Live Scoreboard</a>
            <a href="{{ route('tenant.fest.search', $event->id) }}" class="p-4 bg-white border rounded-xl hover:border-amber-400">🔍 Search Participant</a>
        </div>
        @if($event->parent_event_id)
        <p class="text-xs text-gray-400 mt-6">Part of a multi-level festival series.</p>
        @endif
    </div>
</section>
@endsection
