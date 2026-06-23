@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-lg mx-auto bg-white border rounded-2xl p-6">
        <p class="text-xs text-amber-600 font-bold uppercase">Participant</p>
        <h1 class="text-2xl font-bold font-heading mt-1">#{{ $chestNo }} {{ $participant->student?->name ?? $participant->teacher?->name }}</h1>
        <p class="text-gray-500 text-sm">{{ $event->title }}</p>
        <dl class="mt-6 grid grid-cols-2 gap-3 text-sm">
            <dt class="text-gray-500">Item</dt><dd class="font-medium">{{ $participant->registration?->item?->title ?? '—' }}</dd>
            @if($participant->group?->team_name)
            <dt class="text-gray-500">Team</dt><dd>{{ $participant->group->team_name }}</dd>
            @endif
            @if($schedule?->scheduled_at)
            <dt class="text-gray-500">Scheduled</dt><dd>{{ $schedule->scheduled_at->format('d M, H:i') }} @if($schedule->stage)({{ $schedule->stage }})@endif</dd>
            @endif
            @if($mark?->position)
            <dt class="text-gray-500">Position</dt><dd>#{{ $mark->position }}</dd>
            @endif
            @if($mark?->grade)
            <dt class="text-gray-500">Grade</dt><dd>{{ $mark->grade }}</dd>
            @endif
            @if($participant->disqualified_at)
            <dt class="text-gray-500">Status</dt><dd class="text-red-600">Disqualified</dd>
            @endif
        </dl>
        <p class="mt-6"><a href="{{ route('tenant.fest.search', $event->id) }}" class="text-sm text-amber-700">← Search again</a></p>
    </div>
</section>
@endsection
