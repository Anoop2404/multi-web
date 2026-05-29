<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['albums']) && is_array($config['albums']))
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($config['albums'] as $album)
            <a href="{{ $album['url'] ?? '#' }}" class="group block rounded-xl overflow-hidden shadow-sm border border-gray-100 bg-white">
                @if(!empty($album['cover_image']))
                <div class="aspect-video overflow-hidden">
                    <img loading="lazy" src="{{ $album['cover_image'] }}" alt="{{ $album['title'] }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                </div>
                @endif
                <div class="p-4">
                    <h3 class="font-bold font-heading text-gray-800 group-hover:text-primary transition">{{ $album['title'] }}</h3>
                    @if(!empty($album['photo_count']))
                    <p class="text-sm text-gray-500 mt-1">{{ $album['photo_count'] }} photos</p>
                    @endif
                </div>
            </a>
            @endforeach
        </div>
        @endif
    </div>
</section>