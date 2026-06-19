{{-- hero/full-bleed.blade.php — Immersive full-viewport hero with parallax-like bg --}}
<section class="relative min-h-[80vh] flex items-end overflow-hidden"
         @if(!empty($config['bg_image']))
         style="background-image: url('{{ $config['bg_image'] }}'); background-size: cover; background-position: center;"
         @else
         style="background: linear-gradient(160deg, var(--color-primary) 0%, #0f172a 100%);"
         @endif>

    {{-- Gradient overlay --}}
    <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent"></div>

    {{-- Bottom-anchored content --}}
    <div class="relative z-10 w-full max-w-7xl mx-auto px-4 pb-16 pt-32">
        <div class="max-w-3xl space-y-4">
            @if(!empty($config['eyebrow']))
            <div class="inline-flex items-center gap-2 bg-white/15 backdrop-blur-sm text-white text-xs font-bold uppercase tracking-widest px-4 py-2 rounded-full">
                <span class="w-1.5 h-1.5 bg-amber-400 rounded-full"></span>
                {{ $config['eyebrow'] }}
            </div>
            @endif

            <h1 class="font-heading text-5xl sm:text-6xl lg:text-7xl font-extrabold text-white leading-none">
                {!! nl2br(e($config['heading'] ?? $tenant->name)) !!}
            </h1>

            @if(!empty($config['tagline']))
            <p class="text-xl text-white/75 max-w-xl">{{ $config['tagline'] }}</p>
            @endif

            <div class="flex flex-wrap gap-3 pt-4">
                @if(!empty($config['cta_label']) && !empty($config['cta_url']))
                <a href="{{ $config['cta_url'] }}"
                   class="inline-flex items-center gap-2 bg-white text-gray-900 font-extrabold text-sm px-7 py-3.5 rounded-full shadow-2xl hover:scale-105 transition-all">
                    {{ $config['cta_label'] }}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
                @endif
                @if(!empty($config['secondary_cta_label']) && !empty($config['secondary_cta_url']))
                <a href="{{ $config['secondary_cta_url'] }}"
                   class="inline-flex items-center gap-2 border-2 border-white/50 text-white font-semibold text-sm px-7 py-3.5 rounded-full hover:bg-white/10 transition backdrop-blur-sm">
                    {{ $config['secondary_cta_label'] }}
                </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Scroll indicator --}}
    <div class="absolute bottom-6 left-1/2 -translate-x-1/2 text-white/40 animate-bounce">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </div>
</section>
