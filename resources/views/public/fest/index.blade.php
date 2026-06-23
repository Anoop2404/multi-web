@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold font-heading text-gray-900 mb-2">Festival Portal</h1>
        <p class="text-gray-600 mb-8">Live schedules, results, and participant lookup.</p>
        @forelse($events as $event)
        <a href="{{ route('tenant.fest.show', $event->id) }}" class="block mb-4 p-5 bg-white border rounded-xl hover:border-amber-400 transition">
            <h2 class="font-semibold text-lg">{{ $event->title }}</h2>
            <p class="text-sm text-gray-500">{{ $event->event_start?->format('d M Y') }} · {{ ucfirst(str_replace('_', ' ', $event->status)) }}</p>
        </a>
        @empty
        <p class="text-gray-400">No public festivals at this time.</p>
        @endforelse
    </div>
</section>
@endsection
