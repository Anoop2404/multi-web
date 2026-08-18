@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-5xl mx-auto">
        <a href="/" class="inline-flex items-center gap-1 text-sm font-semibold mb-8 hover:underline" style="color: var(--color-primary)">
            &larr; Back to home
        </a>

        <h1 class="text-3xl md:text-4xl font-bold font-heading text-gray-900 mb-10">Circulars</h1>

        <div class="space-y-4">
            @forelse($circulars as $circular)
            <a href="{{ route('tenant.circulars.download', $circular) }}"
               class="flex items-center justify-between gap-4 bg-white border border-gray-100 rounded-2xl p-5 shadow-sm hover:shadow-md transition">
                <div class="min-w-0">
                    @if($circular->category)
                    <span class="text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full mb-2 inline-block"
                          style="background-color: color-mix(in srgb, var(--color-primary) 15%, transparent); color: var(--color-primary)">
                        {{ $circular->category }}
                    </span>
                    @endif
                    <h2 class="text-lg font-bold text-gray-900 truncate">{{ $circular->title }}</h2>
                    <p class="text-sm text-gray-500 mt-1">
                        @if($circular->circular_number) {{ $circular->circular_number }} &middot; @endif
                        @if($circular->issued_date) {{ $circular->issued_date->format('d M Y') }} @endif
                    </p>
                </div>
                <span class="shrink-0 text-sm font-semibold px-4 py-2 rounded-lg" style="background-color: var(--color-primary); color: #fff">
                    Download
                </span>
            </a>
            @empty
            <p class="text-gray-500 text-center py-12">No circulars published yet.</p>
            @endforelse
        </div>

        @if($circulars->hasPages())
        <div class="mt-10">
            {{ $circulars->links() }}
        </div>
        @endif
    </div>
</section>
@endsection
