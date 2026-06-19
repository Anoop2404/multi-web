@php
    $featured = \App\Models\NewsArticle::where('tenant_id', $tenant->id)
        ->where('is_featured', true)->whereNotNull('published_at')
        ->where('published_at', '<=', now())
        ->orderByDesc('published_at')->first();

    $recents = \App\Models\NewsArticle::where('tenant_id', $tenant->id)
        ->whereNotNull('published_at')->where('published_at', '<=', now())
        ->orderByDesc('published_at')->limit(4)->get();
@endphp
@if($recents->isNotEmpty())
<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        <h2 class="text-3xl font-bold font-heading text-gray-900 mb-10">{{ $config['heading'] ?? 'News & Updates' }}</h2>

        <div class="grid lg:grid-cols-5 gap-6">
            {{-- Featured article --}}
            @if($featured)
            <a href="/news/{{ $featured->slug }}" class="lg:col-span-3 bg-white rounded-2xl overflow-hidden shadow-sm group block hover:shadow-md transition">
                @if($featured->image)
                <div class="aspect-video overflow-hidden">
                    <img loading="lazy" src="{{ $featured->image }}" alt="{{ $featured->title }}"
                         class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                </div>
                @endif
                <div class="p-6">
                    <span class="text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full mb-3 inline-block"
                          style="background-color: color-mix(in srgb, var(--color-primary) 15%, transparent); color: var(--color-primary)">
                        Featured
                    </span>
                    <h3 class="font-bold text-xl text-gray-900 mb-2">{{ $featured->title }}</h3>
                    <p class="text-gray-500 text-sm mb-3">{{ Str::limit(strip_tags($featured->body), 180) }}</p>
                    <span class="text-xs text-gray-400">{{ $featured->published_at->format('d M Y') }}</span>
                </div>
            </a>
            @endif

            {{-- Recent list --}}
            <div class="lg:col-span-2 flex flex-col gap-4">
                @foreach($recents->take(4) as $article)
                <a href="/news/{{ $article->slug }}" class="bg-white rounded-xl p-4 shadow-sm flex gap-4 hover:shadow-md transition group">
                    @if($article->image)
                    <div class="w-20 h-16 rounded-lg overflow-hidden shrink-0">
                        <img loading="lazy" src="{{ $article->image }}" alt="{{ $article->title }}"
                             class="w-full h-full object-cover group-hover:scale-105 transition">
                    </div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <h4 class="font-semibold text-gray-900 text-sm line-clamp-2 mb-1">{{ $article->title }}</h4>
                        <span class="text-xs text-gray-400">{{ $article->published_at->format('d M Y') }}</span>
                    </div>
                </a>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif
