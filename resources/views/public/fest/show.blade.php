@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold font-heading">{{ $event->title }}</h1>
        @if($event->venue)<p class="text-gray-500 mt-1">{{ $event->venue }}</p>@endif
        @unless($event->results_published)
        <p class="text-xs text-amber-700 mt-2">Live fest — participant names hidden on-stage until results are published.</p>
        @endunless
        <div class="grid sm:grid-cols-2 gap-3 mt-8">
            @if($event->schedule_published)
            <a href="{{ route('tenant.fest.schedule', $event->id) }}" class="p-4 bg-white border rounded-xl hover:border-amber-400">📅 Schedule</a>
            @else
            <div class="p-4 bg-gray-50 border rounded-xl text-sm text-gray-500">📅 Schedule — not published yet</div>
            @endif
            <a href="{{ route('tenant.fest.live', $event->id) }}" class="p-4 bg-white border rounded-xl hover:border-amber-400">🔴 Live Scoreboard</a>
            <a href="{{ route('tenant.fest.scoreboard', $event->id) }}" class="p-4 bg-white border rounded-xl hover:border-amber-400">🏆 Category Scoreboard</a>
            @if($event->results_published)
            <a href="{{ route('tenant.fest.results', $event->id) }}" class="p-4 bg-white border rounded-xl hover:border-amber-400">🥇 Published Results</a>
            @endif
            @if($event->manual_pdf_path)
            <a href="{{ route('tenant.fest.manual', $event->id) }}" class="p-4 bg-white border rounded-xl hover:border-amber-400">📄 Event Manual (PDF)</a>
            @endif
            @if($event->record_tracking_enabled)
            <a href="{{ route('tenant.fest.records', $event->id) }}" class="p-4 bg-white border rounded-xl hover:border-amber-400">🏃 Athletic Records</a>
            @endif
            <a href="{{ route('tenant.fest.search', $event->id) }}" class="p-4 bg-white border rounded-xl hover:border-amber-400">🔍 Search Participant</a>
        </div>
        @if($items->isNotEmpty())
        <h2 class="text-lg font-semibold mt-10 mb-3">Item boards</h2>
        <div class="grid sm:grid-cols-2 gap-2">
            @foreach($items as $item)
            @if($event->schedule_published)
            <a href="{{ route('tenant.fest.item-schedule', [$event->id, $item->id]) }}"
               class="p-3 bg-white border rounded-lg text-sm hover:border-amber-400 block">
                {{ $item->title }}
                @if($item->stage_type === 'off_stage')
                <span class="text-xs text-gray-400"> · off-stage</span>
                @endif
            </a>
            @endif
            @if($event->results_published)
            <a href="{{ route('tenant.fest.item-results', [$event->id, $item->id]) }}"
               class="p-3 bg-amber-50 border border-amber-100 rounded-lg text-sm hover:border-amber-400 block">
                {{ $item->title }} — results
            </a>
            @endif
            @endforeach
        </div>
        @endif
        @if($event->parent_event_id)
        <p class="text-xs text-gray-400 mt-6">Part of a multi-level festival series.</p>
        @endif
    </div>
</section>
@endsection
