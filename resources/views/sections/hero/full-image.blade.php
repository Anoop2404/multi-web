<section class="relative min-h-[560px] flex items-center justify-center text-white overflow-hidden">
    {{-- Background --}}
    @if(!empty($config['background_image']))
    <div class="absolute inset-0 bg-cover bg-center"
         style="background-image: url('{{ $config['background_image'] }}')"></div>
    @else
    <div class="absolute inset-0" style="background-color: var(--color-primary)"></div>
    @endif
    <div class="absolute inset-0 bg-black/50"></div>

    {{-- Content --}}
    <div class="relative z-10 max-w-3xl mx-auto px-6 text-center">
        @if(!empty($config['eyebrow']))
        <p class="text-sm font-semibold uppercase tracking-widest opacity-80 mb-3">{{ $config['eyebrow'] }}</p>
        @endif
        <h1 class="text-4xl md:text-6xl font-bold font-heading leading-tight mb-4 drop-shadow-lg">
            {{ $config['heading'] ?? $tenant->name }}
        </h1>
        @if(!empty($config['tagline']))
        <p class="text-xl opacity-90 mb-8 drop-shadow">{{ $config['tagline'] }}</p>
        @endif
        @if(!empty($config['cta_label']) && !empty($config['cta_url']))
        <a href="{{ $config['cta_url'] }}"
           class="inline-block bg-white font-semibold px-8 py-3 rounded-full hover:bg-opacity-90 transition shadow-lg"
           style="color: var(--color-primary)">
            {{ $config['cta_label'] }}
        </a>
        @endif
    </div>

    {{-- Scroll indicator --}}
    <div class="absolute bottom-6 left-1/2 -translate-x-1/2 animate-bounce opacity-70">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </div>
</section>
