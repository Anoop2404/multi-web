<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-6xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        <div class="grid lg:grid-cols-2 gap-8">
            <div>
                @if(!empty($config['featured_youtube_id']))
                <div class="aspect-video rounded-xl overflow-hidden shadow-lg">
                    <iframe class="w-full h-full" src="https://www.youtube.com/embed/{{ $config['featured_youtube_id'] }}" frameborder="0" allowfullscreen></iframe>
                </div>
                @endif
            </div>
            <div>
                <h3 class="font-bold font-heading text-lg mb-4" style="color: var(--color-primary)">{{ $config['featured_title'] ?? 'Featured Video' }}</h3>
                @if(!empty($config['videos']) && is_array($config['videos']))
                <div class="space-y-3">
                    @foreach($config['videos'] as $video)
                    <a href="https://www.youtube.com/watch?v={{ $video['youtube_id'] }}" target="_blank" class="flex gap-3 p-3 rounded-lg bg-white shadow-sm hover:shadow transition">
                        <div class="w-20 h-14 rounded overflow-hidden shrink-0 bg-gray-100">
                            <img loading="lazy" src="https://img.youtube.com/vi/{{ $video['youtube_id'] }}/mqdefault.jpg" alt="" class="w-full h-full object-cover">
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $video['title'] ?? '' }}</p>
                        </div>
                    </a>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</section>