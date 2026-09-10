@extends('layouts.public')

@section('content')
@php
    $pageContent = data_get($siteContent ?? [], 'pages.gallery', []);
@endphp
<section class="py-10 sm:py-12 px-4">
    <div class="max-w-6xl mx-auto">
        <a href="/" class="inline-flex items-center gap-1 text-sm font-semibold mb-8 hover:underline" style="color: var(--color-primary)">
            &larr; {{ data_get($siteContent ?? [], 'common.back_to_home', 'Back to home') }}
        </a>

        <h1 class="text-3xl sm:text-4xl font-bold font-heading text-gray-900 mb-8 sm:mb-10">{{ $pageContent['title'] ?? 'Gallery' }}</h1>

        @if($albums->isNotEmpty())
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($albums as $album)
            <a href="{{ route('tenant.gallery.show', $album->slug) }}" class="v2-card overflow-hidden group block">
                <div class="aspect-[4/3] bg-slate-100 overflow-hidden">
                    @if($album->cover_url)
                    <img loading="lazy" src="{{ $album->cover_url }}" alt="{{ $album->title }}"
                         class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                    @else
                    <div class="w-full h-full flex items-center justify-center" style="background-color: var(--color-primary-light)">
                        <svg class="w-10 h-10" style="color: var(--color-primary)" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909M3 3h18v18H3V3z"/></svg>
                    </div>
                    @endif
                </div>
                <div class="p-4">
                    <h3 class="font-bold font-heading text-gray-900">{{ $album->title }}</h3>
                    @if($album->description)
                    <p class="text-sm text-gray-500 mt-1 line-clamp-2">{{ $album->description }}</p>
                    @endif
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 mt-3">
                        {{ $album->items_count }} {{ $album->items_count === 1 ? ($pageContent['photo_singular'] ?? 'photo') : ($pageContent['photo_plural'] ?? 'photos') }}
                    </p>
                </div>
            </a>
            @endforeach
        </div>

        @if($albums->hasPages())
        <div class="mt-10">
            {{ $albums->links() }}
        </div>
        @endif
        @else
        <div class="rounded-2xl border border-dashed p-8 sm:p-12 text-center" style="border-color: var(--color-primary-border); background-color: var(--color-primary-light)">
            <h2 class="font-heading text-xl font-bold text-gray-900">{{ $pageContent['empty_title'] ?? 'Gallery updates coming soon' }}</h2>
            <p class="text-gray-500 mt-2 max-w-xl mx-auto">{{ $pageContent['empty_description'] ?? 'Event albums and photos will appear here once added.' }}</p>
        </div>
        @endif
    </div>
</section>
@endsection
