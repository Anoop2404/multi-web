@extends('layouts.public')

@section('content')
@php
    $images   = $album->items->pluck('image_path')->values()->all();
    $captions = $album->items->map(fn ($item) => $item->caption ?: $album->title)->values()->all();
@endphp
<section class="py-12 px-4">
    <div class="max-w-6xl mx-auto">
        <a href="/" class="inline-flex items-center gap-1 text-sm font-semibold mb-8 hover:underline" style="color: var(--color-primary)">
            &larr; Back to home
        </a>

        <h1 class="text-3xl md:text-4xl font-bold font-heading text-gray-900 mb-3">{{ $album->title }}</h1>
        @if($album->description)
        <p class="text-gray-600 mb-8 max-w-2xl">{{ $album->description }}</p>
        @endif

        @if($album->items->isNotEmpty())
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($album->items as $index => $item)
            <button type="button"
                    @click="$dispatch('lightbox-open', { images: {{ json_encode($images) }}, captions: {{ json_encode($captions) }}, index: {{ $index }} })"
                    class="rounded-xl overflow-hidden shadow-sm hover:shadow-md transition group text-left"
                    aria-label="View {{ $item->caption ?: 'photo' }}">
                <img loading="lazy" src="{{ $item->image_path }}" alt="{{ $item->caption ?: $album->title }}"
                     class="w-full aspect-square object-cover group-hover:scale-105 transition duration-500">
                @if($item->caption)
                <p class="p-3 text-sm text-gray-600">{{ $item->caption }}</p>
                @endif
            </button>
            @endforeach
        </div>
        @else
        <p class="text-gray-500 text-center py-12">This album has no photos yet.</p>
        @endif
    </div>
</section>
@endsection
