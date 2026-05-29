<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['videos']) && is_array($config['videos']))
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($config['videos'] as $video)
            <div class="rounded-xl overflow-hidden shadow-sm bg-white">
                <div class="aspect-video">
                    <iframe class="w-full h-full" src="https://www.youtube.com/embed/{{ $video['youtube_id'] }}" frameborder="0" allowfullscreen></iframe>
                </div>
                <div class="p-4">
                    <h3 class="font-semibold text-sm text-gray-800">{{ $video['title'] ?? '' }}</h3>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>