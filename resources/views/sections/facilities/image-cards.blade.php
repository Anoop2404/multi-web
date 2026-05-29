<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            <h2 class="text-3xl font-bold font-heading text-gray-900">{{ $config['heading'] ?? 'Campus Facilities' }}</h2>
            @if(!empty($config['subheading']))
            <p class="text-gray-500 mt-3">{{ $config['subheading'] }}</p>
            @endif
        </div>

        @if(!empty($config['facilities']) && is_array($config['facilities']))
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($config['facilities'] as $facility)
            <div class="group rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition bg-white">
                @if(!empty($facility['image']))
                <div class="aspect-video overflow-hidden">
                    <img loading="lazy" src="{{ $facility['image'] }}" alt="{{ $facility['name'] }}"
                         class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                </div>
                @else
                <div class="aspect-video flex items-center justify-center text-5xl"
                     style="background-color: color-mix(in srgb, var(--color-primary) 10%, white)">
                    {{ $facility['emoji'] ?? '🏫' }}
                </div>
                @endif
                <div class="p-5">
                    <h3 class="font-bold text-gray-900 text-lg mb-1">{{ $facility['name'] }}</h3>
                    @if(!empty($facility['description']))
                    <p class="text-sm text-gray-500">{{ $facility['description'] }}</p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>
