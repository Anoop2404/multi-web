{{-- hero/full-slider.blade.php — Full-screen auto-playing hero slider --}}
@php
    use App\Support\TenantStorage;

    $slides = $config['slides'] ?? [];
    if (empty($slides) && (!empty($config['heading']) || !empty($config['bg_image']))) {
        $slides = [[
            'title'                => $config['heading'] ?? $tenant->name,
            'subtitle'             => $config['eyebrow'] ?? '',
            'description'          => $config['tagline'] ?? '',
            'cta_label'            => $config['cta_label'] ?? '',
            'cta_url'              => $config['cta_url'] ?? '',
            'secondary_cta_label'  => $config['secondary_cta_label'] ?? '',
            'secondary_cta_url'    => $config['secondary_cta_url'] ?? '',
            'image_path'           => $config['bg_image'] ?? null,
        ]];
    }

    $slideCount = count($slides) ?: 1;
    $autoplaySeconds = max(3, (int) ($config['autoplay_seconds'] ?? 5));
    $sliderHeight = !empty($config['height_vh']) ? $config['height_vh'] : '75vh';
    $resolveImage = fn (?string $img) => TenantStorage::siteMediaUrl($tenant, $img);
@endphp

<section class="relative w-full overflow-hidden bg-gray-950 group scroll-mt-24"
         x-data="{
            current: 0,
            total: {{ $slideCount }},
            timer: null,
            paused: false,
            init() {
                this.start();
            },
            start() {
                if (this.total <= 1) return;
                this.stop();
                this.timer = setInterval(() => {
                    if (!this.paused) {
                        this.next();
                    }
                }, {{ $autoplaySeconds * 1000 }});
            },
            stop() {
                if (this.timer) clearInterval(this.timer);
            },
            next() {
                this.current = (this.current + 1) % this.total;
            },
            prev() {
                this.current = (this.current - 1 + this.total) % this.total;
            },
            go(index) {
                this.current = index;
            }
         }"
         @mouseenter="paused = true"
         @mouseleave="paused = false">

    <!-- Slider Viewport -->
    <div class="relative w-full min-h-[480px] max-h-[880px]" style="height: {{ $sliderHeight }};">
        @forelse($slides as $index => $slide)
            @php
                $imgUrl = $resolveImage($slide['image_path'] ?? ($slide['image'] ?? null));
            @endphp
            <div x-show="current === {{ $index }}"
                 x-cloak
                 x-transition:enter="transition ease-out duration-700 transform"
                 x-transition:enter-start="opacity-0 scale-105"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-500 transform"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="absolute inset-0 w-full h-full">

                <!-- Background Image or Gradient -->
                @if(!empty($imgUrl))
                    <div class="absolute inset-0 bg-cover bg-center bg-no-repeat transition-transform duration-10000 ease-out transform scale-105"
                         style="background-image: url('{{ $imgUrl }}');">
                    </div>
                @else
                    <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900"></div>
                @endif

                <!-- Dark Gradient Overlay for optimal readability -->
                <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/60 to-black/30"></div>
                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-black/20"></div>

                <!-- Slide Content Container -->
                <div class="relative z-10 max-w-7xl mx-auto h-full px-6 sm:px-8 lg:px-12 flex items-center">
                    <div class="max-w-2xl text-white space-y-4 md:space-y-6 pt-12 pb-16">
                        
                        <!-- Subtitle / Eyebrow Badge -->
                        @if(!empty($slide['subtitle']))
                            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-amber-500/80 text-amber-950 font-bold text-xs md:text-sm tracking-wider uppercase backdrop-blur-md shadow-lg">
                                <span class="w-2 h-2 rounded-full bg-amber-950 animate-pulse"></span>
                                <span>{{ $slide['subtitle'] }}</span>
                            </div>
                        @endif

                        <!-- Slide Title -->
                        @if(!empty($slide['title']))
                            <h1 class="text-3xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight leading-tight drop-shadow-md">
                                {!! nl2br(e($slide['title'])) !!}
                            </h1>
                        @endif

                        <!-- Slide Description -->
                        @if(!empty($slide['description']))
                            <p class="text-slate-200 text-sm sm:text-base md:text-lg leading-relaxed line-clamp-3 max-w-xl font-normal drop-shadow">
                                {{ $slide['description'] }}
                            </p>
                        @endif

                        <!-- Action Buttons -->
                        @if((!empty($slide['cta_label']) && !empty($slide['cta_url'])) || (!empty($slide['secondary_cta_label']) && !empty($slide['secondary_cta_url'])))
                            <div class="flex flex-wrap items-center gap-3 pt-2">
                                @if(!empty($slide['cta_label']) && !empty($slide['cta_url']))
                                    <a href="{{ $slide['cta_url'] }}"
                                       class="inline-flex items-center gap-2 px-6 py-3 md:px-7 md:py-3.5 rounded-xl bg-amber-500 text-slate-950 font-extrabold text-sm md:text-base hover:bg-amber-400 hover:scale-105 transition-all shadow-xl">
                                        <span>{{ $slide['cta_label'] }}</span>
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                    </a>
                                @endif

                                @if(!empty($slide['secondary_cta_label']) && !empty($slide['secondary_cta_url']))
                                    <a href="{{ $slide['secondary_cta_url'] }}"
                                       class="inline-flex items-center gap-2 px-6 py-3 md:px-7 md:py-3.5 rounded-xl bg-white/15 text-white font-bold text-sm md:text-base border border-white/30 hover:bg-white/25 hover:border-white/50 backdrop-blur-md transition-all">
                                        <span>{{ $slide['secondary_cta_label'] }}</span>
                                    </a>
                                @endif
                            </div>
                        @endif

                    </div>
                </div>

            </div>
        @empty
            <div class="relative w-full h-full flex items-center justify-center bg-slate-900 text-white p-8">
                <div class="text-center space-y-3 max-w-xl">
                    <h1 class="text-3xl font-bold">{{ $tenant->name }}</h1>
                    <p class="text-slate-400 text-sm">Welcome to our official website portal.</p>
                </div>
            </div>
        @endforelse
    </div>

    <!-- Navigation Controls (Only if > 1 slide) -->
    @if($slideCount > 1)
        <!-- Previous Arrow Button -->
        <button type="button"
                @click="prev()"
                aria-label="Previous Slide"
                class="absolute left-4 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full bg-black/40 hover:bg-black/75 text-white/80 hover:text-white flex items-center justify-center backdrop-blur-md transition-all border border-white/10 hover:scale-110">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
        </button>

        <!-- Next Arrow Button -->
        <button type="button"
                @click="next()"
                aria-label="Next Slide"
                class="absolute right-4 top-1/2 -translate-y-1/2 z-20 w-11 h-11 rounded-full bg-black/40 hover:bg-black/75 text-white/80 hover:text-white flex items-center justify-center backdrop-blur-md transition-all border border-white/10 hover:scale-110">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
        </button>

        <!-- Pagination Dots -->
        <div class="absolute bottom-6 left-1/2 -translate-x-1/2 z-20 flex items-center gap-2 px-4 py-2 rounded-full bg-black/40 backdrop-blur-md border border-white/10">
            @foreach($slides as $index => $slide)
                <button type="button"
                        @click="go({{ $index }})"
                        aria-label="Go to slide {{ $index + 1 }}"
                        class="h-2.5 rounded-full transition-all duration-300"
                        :class="current === {{ $index }} ? 'w-8 bg-amber-400' : 'w-2.5 bg-white/50 hover:bg-white/80'"></button>
            @endforeach
        </div>
    @endif
</section>
