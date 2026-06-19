{{-- statistics/counter-strip.blade.php — Animated count-up numbers --}}
@php $stats = $config['stats'] ?? []; @endphp
<section class="py-14 px-4" style="background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-2xl font-bold font-heading text-white text-center mb-8 opacity-90">{{ $config['heading'] }}</h2>
        @endif

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 lg:gap-6">
            @foreach($stats as $stat)
            <div class="text-center group">
                @if(!empty($stat['icon']))
                <div class="text-4xl mb-2 opacity-80 group-hover:opacity-100 group-hover:scale-110 transition-transform">
                    {{ $stat['icon'] }}
                </div>
                @endif
                <div class="font-heading text-4xl sm:text-5xl font-extrabold text-white leading-none">
                    <span class="counter" data-target="{{ $stat['value'] ?? '0' }}">{{ $stat['value'] ?? '0' }}</span>{{ $stat['suffix'] ?? '' }}
                </div>
                <p class="text-sm font-semibold text-white/75 mt-1 uppercase tracking-wide">{{ $stat['label'] ?? '' }}</p>
            </div>
            @endforeach

            @if(empty($stats))
            @php
                use App\Support\SahodayaPublicData;
                $schoolCount = SahodayaPublicData::memberSchools($tenant->id)->count();
            @endphp
            @foreach([
                ['icon'=>'🏫','value'=>$schoolCount,'suffix'=>'+','label'=>'Member Schools'],
                ['icon'=>'📅','value'=>'25','suffix'=>'+','label'=>'Years of Excellence'],
                ['icon'=>'👩‍🎓','value'=>'50000','suffix'=>'+','label'=>'Students Benefited'],
                ['icon'=>'🏆','value'=>'100','suffix'=>'+','label'=>'Events Conducted'],
            ] as $stat)
            <div class="text-center">
                <div class="text-4xl mb-2">{{ $stat['icon'] }}</div>
                <div class="font-heading text-4xl sm:text-5xl font-extrabold text-white">
                    {{ number_format($stat['value']) }}{{ $stat['suffix'] }}
                </div>
                <p class="text-sm font-semibold text-white/75 mt-1 uppercase tracking-wide">{{ $stat['label'] }}</p>
            </div>
            @endforeach
            @endif
        </div>
    </div>
</section>
