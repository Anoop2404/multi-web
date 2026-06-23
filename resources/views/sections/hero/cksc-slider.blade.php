{{-- hero/cksc-slider — Image slider with circular logo overlay (CKSC standalone style) --}}
@php
    use App\Support\TenantStorage;

    $slides = $config['slides'] ?? [];
    if (empty($slides) && !empty($config['bg_image'])) {
        $slides = [[
            'title'   => $config['heading'] ?? $tenant->name,
            'content' => $config['tagline'] ?? '',
            'image'   => $config['bg_image'],
        ]];
    }
    $logo = TenantStorage::siteMediaUrl($tenant, $config['logo'] ?? ($logo ?? null));
    $interval = max(3, (int) ($config['autoplay_seconds'] ?? 5));
    $slideImage = fn (?string $img) => TenantStorage::siteMediaUrl($tenant, $img);
@endphp
<section id="hero" class="relative bg-white scroll-mt-24"
         x-data="{
            current: 0,
            total: {{ count($slides) ?: 1 }},
            interval: null,
            start() {
                if (this.total <= 1) return;
                this.interval = setInterval(() => { this.current = (this.current + 1) % this.total; }, {{ $interval * 1000 }});
            },
            go(i) { this.current = i; }
         }"
         x-init="start()">
    <div class="relative w-full" style="min-height: clamp(420px, 50vw, 685px);">
        @forelse($slides as $i => $slide)
        <div x-show="current === {{ $i }}" x-cloak
             x-transition:enter="transition ease-out duration-500"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             class="absolute inset-0">
            @if(!empty($slide['image']) && $slideImage($slide['image']))
            <img src="{{ $slideImage($slide['image']) }}" alt="{{ $slide['title'] ?? '' }}" class="w-full h-full object-cover">
            @else
            <div class="w-full h-full hero-theme-gradient"></div>
            @endif
            <div class="absolute inset-0" style="background: linear-gradient(270deg, rgba(0,0,0,0) 0%, rgba(0,0,0,0.58) 100%);"></div>
            @if(!empty($slide['title']))
            <h1 class="absolute left-[4%] top-[38%] z-10 text-white font-bold leading-tight max-w-[90%]"
                style="font-size: clamp(1.5rem, 4vw, 2.5rem);">
                {{ $slide['title'] }}
            </h1>
            @endif
            @if(!empty($slide['content']))
            <p class="absolute left-[4%] top-[52%] z-10 text-white/95 max-w-xl leading-relaxed"
               style="font-size: clamp(0.875rem, 2vw, 1rem);">
                {{ $slide['content'] }}
            </p>
            @endif
        </div>
        @empty
        <div class="absolute inset-0 flex items-center justify-center hero-theme-gradient">
            <div class="text-center text-white px-6">
                <h1 class="text-3xl sm:text-4xl font-bold">{{ $config['heading'] ?? $tenant->name }}</h1>
                @if(!empty($config['tagline']))
                <p class="mt-3 text-white/90 max-w-xl mx-auto">{{ $config['tagline'] }}</p>
                @endif
            </div>
        </div>
        @endforelse

        @if(count($slides) > 1)
        <div class="absolute left-[4%] bottom-[5%] z-20 flex gap-2 px-4 py-2 rounded-full bg-black/30 backdrop-blur-sm">
            @foreach($slides as $i => $slide)
            <button type="button" @click="go({{ $i }})"
                    class="w-2.5 h-2.5 rounded-full transition-all"
                    :class="current === {{ $i }} ? 'bg-white scale-125' : 'bg-white/50'"></button>
            @endforeach
        </div>
        @endif
    </div>

    @if($logo)
    <div class="relative w-full flex justify-center pointer-events-none">
        <img src="{{ $logo }}" alt="{{ $tenant->name }}"
             class="absolute -top-[clamp(75px,10vw,120px)] w-[clamp(150px,20vw,286px)] h-[clamp(150px,20vw,286px)] rounded-full object-cover border-[5px] border-white shadow-2xl z-20">
    </div>
    @endif
    <div class="h-[clamp(60px,8vw,100px)]"></div>
</section>

<style>
.hero-theme-gradient {
    background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);
}
</style>
