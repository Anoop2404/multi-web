@php
    $previewSchools = \App\Support\SahodayaPublicData::memberSchools($tenant->id)->map(fn ($school) => [
        'name' => $school->name,
        'district' => $school->city ?? $school->district,
    ]);
    $previewTotal = $previewSchools->count();
    $districts = $previewSchools->pluck('district')->filter()->unique()->sort()->values();
    $statSchools = $previewTotal > 0 ? (string) $previewTotal : null;
    $watermarkLogo = \App\Support\TenantBranding::logoUrl($tenant);

    // Media slides (photo or video) follow the data slide, in the order configured
    $mediaSlides = collect($config['slides'] ?? [])->filter(fn ($slide) => !empty($slide['image']) || !empty($slide['video']))->values();
    $totalSlides = 1 + $mediaSlides->count();
@endphp
<section id="hero" class="relative overflow-hidden text-white border-b border-white/10 bg-slate-950 min-h-[78vh] lg:min-h-[620px] flex items-center"
         x-data="{ activeSlide: 0, count: {{ $totalSlides }}, init() { if (this.count > 1) setInterval(() => { this.activeSlide = (this.activeSlide + 1) % this.count }, 7000) } }">

    {{-- Full-width rotating background: slide 0 is a branded surface (never a stock photo), slides 1+ are configured photos/videos --}}
    <div class="absolute inset-0">
        <div x-show="activeSlide === 0" x-transition:enter="transition ease-out duration-700" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="absolute inset-0 overflow-hidden"
             style="background: linear-gradient(160deg, var(--color-primary) 0%, #071224 75%);">
            {{-- Restrained brand-accent glow for depth --}}
            <div class="absolute -top-1/4 -right-[10%] w-[45rem] h-[45rem] rounded-full opacity-[0.16] pointer-events-none" style="background: radial-gradient(circle, var(--color-accent), transparent 65%);"></div>
            <div class="absolute -bottom-1/3 -left-[5%] w-[32rem] h-[32rem] rounded-full opacity-[0.10] pointer-events-none" style="background: radial-gradient(circle, var(--color-secondary), transparent 65%);"></div>
            {{-- Oversized, faint logo watermark — makes the default slide feel finished and on-brand with zero photos configured --}}
            @if($watermarkLogo)
            <img src="{{ $watermarkLogo }}" alt="" aria-hidden="true"
                 class="absolute -right-[8%] bottom-[-15%] w-[38rem] max-w-none h-auto opacity-[0.07] pointer-events-none select-none grayscale brightness-[3]">
            @endif
            <div class="absolute inset-0 opacity-[0.06] bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:26px_26px] pointer-events-none"></div>

            {{-- Abstract "network of schools" motif — a real second visual anchor for the default slide, not just text-on-gradient --}}
            <svg class="hidden lg:block absolute right-[4%] top-1/2 -translate-y-1/2 w-[34rem] h-[34rem] pointer-events-none" viewBox="0 0 520 520" fill="none" aria-hidden="true">
                <g stroke="var(--color-accent)" stroke-width="1.25" opacity="0.35">
                    <line x1="120" y1="140" x2="260" y2="90"/>
                    <line x1="260" y1="90" x2="410" y2="150"/>
                    <line x1="120" y1="140" x2="150" y2="290"/>
                    <line x1="260" y1="90" x2="240" y2="270"/>
                    <line x1="410" y1="150" x2="380" y2="300"/>
                    <line x1="150" y1="290" x2="240" y2="270"/>
                    <line x1="240" y1="270" x2="380" y2="300"/>
                    <line x1="150" y1="290" x2="200" y2="410"/>
                    <line x1="240" y1="270" x2="290" y2="420"/>
                    <line x1="380" y1="300" x2="330" y2="430"/>
                    <line x1="200" y1="410" x2="290" y2="420"/>
                    <line x1="290" y1="420" x2="330" y2="430"/>
                </g>
                <g stroke="var(--color-secondary)" stroke-width="1" opacity="0.2">
                    <line x1="120" y1="140" x2="410" y2="150"/>
                    <line x1="150" y1="290" x2="380" y2="300"/>
                </g>
                <circle cx="260" cy="90" r="10" fill="var(--color-accent)" opacity="0.9"/>
                <circle cx="120" cy="140" r="7" fill="white" opacity="0.6"/>
                <circle cx="410" cy="150" r="8" fill="white" opacity="0.5"/>
                <circle cx="150" cy="290" r="6" fill="white" opacity="0.5"/>
                <circle cx="240" cy="270" r="12" fill="var(--color-accent)" opacity="0.7"/>
                <circle cx="380" cy="300" r="7" fill="white" opacity="0.55"/>
                <circle cx="200" cy="410" r="6" fill="white" opacity="0.45"/>
                <circle cx="290" cy="420" r="9" fill="var(--color-secondary)" opacity="0.6"/>
                <circle cx="330" cy="430" r="6" fill="white" opacity="0.4"/>
            </svg>
        </div>

        @foreach($mediaSlides as $i => $slide)
        <div x-show="activeSlide === {{ $i + 1 }}" x-transition:enter="transition ease-out duration-700" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="absolute inset-0">
            @if(!empty($slide['video']))
            <iframe src="{{ $slide['video'] }}" class="w-full h-full border-0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
            @else
            <img src="{{ $slide['image'] }}" alt="{{ $slide['caption'] ?? '' }}" loading="{{ $i === 0 ? 'eager' : 'lazy' }}" class="v2-media w-full h-full object-cover">
            @endif
        </div>
        @endforeach
    </div>

    {{-- Persistent scrim so heading/search/stats stay legible over any slide --}}
    <div class="absolute inset-0 bg-gradient-to-r from-slate-950/92 via-slate-950/70 to-slate-950/35 pointer-events-none"></div>
    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent pointer-events-none"></div>

    {{-- Persistent content — identity, search, and network facts stay on top regardless of which slide is showing --}}
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full py-16">
        <div class="max-w-2xl space-y-7">
            <div>
                <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-widest bg-white/10 px-4 py-1.5 rounded-full border border-white/20 text-white/90">
                    <span class="w-1.5 h-1.5 rounded-full bg-accent"></span>
                    <span>{{ $config['eyebrow'] ?? 'CBSE School Network' }}</span>
                </div>

                <h1 class="mt-4 text-4xl sm:text-5xl lg:text-[3.25rem] font-extrabold font-heading text-white tracking-tight leading-[1.1] drop-shadow-sm">
                    {{ $config['heading'] ?? $tenant->name }}
                </h1>

                <p class="mt-3 text-base sm:text-lg text-slate-200 font-normal leading-relaxed max-w-xl">
                    {{ $config['tagline'] ?? ('Find schools, programmes and shared opportunities across ' . ($tenant->region->name ?? ($tenant->name ?? 'the network')) . '.') }}
                </p>
            </div>

            {{-- Primary task: search the directory, right inside the hero --}}
            <div x-data="{ query: '' }"
                 @submit.prevent="
                     const input = document.getElementById('school-query');
                     if (input) { input.value = query; input.dispatchEvent(new Event('input', { bubbles: true })); }
                     document.getElementById('member-schools')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
                 ">
                <form class="flex flex-col sm:flex-row gap-2.5" role="search" aria-label="Search member schools">
                    <label for="hero-school-query" class="sr-only">Search by school name, district or location</label>
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400">
                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                        </div>
                        <input id="hero-school-query" x-model="query" type="search"
                               placeholder="Search by school name, district or location…"
                               class="w-full pl-11 pr-4 py-3.5 rounded-xl bg-white text-slate-900 placeholder:text-slate-400 text-sm font-medium border border-transparent focus:outline-none focus:ring-2 focus:ring-white/40 shadow-lg">
                    </div>
                    <button type="submit" class="v2-btn-accent font-bold px-6 py-3.5 rounded-xl shadow-lg text-sm shrink-0">
                        Find a school
                    </button>
                </form>
            </div>

            {{-- Structured fact strip — real network data, stays visible on every slide --}}
            @if($statSchools || !empty($config['years_active']) || $districts->isNotEmpty())
            <div class="flex flex-wrap items-center gap-x-6 gap-y-3 pt-5 border-t border-white/20">
                @if($statSchools)
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-extrabold font-heading text-white">{{ $statSchools }}</span>
                    <span class="text-xs text-slate-300 font-medium">CBSE Schools</span>
                </div>
                <div class="w-px h-6 bg-white/20"></div>
                @endif
                @if(!empty($config['years_active']))
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-extrabold font-heading text-accent">{{ $config['years_active'] }}+</span>
                    <span class="text-xs text-slate-300 font-medium">Years Active</span>
                </div>
                <div class="w-px h-6 bg-white/20"></div>
                @endif
                @if($districts->isNotEmpty())
                <div class="flex items-baseline gap-2">
                    <span class="text-2xl font-extrabold font-heading text-white">{{ $districts->count() }}</span>
                    <span class="text-xs text-slate-300 font-medium">{{ $districts->count() === 1 ? 'District' : 'Districts' }} Covered</span>
                </div>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- Caption + dots for the active slide, bottom-right, out of the content's way --}}
    @foreach($mediaSlides as $i => $slide)
    @if(!empty($slide['caption']))
    <p x-show="activeSlide === {{ $i + 1 }}" class="absolute bottom-16 sm:bottom-6 right-4 sm:right-6 z-10 text-sm font-semibold text-white/90 drop-shadow max-w-xs text-right pointer-events-none">{{ $slide['caption'] }}</p>
    @endif
    @endforeach

    @if($totalSlides > 1)
    <div class="absolute bottom-4 right-4 sm:right-6 z-10 flex items-center gap-1.5">
        @for($i = 0; $i < $totalSlides; $i++)
        <button @click="activeSlide = {{ $i }}"
                :class="activeSlide === {{ $i }} ? 'w-6 bg-accent' : 'w-2 bg-white/50 hover:bg-white/75'"
                class="h-2 rounded-full transition-all duration-300 cursor-pointer" aria-label="Show slide {{ $i + 1 }}"></button>
        @endfor
    </div>
    @endif
</section>
