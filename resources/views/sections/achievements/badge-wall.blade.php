<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['badges']) && is_array($config['badges']))
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            @foreach($config['badges'] as $badge)
            <div class="bg-white rounded-xl p-4 shadow-sm border border-gray-100 text-center hover:shadow-md transition group">
                @if(!empty($badge['image']))
                <div class="aspect-square overflow-hidden rounded-lg mb-3">
                    <img loading="lazy" src="{{ $badge['image'] }}" alt="{{ $badge['title'] }}" class="w-full h-full object-cover group-hover:scale-110 transition duration-300">
                </div>
                @endif
                <p class="text-xs font-medium text-gray-700">{{ $badge['title'] ?? '' }}</p>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>