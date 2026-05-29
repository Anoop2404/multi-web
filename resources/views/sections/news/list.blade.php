<section class="py-16 px-4">
    <div class="max-w-4xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['articles']) && is_array($config['articles']))
        <div class="space-y-6">
            @foreach($config['articles'] as $article)
            <article class="flex gap-4 pb-6 border-b border-gray-100">
                @if(!empty($article['image']))
                <img loading="lazy" src="{{ $article['image'] }}" alt="{{ $article['title'] }}" class="w-20 h-20 rounded-lg object-cover shrink-0">
                @endif
                <div>
                    @if(!empty($article['date']))
                    <time class="text-xs font-medium" style="color: var(--color-primary)">{{ \Carbon\Carbon::parse($article['date'])->format('d M Y') }}</time>
                    @endif
                    <h3 class="font-semibold text-gray-800 mt-1">{{ $article['title'] }}</h3>
                    <p class="text-sm text-gray-500 mt-1">{{ $article['excerpt'] ?? '' }}</p>
                </div>
            </article>
            @endforeach
        </div>
        @endif
    </div>
</section>