@php
    $limit = $config['limit'] ?? 6;
    $articles = \App\Models\NewsArticle::where('tenant_id', $tenant->id)
        ->whereNotNull('published_at')->where('published_at', '<=', now())
        ->orderByDesc('published_at')->limit($limit)->get();
@endphp
@if($articles->isNotEmpty())
<section class="py-16 px-4 bg-white">
    <div class="max-w-7xl mx-auto">
        {{-- Heading --}}
        <div class="flex items-end justify-between mb-10">
            <div>
                @if(!empty($config['eyebrow']))
                <p class="text-sm font-semibold uppercase tracking-widest mb-1" style="color: var(--color-primary)">{{ $config['eyebrow'] }}</p>
                @endif
                <h2 class="text-3xl font-bold font-heading text-gray-900">
                    {{ $config['heading'] ?? 'Latest News' }}
                </h2>
            </div>
            @php $viewAllUrl = $config['view_all_url'] ?? '/news'; @endphp
            <a href="{{ $viewAllUrl }}" class="text-sm font-semibold hover:underline" style="color: var(--color-primary)">
                View all &rarr;
            </a>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($articles as $article)
            <article class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition group">
                <a href="/news/{{ $article->slug }}" class="block">
                    @if($article->image)
                    <div class="aspect-video overflow-hidden">
                        <img loading="lazy" src="{{ $article->image }}" alt="{{ $article->title }}"
                             class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                    </div>
                    @endif
                    <div class="p-5">
                        @if($article->category)
                        <span class="text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full mb-3 inline-block"
                              style="background-color: color-mix(in srgb, var(--color-primary) 15%, transparent); color: var(--color-primary)">
                            {{ $article->category }}
                        </span>
                        @endif
                        <h3 class="font-bold text-gray-900 text-lg leading-snug mb-2 line-clamp-2 group-hover:text-primary transition"
                            style="--tw-text-opacity:1">
                            {{ $article->title }}
                        </h3>
                        <p class="text-gray-500 text-sm line-clamp-2 mb-4">
                            {{ Str::limit(strip_tags($article->body), 120) }}
                        </p>
                        <div class="flex items-center justify-between text-xs text-gray-400">
                            <span>{{ $article->published_at->format('d M Y') }}</span>
                            <span class="font-semibold" style="color: var(--color-primary)">Read more &rarr;</span>
                        </div>
                    </div>
                </a>
            </article>
            @endforeach
        </div>
    </div>
</section>
@endif
