@extends('layouts.public')

@section('content')
<article class="py-12 px-4">
    <div class="max-w-3xl mx-auto">
        <a href="/" class="inline-flex items-center gap-1 text-sm font-semibold mb-8 hover:underline" style="color: var(--color-primary)">
            &larr; Back to home
        </a>

        <h1 class="text-3xl md:text-4xl font-bold font-heading text-gray-900 mb-4">{{ $event->title }}</h1>

        <div class="flex flex-wrap gap-4 text-sm text-gray-600 mb-8">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" style="color: var(--color-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                <time datetime="{{ $event->start_date->toDateString() }}">
                    {{ $event->start_date->format('d M Y') }}
                    @if($event->end_date && $event->end_date->ne($event->start_date))
                        – {{ $event->end_date->format('d M Y') }}
                    @endif
                </time>
            </div>
            @if($event->venue)
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0" style="color: var(--color-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <span>{{ $event->venue }}</span>
            </div>
            @endif
        </div>

        @if($event->image)
        <div class="rounded-2xl overflow-hidden mb-8 shadow-sm">
            <img src="{{ $event->image }}" alt="{{ $event->title }}" class="w-full h-auto object-cover">
        </div>
        @endif

        @if($event->description)
        <div class="prose prose-gray max-w-none text-gray-700 leading-relaxed">
            {!! nl2br(e($event->description)) !!}
        </div>
        @endif
    </div>
</article>
@endsection
