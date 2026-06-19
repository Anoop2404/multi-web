@extends('layouts.public')

@section('content')
<article class="py-12 px-4">
    <div class="max-w-3xl mx-auto">
        <a href="/" class="inline-flex items-center gap-1 text-sm font-semibold mb-8 hover:underline" style="color: var(--color-primary)">
            &larr; Back to home
        </a>

        @if($article->category)
        <span class="text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full mb-4 inline-block"
              style="background-color: color-mix(in srgb, var(--color-primary) 15%, transparent); color: var(--color-primary)">
            {{ $article->category }}
        </span>
        @endif

        <h1 class="text-3xl md:text-4xl font-bold font-heading text-gray-900 mb-3">{{ $article->title }}</h1>

        @if($article->published_at)
        <time datetime="{{ $article->published_at->toIso8601String() }}" class="text-sm text-gray-500 mb-8 block">
            {{ $article->published_at->format('d M Y') }}
        </time>
        @endif

        @if($article->image)
        <div class="rounded-2xl overflow-hidden mb-8 shadow-sm">
            <img src="{{ $article->image }}" alt="{{ $article->title }}" class="w-full h-auto object-cover">
        </div>
        @endif

        <div class="prose prose-gray max-w-none text-gray-700 leading-relaxed">
            {!! nl2br(e($article->body)) !!}
        </div>
    </div>
</article>
@endsection
