<section class="relative overflow-hidden min-h-[500px] flex items-center justify-center text-white">
    {{-- Video Background --}}
    @if(!empty($config['video_type']) && $config['video_type'] === 'youtube' && !empty($config['youtube_id']))
    <div class="absolute inset-0 pointer-events-none">
        <iframe class="w-full h-full scale-150"
                src="https://www.youtube.com/embed/{{ $config['youtube_id'] }}?autoplay=1&mute=1&loop=1&playlist={{ $config['youtube_id'] }}&controls=0&showinfo=0"
                frameborder="0" allow="autoplay; encrypted-media" allowfullscreen></iframe>
    </div>
    <div class="absolute inset-0 bg-black/50"></div>
    @elseif(!empty($config['video_type']) && $config['video_type'] === 'mp4' && !empty($config['mp4_url']))
    <video autoplay muted loop playsinline class="absolute inset-0 w-full h-full object-cover">
        <source src="{{ $config['mp4_url'] }}" type="video/mp4">
    </video>
    <div class="absolute inset-0 bg-black/50"></div>
    @else
    <div class="absolute inset-0" style="background-color: var(--color-primary)"></div>
    @endif

    <div class="relative z-10 max-w-4xl mx-auto px-4 text-center py-24">
        @if(!empty($config['eyebrow']))
        <p class="text-sm font-semibold uppercase tracking-widest opacity-80 mb-3">{{ $config['eyebrow'] }}</p>
        @endif
        <h1 class="text-4xl md:text-6xl font-bold font-heading mb-4">
            {{ $config['heading'] ?? $tenant->name }}
        </h1>
        @if(!empty($config['tagline']))
        <p class="text-xl md:text-2xl opacity-90 mb-8 max-w-2xl mx-auto">{{ $config['tagline'] }}</p>
        @endif
        @if(!empty($config['cta_label']) && !empty($config['cta_url']))
        <a href="{{ $config['cta_url'] }}" class="inline-block bg-white text-primary font-semibold px-8 py-3 rounded-full hover:bg-opacity-90 transition"
           style="color: var(--color-primary)">
            {{ $config['cta_label'] }}
        </a>
        @endif
    </div>
</section>