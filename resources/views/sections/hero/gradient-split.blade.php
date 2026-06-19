{{-- hero/gradient-split.blade.php — Text left, image right, diagonal gradient bg --}}
@php $theme = $tenant->getSetting('theme', []); @endphp
<section class="relative overflow-hidden py-0 min-h-[560px] flex items-stretch"
         style="background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 60%, #1e1b4b 100%);">

    {{-- Decorative circles --}}
    <div class="absolute inset-0 pointer-events-none overflow-hidden">
        <div class="absolute -top-20 -left-20 w-80 h-80 rounded-full opacity-10 bg-white"></div>
        <div class="absolute top-1/2 right-0 translate-x-1/3 -translate-y-1/2 w-96 h-96 rounded-full opacity-10 bg-white"></div>
        <div class="absolute bottom-0 left-1/3 w-64 h-64 rounded-full opacity-5 bg-white"></div>
    </div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 w-full grid lg:grid-cols-2 gap-0 items-center py-16 lg:py-20">
        {{-- Left: text --}}
        <div class="text-white space-y-5 lg:pr-10">
            @if(!empty($config['eyebrow']))
            <p class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] bg-white/15 backdrop-blur px-4 py-2 rounded-full text-white/90">
                {{ $config['eyebrow'] }}
            </p>
            @endif

            <h1 class="font-heading text-4xl sm:text-5xl xl:text-6xl font-extrabold leading-tight">
                {{ $config['heading'] ?? $tenant->name }}
            </h1>

            @if(!empty($config['tagline']))
            <p class="text-lg sm:text-xl text-white/80 max-w-lg leading-relaxed">{{ $config['tagline'] }}</p>
            @endif

            @if(!empty($config['motto']))
            <p class="text-sm font-semibold text-white/60 italic border-l-4 border-white/30 pl-4">
                "{{ $config['motto'] }}"
            </p>
            @endif

            <div class="flex flex-wrap gap-3 pt-2">
                @if(!empty($config['cta_label']) && !empty($config['cta_url']))
                <a href="{{ $config['cta_url'] }}"
                   class="inline-flex items-center gap-2 bg-white font-bold px-6 py-3 rounded-full text-sm shadow-lg hover:shadow-xl hover:scale-105 transition-all"
                   style="color: var(--color-primary)">
                    {{ $config['cta_label'] }}
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                </a>
                @endif
                @if(!empty($config['secondary_cta_label']) && !empty($config['secondary_cta_url']))
                <a href="{{ $config['secondary_cta_url'] }}"
                   class="inline-flex items-center gap-2 border-2 border-white/50 text-white font-semibold px-6 py-3 rounded-full text-sm hover:bg-white/10 transition">
                    {{ $config['secondary_cta_label'] }}
                </a>
                @endif
            </div>

            {{-- Stats chips --}}
            @php
                use App\Support\SahodayaPublicData;
                $schools = SahodayaPublicData::memberSchools($tenant->id);
            @endphp
            @if($schools->count())
            <div class="flex flex-wrap gap-3 pt-3 border-t border-white/15">
                <div class="flex items-center gap-2 text-sm text-white/80">
                    <span class="text-2xl font-extrabold text-white">{{ $schools->count() }}</span>
                    Member Schools
                </div>
                @if(!empty($config['years_active']))
                <div class="flex items-center gap-2 text-sm text-white/80">
                    <span class="text-2xl font-extrabold text-white">{{ $config['years_active'] }}+</span>
                    Years
                </div>
                @endif
            </div>
            @endif
        </div>

        {{-- Right: image / decorative --}}
        <div class="hidden lg:flex items-center justify-center">
            @if(!empty($config['image']))
            <div class="relative">
                <div class="absolute inset-4 bg-white/10 rounded-3xl blur-xl"></div>
                <img src="{{ $config['image'] }}" alt="{{ $config['heading'] ?? '' }}"
                     class="relative w-full max-w-md rounded-3xl shadow-2xl object-cover aspect-square">
            </div>
            @else
            <div class="relative w-80 h-80">
                <div class="absolute inset-0 bg-white/5 rounded-full"></div>
                <div class="absolute inset-8 bg-white/5 rounded-full"></div>
                <div class="absolute inset-16 bg-white/10 rounded-full flex items-center justify-center">
                    <span class="text-6xl font-extrabold text-white/30 font-heading">
                        {{ substr($tenant->name, 0, 1) }}
                    </span>
                </div>
            </div>
            @endif
        </div>
    </div>
</section>
