@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold font-heading">{{ $event->title }}</h1>
        @if($event->venue)<p class="text-gray-500 mt-1">{{ $event->venue }}</p>@endif
        @unless($event->results_published)
        <p class="text-xs text-amber-700 mt-2">Live fest — participant names hidden on-stage until results are published.</p>
        @endunless

        @include('public.fest.partials.scope-nav', [
            'routeName' => 'tenant.fest.show',
            'class' => 'mt-6 mb-2',
        ])

        @if(($selectedScope['key'] ?? 'overall') !== 'overall')
        <p class="text-sm text-gray-500 mt-3">Viewing {{ $selectedScope['label'] }} pages for this event.</p>
        @endif

        <div class="grid sm:grid-cols-2 gap-3 mt-8">
            @if($scopeSchedulePublished)
            <a href="{{ route('tenant.fest.schedule', ['event' => $event->id, 'scope' => $selectedScope['key']]) }}" class="p-4 bg-white border rounded-xl hover:border-amber-400">📅 {{ $selectedScope['label'] }} Schedule</a>
            @else
            <div class="p-4 bg-gray-50 border rounded-xl text-sm text-gray-500">📅 Schedule — not published yet</div>
            @endif
            <a href="{{ route('tenant.fest.live', ['event' => $event->id, 'scope' => $selectedScope['key']]) }}" class="p-4 bg-white border rounded-xl hover:border-amber-400">🔴 Live Event</a>
            @if($scopeResultsPublished)
            <a href="{{ route('tenant.fest.scoreboard', ['event' => $event->id, 'scope' => $selectedScope['key']]) }}" class="p-4 bg-white border rounded-xl hover:border-amber-400">🏆 {{ $selectedScope['label'] }} Scoreboard</a>
            @else
            <div class="p-4 bg-gray-50 border rounded-xl text-sm text-gray-500">🏆 Scoreboard — not published yet</div>
            @endif
            @if($scopeResultsPublished)
            <a href="{{ route('tenant.fest.results', ['event' => $event->id, 'scope' => $selectedScope['key']]) }}" class="p-4 bg-white border rounded-xl hover:border-amber-400">🥇 {{ $selectedScope['label'] }} Results</a>
            @endif
            @if($event->manual_pdf_path)
            <a href="{{ route('tenant.fest.manual', $event->id) }}" class="p-4 bg-white border rounded-xl hover:border-amber-400">📄 Event Manual (PDF)</a>
            @endif
            @if($event->record_tracking_enabled)
            <a href="{{ route('tenant.fest.records', $event->id) }}" class="p-4 bg-white border rounded-xl hover:border-amber-400">🏃 Athletic Records</a>
            @endif
            <a href="{{ route('tenant.fest.search', $event->id) }}" class="p-4 bg-white border rounded-xl hover:border-amber-400">🔍 Search Participant</a>
        </div>
        @if($itemGroups->isNotEmpty())
        <h2 class="text-lg font-semibold mt-10 mb-3">Item boards</h2>
        @foreach($itemGroups as $group)
            @if($itemGroups->count() > 1)
            <h3 class="text-xs font-bold text-gray-500 uppercase tracking-widest mt-6 mb-2">{{ $group['label'] ?? 'Items' }}</h3>
            @endif
            <div class="grid sm:grid-cols-2 gap-2">
                @foreach($group['items'] as $item)
                @if($scopeSchedulePublished)
                <a href="{{ route('tenant.fest.item-schedule', [$event->id, $item->id]) }}"
                   class="p-3 bg-white border rounded-lg text-sm hover:border-amber-400 block">
                    {{ $item->title }}
                    @if($item->stage_type === 'off_stage')
                    <span class="text-xs text-gray-400"> · off-stage</span>
                    @endif
                </a>
                @endif
                @if($scopeResultsPublished)
                <a href="{{ route('tenant.fest.item-results', [$event->id, $item->id]) }}"
                   class="p-3 bg-amber-50 border border-amber-100 rounded-lg text-sm hover:border-amber-400 block">
                    {{ $item->title }} — results
                </a>
                @endif
                @endforeach
            </div>
        @endforeach
        @endif
        @if($event->parent_event_id)
        <p class="text-xs text-gray-400 mt-6">Part of a multi-level festival series.</p>
        @endif
    </div>
</section>
@endsection
