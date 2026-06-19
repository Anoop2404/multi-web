{{-- about_sahodaya/with-timeline.blade.php — History/milestones timeline section --}}
<section class="py-16 lg:py-20 px-4 bg-gray-50">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-12">
            @if(!empty($config['eyebrow']))
            <p class="text-xs font-bold uppercase tracking-[0.2em] mb-2" style="color: var(--color-secondary)">
                {{ $config['eyebrow'] }}
            </p>
            @endif
            <h2 class="font-heading text-3xl sm:text-4xl font-bold text-gray-900">
                {{ $config['heading'] ?? 'Our Journey' }}
            </h2>
            @if(!empty($config['subtext']))
            <p class="mt-3 text-gray-500 max-w-xl mx-auto">{{ $config['subtext'] }}</p>
            @endif
        </div>

        {{-- Timeline --}}
        @php $milestones = $config['milestones'] ?? []; @endphp
        @if(!empty($milestones))
        <div class="relative">
            {{-- Centre line --}}
            <div class="absolute left-1/2 top-0 bottom-0 w-0.5 -translate-x-1/2 bg-gradient-to-b hidden md:block"
                 style="background: linear-gradient(to bottom, var(--color-primary), var(--color-secondary), transparent)"></div>

            <div class="space-y-10">
                @foreach($milestones as $i => $m)
                <div class="relative flex items-start gap-6"
                     @if($i % 2 === 0) {{-- even: right-aligned on desktop --}} @endif>
                    {{-- Desktop: left side --}}
                    <div class="hidden md:block flex-1 @if($i % 2 === 0) text-right pr-8 @else opacity-0 @endif">
                        @if($i % 2 === 0)
                        <div class="inline-block bg-white rounded-2xl shadow-sm border border-gray-100 p-5 max-w-xs text-left">
                            @if(!empty($m['year']))
                            <span class="text-xs font-bold uppercase tracking-wider" style="color: var(--color-primary)">{{ $m['year'] }}</span>
                            @endif
                            <h4 class="font-bold text-gray-900 mt-1">{{ $m['title'] ?? '' }}</h4>
                            @if(!empty($m['description']))
                            <p class="text-sm text-gray-500 mt-1">{{ $m['description'] }}</p>
                            @endif
                        </div>
                        @endif
                    </div>

                    {{-- Centre dot --}}
                    <div class="hidden md:flex items-center justify-center w-8 h-8 rounded-full text-white text-xs font-bold shrink-0 z-10 shadow-md"
                         style="background: var(--color-primary)">
                        {{ $i + 1 }}
                    </div>

                    {{-- Desktop: right side --}}
                    <div class="hidden md:block flex-1 @if($i % 2 === 1) pl-8 @else opacity-0 @endif">
                        @if($i % 2 === 1)
                        <div class="inline-block bg-white rounded-2xl shadow-sm border border-gray-100 p-5 max-w-xs">
                            @if(!empty($m['year']))
                            <span class="text-xs font-bold uppercase tracking-wider" style="color: var(--color-primary)">{{ $m['year'] }}</span>
                            @endif
                            <h4 class="font-bold text-gray-900 mt-1">{{ $m['title'] ?? '' }}</h4>
                            @if(!empty($m['description']))
                            <p class="text-sm text-gray-500 mt-1">{{ $m['description'] }}</p>
                            @endif
                        </div>
                        @endif
                    </div>

                    {{-- Mobile: full-width card --}}
                    <div class="md:hidden flex gap-4 w-full">
                        <div class="w-8 h-8 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0"
                             style="background: var(--color-primary)">{{ $i + 1 }}</div>
                        <div class="flex-1 bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
                            @if(!empty($m['year']))
                            <span class="text-xs font-bold uppercase tracking-wider" style="color: var(--color-primary)">{{ $m['year'] }}</span>
                            @endif
                            <h4 class="font-bold text-gray-900 mt-1">{{ $m['title'] ?? '' }}</h4>
                            @if(!empty($m['description']))
                            <p class="text-sm text-gray-500 mt-1">{{ $m['description'] }}</p>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @else
        <div class="text-center text-gray-400 py-8">Add milestones in the section editor to display your timeline.</div>
        @endif
    </div>
</section>
