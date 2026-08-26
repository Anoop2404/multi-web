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
    $autoplayMs = max(1, (int) ($config['autoplay_seconds'] ?? 3)) * 1000;
@endphp
<section class="relative overflow-hidden text-white border-b border-white/10 bg-slate-950 min-h-[78vh] lg:min-h-[620px] flex items-center"
         x-data="{ activeSlide: 0, count: {{ $totalSlides }}, init() { if (this.count > 1) setInterval(() => { this.activeSlide = (this.activeSlide + 1) % this.count }, {{ $autoplayMs }}) } }">

    {{-- Full-width rotating background: slide 0 is a branded surface (never a stock photo), slides 1+ are configured photos/videos --}}
    <div class="absolute inset-0">
        <div x-show="activeSlide === 0" x-transition:enter="transition ease-out duration-700" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="absolute inset-0 overflow-hidden">
            {{-- True split: distinct surfaces for the reading side (left) and the visual side (right) --}}
            <div class="absolute inset-0 grid lg:grid-cols-2">
                <div style="background: linear-gradient(165deg, var(--color-primary) 0%, #050b16 100%);"></div>
                <div class="hidden lg:block relative overflow-hidden" style="background: linear-gradient(200deg, #050b16 0%, var(--color-secondary) 100%, var(--color-primary) 160%);">
                    <div class="absolute inset-0 opacity-[0.07] bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:28px_28px] pointer-events-none"></div>
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[38rem] h-[38rem] rounded-full opacity-[0.22] pointer-events-none" style="background: radial-gradient(circle, var(--color-accent), transparent 62%);"></div>

                    {{-- Network-of-schools motif, redrawn as a clear hub-and-spoke: the tenant sits at the center of its own network --}}
                    <svg class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[30rem] h-[30rem] pointer-events-none" viewBox="0 0 520 520" fill="none" aria-hidden="true">
                        <g stroke="var(--color-accent)" stroke-width="1.5" opacity="0.45">
                            <line x1="260" y1="260" x2="130" y2="150"/>
                            <line x1="260" y1="260" x2="390" y2="140"/>
                            <line x1="260" y1="260" x2="430" y2="290"/>
                            <line x1="260" y1="260" x2="340" y2="410"/>
                            <line x1="260" y1="260" x2="150" y2="390"/>
                            <line x1="260" y1="260" x2="90" y2="260"/>
                        </g>
                        <g stroke="var(--color-secondary)" stroke-width="1" opacity="0.25">
                            <line x1="130" y1="150" x2="390" y2="140"/>
                            <line x1="150" y1="390" x2="340" y2="410"/>
                            <line x1="90" y1="260" x2="130" y2="150"/>
                            <line x1="430" y1="290" x2="340" y2="410"/>
                        </g>
                        <circle cx="130" cy="150" r="7" fill="white" opacity="0.6"/>
                        <circle cx="390" cy="140" r="8" fill="var(--color-accent)" opacity="0.85"/>
                        <circle cx="430" cy="290" r="6" fill="white" opacity="0.55"/>
                        <circle cx="340" cy="410" r="9" fill="var(--color-secondary)" opacity="0.75"/>
                        <circle cx="150" cy="390" r="7" fill="white" opacity="0.5"/>
                        <circle cx="90" cy="260" r="6" fill="white" opacity="0.45"/>
                        <circle cx="260" cy="260" r="60" fill="none" stroke="var(--color-accent)" stroke-width="2" opacity="0.55"/>
                        <circle cx="260" cy="260" r="60" fill="var(--color-primary)" opacity="0.25"/>
                    </svg>

                    {{-- Logo sits at the hub of the network — a real identity anchor, not a faded background bleed --}}
                    @if($watermarkLogo)
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-24 h-24 rounded-full bg-white shadow-2xl border-4 border-white/25 flex items-center justify-center p-3">
                        <img src="{{ $watermarkLogo }}" alt="" class="w-full h-full object-contain">
                    </div>
                    @else
                    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-20 h-20 rounded-full flex items-center justify-center text-2xl font-extrabold font-heading text-white shadow-2xl border-4 border-white/25" style="background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));">
                        {{ mb_substr($tenant->name ?? 'S', 0, 1) }}
                    </div>
                    @endif
                </div>
                {{-- Seam between the two halves makes the split feel deliberate --}}
                <div class="hidden lg:block absolute top-0 bottom-0 left-1/2 w-px bg-gradient-to-b from-transparent via-white/15 to-transparent"></div>
            </div>

            {{-- Mobile: the right visual panel is hidden, so keep a light glow behind the content instead --}}
            <div class="lg:hidden absolute -top-1/4 -right-[10%] w-[30rem] h-[30rem] rounded-full opacity-[0.14] pointer-events-none" style="background: radial-gradient(circle, var(--color-accent), transparent 65%);"></div>
            <div class="lg:hidden absolute inset-0 opacity-[0.05] bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:26px_26px] pointer-events-none"></div>
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

    {{-- Content area — each slide is fully standalone. Slide 0 carries the tenant's identity, search
         and stats; every photo/video slide replaces all of it with its own heading and (optional) CTA. --}}
    <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full py-16">
        <div x-show="activeSlide === 0" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="lg:grid lg:grid-cols-2 lg:gap-10 lg:items-center">
        <div class="max-w-xl space-y-7">
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

            {{-- Primary task: search the directory, right inside the hero — admin can turn this off --}}
            @if($config['show_search'] ?? true)
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
                <p class="mt-3 text-sm text-slate-300">
                    New school? <a href="{{ route('school-register.create') }}" class="font-bold text-white hover:text-accent underline underline-offset-2 transition">Apply for membership →</a>
                </p>
            </div>
            @endif

            {{-- Additional free-form text + button — usable on its own if search is turned off, or alongside it --}}
            @if(!empty($config['secondary_text']) || (!empty($config['cta_label']) && !empty($config['cta_url'])))
            <div class="space-y-4">
                @if(!empty($config['secondary_text']))
                <p class="text-base text-slate-200 leading-relaxed max-w-xl">{{ $config['secondary_text'] }}</p>
                @endif
                @if(!empty($config['cta_label']) && !empty($config['cta_url']))
                <a href="{{ $config['cta_url'] }}" class="v2-btn-accent inline-flex items-center gap-2 font-bold px-6 py-3.5 rounded-xl shadow-lg text-sm">
                    {{ $config['cta_label'] }}
                </a>
                @endif
            </div>
            @endif

            {{-- Structured fact cards — real network data, only on the static slide — admin can turn this off --}}
            @if(($config['show_stats'] ?? true) && ($statSchools || !empty($config['years_active']) || $districts->isNotEmpty()))
            <div class="flex flex-wrap gap-3 pt-4">
                @if($statSchools)
                <div class="flex items-center gap-3 bg-white/[0.07] border border-white/15 rounded-xl px-4 py-3 backdrop-blur-sm">
                    <div class="w-9 h-9 rounded-lg bg-accent/20 flex items-center justify-center shrink-0">
                        <svg class="w-4.5 h-4.5 text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3L2 8l10 5 10-5-10-5zM2 15l10 5 10-5M2 11.5V17M22 11.5V17"/></svg>
                    </div>
                    <div>
                        <p class="text-xl font-extrabold font-heading text-white leading-none">{{ $statSchools }}</p>
                        <p class="text-[11px] text-slate-300 font-medium mt-1">CBSE Schools</p>
                    </div>
                </div>
                @endif
                @if(!empty($config['years_active']))
                <div class="flex items-center gap-3 bg-white/[0.07] border border-white/15 rounded-xl px-4 py-3 backdrop-blur-sm">
                    <div class="w-9 h-9 rounded-lg bg-accent/20 flex items-center justify-center shrink-0">
                        <svg class="w-4.5 h-4.5 text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <p class="text-xl font-extrabold font-heading text-white leading-none">{{ $config['years_active'] }}+</p>
                        <p class="text-[11px] text-slate-300 font-medium mt-1">Years Active</p>
                    </div>
                </div>
                @endif
                @if($districts->isNotEmpty())
                <div class="flex items-center gap-3 bg-white/[0.07] border border-white/15 rounded-xl px-4 py-3 backdrop-blur-sm">
                    <div class="w-9 h-9 rounded-lg bg-accent/20 flex items-center justify-center shrink-0">
                        <svg class="w-4.5 h-4.5 text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.828 0l-4.243-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <p class="text-xl font-extrabold font-heading text-white leading-none">{{ $districts->count() }}</p>
                        <p class="text-[11px] text-slate-300 font-medium mt-1">{{ $districts->count() === 1 ? 'District' : 'Districts' }} Covered</p>
                    </div>
                </div>
                @endif
            </div>
            @endif
        </div>
        </div>

        {{-- Each media slide is a fully standalone slide — its own eyebrow, heading, description and button --}}
        @foreach($mediaSlides as $i => $slide)
        <div x-show="activeSlide === {{ $i + 1 }}" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 translate-y-2" x-transition:enter-end="opacity-100 translate-y-0" class="max-w-2xl space-y-5" style="display: none;">
            @if(!empty($slide['eyebrow']))
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-widest bg-white/10 px-4 py-1.5 rounded-full border border-white/20 text-white/90">
                <span class="w-1.5 h-1.5 rounded-full bg-accent"></span>
                <span>{{ $slide['eyebrow'] }}</span>
            </div>
            @endif

            <h1 class="text-4xl sm:text-5xl lg:text-[3.25rem] font-extrabold font-heading text-white tracking-tight leading-[1.1] drop-shadow-sm">
                {{ $slide['title'] }}
            </h1>

            @if(!empty($slide['caption']))
            <p class="text-base sm:text-lg text-slate-200 font-normal leading-relaxed max-w-xl">
                {{ $slide['caption'] }}
            </p>
            @endif

            @if(!empty($slide['cta_label']) && !empty($slide['cta_url']))
            <a href="{{ $slide['cta_url'] }}" class="v2-btn-accent inline-flex items-center gap-2 font-bold px-6 py-3.5 rounded-xl shadow-lg text-sm">
                {{ $slide['cta_label'] }}
            </a>
            @endif
        </div>
        @endforeach
    </div>

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
