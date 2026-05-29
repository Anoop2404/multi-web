<section class="py-16 px-4 bg-white">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            @if(!empty($config['eyebrow']))
            <p class="text-sm font-semibold uppercase tracking-widest mb-1" style="color: var(--color-primary)">{{ $config['eyebrow'] }}</p>
            @endif
            <h2 class="text-3xl font-bold font-heading text-gray-900">{{ $config['heading'] ?? 'Our Facilities' }}</h2>
            @if(!empty($config['subheading']))
            <p class="text-gray-500 mt-3 max-w-2xl mx-auto">{{ $config['subheading'] }}</p>
            @endif
        </div>

        @if(!empty($config['facilities']) && is_array($config['facilities']))
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-5">
            @foreach($config['facilities'] as $facility)
            <div class="text-center p-5 rounded-xl border border-gray-100 hover:border-primary/30 hover:shadow-sm transition group">
                <div class="w-14 h-14 mx-auto rounded-full flex items-center justify-center mb-3 transition"
                     style="background-color: color-mix(in srgb, var(--color-primary) 10%, transparent)">
                    @if(!empty($facility['emoji']))
                    <span class="text-3xl">{{ $facility['emoji'] }}</span>
                    @else
                    <svg class="w-7 h-7" style="color: var(--color-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    @endif
                </div>
                <p class="font-semibold text-sm text-gray-800">{{ $facility['name'] }}</p>
                @if(!empty($facility['description']))
                <p class="text-xs text-gray-400 mt-1">{{ $facility['description'] }}</p>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>
