@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-5xl mx-auto">
        <a href="/" class="inline-flex items-center gap-1 text-sm font-semibold mb-8 hover:underline" style="color: var(--color-primary)">
            &larr; Back to home
        </a>

        <h1 class="text-3xl md:text-4xl font-bold font-heading text-gray-900 mb-10">News & Announcements</h1>

        <div class="space-y-6">
            @forelse($articles as $article)
            <article class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition">
                <a href="/news/{{ $article->slug }}" class="flex flex-col sm:flex-row gap-0 sm:gap-6">
                    @if($article->image)
                    <div class="sm:w-48 md:w-56 shrink-0 aspect-video sm:aspect-square overflow-hidden">
                        <img loading="lazy" src="{{ $article->image }}" alt="{{ $article->title }}" class="w-full h-full object-cover">
                    </div>
                    @endif
                    <div class="p-6 flex-1">
                        @if($article->category)
                        <span class="text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full mb-2 inline-block"
                              style="background-color: color-mix(in srgb, var(--color-primary) 15%, transparent); color: var(--color-primary)">
                            {{ $article->category }}
                        </span>
                        @endif
                        <h2 class="text-xl font-bold text-gray-900 mb-2">{{ $article->title }}</h2>
                        <p class="text-gray-500 text-sm line-clamp-2 mb-3">{{ Str::limit(strip_tags($article->body), 160) }}</p>
                        <time class="text-xs text-gray-400">{{ $article->published_at->format('d M Y') }}</time>
                    </div>
                </a>
            </article>
            @empty
            <p class="text-gray-500 text-center py-12">No news articles published yet.</p>
            @endforelse
        </div>

        @if($articles->hasPages())
        <div class="mt-10">
            {{ $articles->links() }}
        </div>
        @endif
    </div>
</section>
@endsection
