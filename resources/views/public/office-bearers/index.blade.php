@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-6xl mx-auto">
        <a href="/" class="inline-flex items-center gap-1 text-sm font-semibold mb-8 hover:underline" style="color: var(--color-primary)">
            &larr; Back to home
        </a>

        <h1 class="text-3xl md:text-4xl font-bold font-heading text-gray-900 mb-10">Office Bearers</h1>

        @if($bearers->isNotEmpty())
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($bearers as $bearer)
            <div class="v2-card p-6 text-center">
                <div class="w-24 h-24 rounded-full mx-auto mb-4 overflow-hidden flex items-center justify-center"
                     style="background-color: var(--color-primary-light)">
                    @if($bearer->photo_url)
                    <img loading="lazy" src="{{ $bearer->photo_url }}" alt="{{ $bearer->name }}" class="w-full h-full object-cover">
                    @else
                    <span class="text-2xl font-bold font-heading" style="color: var(--color-primary)">{{ substr($bearer->name ?? '?', 0, 1) }}</span>
                    @endif
                </div>
                <h3 class="font-bold font-heading text-gray-900">{{ $bearer->name }}</h3>
                <p class="text-sm font-semibold mt-0.5" style="color: var(--color-primary)">{{ $bearer->role }}</p>
                @if($bearer->school_name)
                <p class="text-sm text-gray-500 mt-1">{{ $bearer->school_name }}</p>
                @endif
                @if($bearer->term_from || $bearer->term_to)
                <p class="text-xs text-gray-400 mt-2">
                    {{ $bearer->term_from }}{{ $bearer->term_from && $bearer->term_to ? '–' : '' }}{{ $bearer->term_to }}
                </p>
                @endif
            </div>
            @endforeach
        </div>
        @else
        <p class="text-gray-500 text-center py-12">Office bearers will be listed here once added.</p>
        @endif
    </div>
</section>
@endsection
