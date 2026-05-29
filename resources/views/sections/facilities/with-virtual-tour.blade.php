<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-4" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        <div class="grid lg:grid-cols-2 gap-10 items-start">
            <div>
                @if(!empty($config['tour_embed']))
                <div class="aspect-video rounded-xl overflow-hidden shadow-lg">
                    {!! $config['tour_embed'] !!}
                </div>
                @elseif(!empty($config['youtube_id']))
                <div class="aspect-video rounded-xl overflow-hidden shadow-lg">
                    <iframe class="w-full h-full" src="https://www.youtube.com/embed/{{ $config['youtube_id'] }}"
                            frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                </div>
                @endif
                @if(!empty($config['tour_description']))
                <p class="text-gray-600 mt-4 text-sm">{{ $config['tour_description'] }}</p>
                @endif
            </div>
            <div>
                <h3 class="text-xl font-bold font-heading mb-4" style="color: var(--color-secondary)">{{ $config['facilities_title'] ?? 'Our Facilities' }}</h3>
                @if(!empty($config['facilities']) && is_array($config['facilities']))
                <div class="grid sm:grid-cols-2 gap-3">
                    @foreach($config['facilities'] as $facility)
                    <div class="flex items-center gap-3 p-3 rounded-lg bg-gray-50">
                        @if(!empty($facility['icon']))
                        <span class="text-2xl">{{ $facility['icon'] }}</span>
                        @endif
                        <span class="text-sm font-medium text-gray-700">{{ $facility['name'] ?? $facility }}</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</section>