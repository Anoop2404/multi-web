<section class="relative overflow-hidden" style="background-color: var(--color-primary)">
    <div class="max-w-7xl mx-auto grid md:grid-cols-2 min-h-[500px]">
        {{-- Text side --}}
        <div class="flex flex-col justify-center px-8 py-16 text-white">
            @if(!empty($config['eyebrow']))
            <p class="text-sm font-semibold uppercase tracking-widest opacity-80 mb-3">{{ $config['eyebrow'] }}</p>
            @endif
            <h1 class="text-4xl md:text-5xl font-bold font-heading leading-tight mb-4">
                {{ $config['heading'] ?? $tenant->name }}
            </h1>
            @if(!empty($config['tagline']))
            <p class="text-lg opacity-90 mb-8 max-w-md">{{ $config['tagline'] }}</p>
            @endif
            <div class="flex flex-wrap gap-3">
                @if(!empty($config['cta_label']) && !empty($config['cta_url']))
                <a href="{{ $config['cta_url'] }}"
                   class="inline-block bg-white font-semibold px-6 py-3 rounded-full hover:bg-opacity-90 transition"
                   style="color: var(--color-primary)">
                    {{ $config['cta_label'] }}
                </a>
                @endif
                @if(!empty($config['secondary_cta_label']) && !empty($config['secondary_cta_url']))
                <a href="{{ $config['secondary_cta_url'] }}"
                   class="inline-block border border-white/60 text-white font-semibold px-6 py-3 rounded-full hover:bg-white/10 transition">
                    {{ $config['secondary_cta_label'] }}
                </a>
                @endif
            </div>
        </div>
        {{-- Image side --}}
        @if(!empty($config['image']))
        <div class="relative">
            <img loading="lazy" src="{{ $config['image'] }}" alt="{{ $config['image_alt'] ?? $tenant->name }}"
                 class="absolute inset-0 w-full h-full object-cover">
        </div>
        @else
        <div class="hidden md:block bg-black/10"></div>
        @endif
    </div>
</section>
