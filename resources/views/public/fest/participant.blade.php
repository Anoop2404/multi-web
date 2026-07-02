@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-lg mx-auto bg-white border rounded-2xl p-6">
        <p class="text-xs text-amber-600 font-bold uppercase">Participant</p>
        <h1 class="text-2xl font-bold font-heading mt-1">
            @if(($public['reference'] ?? '—') !== '—')
            <span class="font-mono">#{{ $public['reference'] }}</span>
            @endif
            @if($public['show_name'] && $public['name'])
            {{ $public['name'] }}
            @elseif(!$public['show_name'])
            <span class="text-base font-normal text-gray-400">Participant (identity hidden until results)</span>
            @endif
        </h1>
        <p class="text-gray-500 text-sm">{{ $event->title }}</p>
        <dl class="mt-6 grid grid-cols-2 gap-3 text-sm">
            <dt class="text-gray-500">Item</dt><dd class="font-medium">{{ $public['item_title'] ?? '—' }}</dd>
            @if($public['team_name'])
            <dt class="text-gray-500">Team</dt><dd>{{ $public['team_name'] }}</dd>
            @endif
            @if($schedule?->sort_order)
            <dt class="text-gray-500">Order</dt><dd>#{{ $schedule->sort_order }}</dd>
            @endif
            @if($public['scheduled_at'])
            <dt class="text-gray-500">Scheduled</dt><dd>{{ $public['scheduled_at']->format('d M, H:i') }} @if($public['stage'])({{ $public['stage'] }})@endif</dd>
            @endif
            @if($public['show_marks'] && $public['position'])
            <dt class="text-gray-500">Position</dt><dd>#{{ $public['position'] }}</dd>
            @endif
            @if($public['show_marks'] && $public['grade'])
            <dt class="text-gray-500">Grade</dt><dd>{{ $public['grade'] }}</dd>
            @endif
            @if($public['show_marks'] && $public['measurement_value'])
            <dt class="text-gray-500">Result</dt><dd>{{ $public['measurement_value'] }} {{ $public['measurement_unit'] ?? '' }}</dd>
            @endif
            @if($public['disqualified'])
            <dt class="text-gray-500">Status</dt><dd class="text-red-600">Disqualified</dd>
            @endif
        </dl>
        @if(!$public['show_marks'] && !$event->results_published)
        <p class="mt-4 text-xs text-gray-400">Individual results appear here after official publication.</p>
        @endif
        <p class="mt-6"><a href="{{ route('tenant.fest.search', $event->id) }}" class="text-sm text-amber-700">← Search again</a></p>
    </div>
</section>
@endsection
