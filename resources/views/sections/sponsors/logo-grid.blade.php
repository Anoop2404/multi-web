@php
    $sponsors = collect($config['sponsors'] ?? [])->filter(fn ($s) => !empty($s['name']) && !empty($s['logo']))->values();
    $tiers = $sponsors->pluck('tier')->filter()->unique()->values();
    $tierOrder = ['Title', 'Gold', 'Silver', 'Bronze'];
    $groups = $tiers->isNotEmpty()
        ? $sponsors->groupBy(fn ($s) => $s['tier'] ?: 'Other')->sortBy(fn ($_, $tier) => array_search($tier, $tierOrder, true) === false ? 999 : array_search($tier, $tierOrder, true))
        : collect(['' => $sponsors]);
@endphp
@if($sponsors->isNotEmpty())
<section class="py-12 lg:py-16 px-4 sm:px-6 lg:px-8 bg-slate-50 border-t border-slate-200">
    <div class="max-w-6xl mx-auto">
        <div class="text-center max-w-2xl mx-auto mb-10 space-y-2">
            @if(!empty($config['eyebrow']))
            <span class="inline-block text-xs font-bold uppercase tracking-widest v2-badge-primary px-3.5 py-1.5 rounded-full">
                {{ $config['eyebrow'] }}
            </span>
            @endif
            <h2 class="text-3xl sm:text-4xl font-extrabold font-heading text-slate-900 tracking-tight">
                {{ $config['heading'] ?? 'Sponsors & Partners' }}
            </h2>
        </div>

        <div class="space-y-10">
            @foreach($groups as $tier => $group)
            <div>
                @if($tier)
                <h3 class="text-center text-xs font-bold uppercase tracking-widest text-accent mb-5">{{ $tier }} Sponsors</h3>
                @endif
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-5">
                    @foreach($group as $sponsor)
                    @if(!empty($sponsor['url']))
                    <a href="{{ $sponsor['url'] }}" target="_blank" rel="noopener sponsored" class="v2-card p-6 flex items-center justify-center h-28 group">
                        <img loading="lazy" src="{{ $sponsor['logo'] }}" alt="{{ $sponsor['name'] }}" title="{{ $sponsor['name'] }}" class="v2-media max-h-14 max-w-full object-contain grayscale opacity-70 group-hover:grayscale-0 group-hover:opacity-100 transition duration-200">
                    </a>
                    @else
                    <div class="v2-card p-6 flex items-center justify-center h-28 group">
                        <img loading="lazy" src="{{ $sponsor['logo'] }}" alt="{{ $sponsor['name'] }}" title="{{ $sponsor['name'] }}" class="v2-media max-h-14 max-w-full object-contain grayscale opacity-70 group-hover:grayscale-0 group-hover:opacity-100 transition duration-200">
                    </div>
                    @endif
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
