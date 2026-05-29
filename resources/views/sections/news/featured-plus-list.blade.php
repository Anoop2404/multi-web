<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        <div class="grid lg:grid-cols-3 gap-8">
            {{-- Featured --}}
            @if(!empty($config['featured']))
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl overflow-hidden shadow-sm">
                    @if(!empty($config['featured']['image']))
                    <img loading="lazy" src="{{ $config['featured']['image'] }}" alt="{{ $config['featured']['title'] }}" class="w-full h-64 object-cover">
                    @endif
                    <div class="p-6">
                        @if(!empty($config['featured']['date']))
                        <time class="text-xs font-medium" style="color: var(--color-primary)">{{ \Carbon\Carbon::parse($config['featured']['date'])->format('d M Y') }}</time>
                        @endif
                        <h3 class="text-xl font-bold font-heading mt-2 text-gray-800">{{ $config['featured']['title'] }}</h3>
                        <p class="text-gray-600 mt-2 text-sm">{{ $config['featured']['excerpt'] ?? '' }}</p>
                        @if(!empty($config['featured']['url']))
                        <a href="{{ $config['featured']['url'] }}" class="inline-block mt-4 text-sm font-semibold transition" style="color: var(--color-primary)">Read More →</a>
                        @endif
                    </div>
                </div>
            </div>
            @endif
            {{-- List --}}
            <div>
                <h3 class="font-bold font-heading text-lg mb-4 text-gray-800">{{ $config['list_title'] ?? 'Latest Updates' }}</h3>
                @if(!empty($config['articles']) && is_array($config['articles']))
                <div class="space-y-4">
                    @foreach($config['articles'] as $article)
                    <div class="flex gap-3 pb-4 border-b border-gray-100 last:border-0">
                        @if(!empty($article['image']))
                        <img loading="lazy" src="{{ $article['image'] }}" alt="" class="w-12 h-12 rounded object-cover shrink-0">
                        @endif
                        <div>
                            @if(!empty($article['date']))
                            <time class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($article['date'])->format('d M Y') }}</time>
                            @endif
                            <p class="text-sm font-medium text-gray-700">{{ $article['title'] }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</section>