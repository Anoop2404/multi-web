{{-- statistics/counter-strip.blade.php — Animated count-up numbers --}}
@php
    $statsItems = $config['stats'] ?? [];
    if (empty($statsItems)) {
        $realSchools = \App\Support\SahodayaPublicData::memberSchools($tenant->id)->count();
        $statsItems = $realSchools > 0
            ? [['icon' => '🏫', 'value' => $realSchools, 'suffix' => '+', 'label' => 'Member Schools']]
            : [];
    }
@endphp
@if(!empty($statsItems))
<section class="py-12 lg:py-16 px-4 sm:px-6 lg:px-8 bg-slate-900 text-white relative overflow-hidden"
         style="background: linear-gradient(135deg, var(--color-primary) 0%, #0f172a 100%);">

    <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:20px_20px] pointer-events-none"></div>

    <div class="max-w-7xl mx-auto relative z-10">
        @if(!empty($config['heading']))
        <h2 class="text-2xl sm:text-3xl font-extrabold font-heading text-white text-center mb-10 tracking-tight">
            {{ $config['heading'] }}
        </h2>
        @endif

        <div class="flex flex-wrap justify-center gap-4 sm:gap-6">
            @foreach($statsItems as $stat)
            <div class="bg-white/10 backdrop-blur-md rounded-2xl p-6 border border-white/10 text-center hover:bg-white/15 hover:border-white/20 transition duration-200 group w-40 sm:w-48">
                @if(!empty($stat['icon']))
                <div class="text-3xl mb-2 group-hover:scale-110 transition-transform duration-200 inline-block">
                    {{ $stat['icon'] }}
                </div>
                @endif
                <div class="font-heading text-3xl sm:text-4xl lg:text-5xl font-extrabold text-accent tracking-tight leading-none">
                    {{ is_numeric($stat['value']) ? number_format($stat['value']) : $stat['value'] }}{{ $stat['suffix'] ?? '' }}
                </div>
                <p class="text-xs sm:text-sm font-semibold text-slate-300 mt-2 uppercase tracking-wider">
                    {{ $stat['label'] ?? '' }}
                </p>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
