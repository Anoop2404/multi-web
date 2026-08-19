{{-- about_sahodaya/with-timeline.blade.php — History/milestones timeline section --}}
@php $milestones = collect($config['milestones'] ?? [])->filter(fn ($m) => !empty($m['title']))->values(); @endphp
@if($milestones->isNotEmpty())
<section class="py-14 lg:py-20 px-4 sm:px-6 lg:px-8 bg-slate-50 border-t border-slate-200">
    <div class="max-w-5xl mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-12 space-y-2">
            @if(!empty($config['eyebrow']))
            <span class="inline-block text-xs font-bold uppercase tracking-widest v2-badge-primary px-3.5 py-1.5 rounded-full">
                {{ $config['eyebrow'] }}
            </span>
            @endif
            <h2 class="text-3xl sm:text-4xl font-extrabold font-heading text-slate-900 tracking-tight">
                {{ $config['heading'] ?? 'Our Journey' }}
            </h2>
            @if(!empty($config['subtext']))
            <p class="text-sm sm:text-base text-slate-600 font-normal leading-relaxed">{{ $config['subtext'] }}</p>
            @endif
        </div>

        <div class="relative">
            {{-- Centre line --}}
            <div class="absolute left-1/2 top-0 bottom-0 w-0.5 -translate-x-1/2 hidden md:block"
                 style="background: linear-gradient(to bottom, var(--color-primary), var(--color-accent), transparent)"></div>

            <div class="space-y-8 md:space-y-10">
                @foreach($milestones as $i => $m)
                <div class="relative flex items-start gap-6">
                    {{-- Desktop: left side --}}
                    <div class="hidden md:block flex-1 @if($i % 2 === 0) text-right pr-8 @else opacity-0 @endif">
                        @if($i % 2 === 0)
                        <div class="v2-card inline-block p-5 max-w-xs text-left">
                            @if(!empty($m['year']))
                            <span class="text-xs font-bold uppercase tracking-wider text-accent">{{ $m['year'] }}</span>
                            @endif
                            <h4 class="font-heading font-bold text-slate-900 mt-1">{{ $m['title'] }}</h4>
                            @if(!empty($m['description']))
                            <p class="text-sm text-slate-500 mt-1 leading-relaxed">{{ $m['description'] }}</p>
                            @endif
                        </div>
                        @endif
                    </div>

                    {{-- Centre dot --}}
                    <div class="hidden md:flex items-center justify-center w-9 h-9 rounded-full text-white text-xs font-bold shrink-0 z-10 shadow-md v2-btn-primary">
                        {{ $i + 1 }}
                    </div>

                    {{-- Desktop: right side --}}
                    <div class="hidden md:block flex-1 @if($i % 2 === 1) pl-8 @else opacity-0 @endif">
                        @if($i % 2 === 1)
                        <div class="v2-card inline-block p-5 max-w-xs">
                            @if(!empty($m['year']))
                            <span class="text-xs font-bold uppercase tracking-wider text-accent">{{ $m['year'] }}</span>
                            @endif
                            <h4 class="font-heading font-bold text-slate-900 mt-1">{{ $m['title'] }}</h4>
                            @if(!empty($m['description']))
                            <p class="text-sm text-slate-500 mt-1 leading-relaxed">{{ $m['description'] }}</p>
                            @endif
                        </div>
                        @endif
                    </div>

                    {{-- Mobile: full-width card --}}
                    <div class="md:hidden flex gap-4 w-full">
                        <div class="w-9 h-9 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0 v2-btn-primary">{{ $i + 1 }}</div>
                        <div class="v2-card flex-1 p-4">
                            @if(!empty($m['year']))
                            <span class="text-xs font-bold uppercase tracking-wider text-accent">{{ $m['year'] }}</span>
                            @endif
                            <h4 class="font-heading font-bold text-slate-900 mt-1">{{ $m['title'] }}</h4>
                            @if(!empty($m['description']))
                            <p class="text-sm text-slate-500 mt-1 leading-relaxed">{{ $m['description'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif
