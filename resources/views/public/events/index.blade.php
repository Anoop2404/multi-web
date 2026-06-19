@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-5xl mx-auto">
        <a href="/" class="inline-flex items-center gap-1 text-sm font-semibold mb-8 hover:underline" style="color: var(--color-primary)">
            &larr; Back to home
        </a>

        <h1 class="text-3xl md:text-4xl font-bold font-heading text-gray-900 mb-10">Events</h1>

        <div class="grid sm:grid-cols-2 gap-6">
            @forelse($events as $event)
            <a href="/events/{{ $event->slug }}" class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition group block">
                @if($event->image)
                <div class="aspect-video overflow-hidden">
                    <img loading="lazy" src="{{ $event->image }}" alt="{{ $event->title }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                </div>
                @endif
                <div class="p-5">
                    <h2 class="font-bold text-gray-900 text-lg mb-2">{{ $event->title }}</h2>
                    <p class="text-sm text-gray-500 mb-1">{{ $event->start_date->format('d M Y') }}
                        @if($event->end_date && $event->end_date->ne($event->start_date))
                            – {{ $event->end_date->format('d M Y') }}
                        @endif
                    </p>
                    @if($event->venue)
                    <p class="text-sm text-gray-400">{{ $event->venue }}</p>
                    @endif
                </div>
            </a>
            @empty
            <p class="text-gray-500 col-span-full text-center py-12">No events listed yet.</p>
            @endforelse
        </div>

        @if($events->hasPages())
        <div class="mt-10">
            {{ $events->links() }}
        </div>
        @endif
    </div>
</section>
@endsection
