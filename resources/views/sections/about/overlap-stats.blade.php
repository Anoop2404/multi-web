@php
    use App\Support\TenantStorage;
    $image = TenantStorage::siteMediaUrl($tenant, $config['image'] ?? null);
    $imageSecondary = TenantStorage::siteMediaUrl($tenant, $config['image_secondary'] ?? null);
    $miniStats = collect($config['mini_stats'] ?? [])->filter(fn ($s) => !empty($s['value']));
@endphp
<section class="py-16 px-4 bg-white">
    <div class="max-w-7xl mx-auto grid lg:grid-cols-12 gap-12 items-center">
        {{-- Layered image composition --}}
        <div class="lg:col-span-5 relative">
            <div class="relative rounded-[20px] overflow-hidden shadow-lg h-[420px]">
                @if($image)
                <img loading="lazy" src="{{ $image }}" alt="{{ $config['heading'] ?? 'About us' }}"
                     class="w-full h-full object-cover">
                @else
                <div class="w-full h-full flex items-center justify-center"
                     style="background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary, var(--color-primary)) 100%)">
                    <span class="text-6xl font-extrabold font-heading text-white/90">{{ mb_substr($tenant->name ?? 'S', 0, 1) }}</span>
                </div>
                @endif
            </div>

            @if($imageSecondary)
            <div class="absolute -bottom-8 -right-6 w-[180px] h-[130px] rounded-2xl overflow-hidden shadow-lg border-4 border-white hidden sm:block">
                <img loading="lazy" src="{{ $imageSecondary }}" alt="" class="w-full h-full object-cover">
            </div>
            @endif

            @if(!empty($config['stat_value']))
            <div class="absolute top-6 -right-6 rounded-2xl px-5 py-4 text-white shadow-lg hidden sm:block"
                 style="background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary, var(--color-primary)) 100%)">
                <div class="text-3xl font-extrabold font-heading leading-none">{{ $config['stat_value'] }}</div>
                <div class="text-[11px] leading-tight mt-1 opacity-90">
                    {{ $config['stat_label_line1'] ?? '' }}@if(!empty($config['stat_label_line2']))<br>{{ $config['stat_label_line2'] }}@endif
                </div>
            </div>
            @endif
        </div>

        {{-- Text --}}
        <div class="lg:col-span-7">
            <p class="inline-block text-xs font-semibold uppercase tracking-widest px-3 py-1.5 rounded mb-4"
               style="background-color: color-mix(in srgb, var(--color-primary) 12%, transparent); color: var(--color-primary)">
                {{ $config['eyebrow'] ?? 'About Us' }}
            </p>
            <h2 class="text-3xl md:text-4xl font-bold font-heading text-gray-900 mb-4">
                {{ $config['heading'] ?? 'About '.$tenant->name }}
            </h2>
            @if(!empty($config['body']))
            <div class="text-gray-600 leading-relaxed space-y-4">
                {!! nl2br(e($config['body'])) !!}
            </div>
            @endif

            @if($miniStats->isNotEmpty())
            <div class="grid grid-cols-3 gap-4 mt-8">
                @foreach($miniStats->take(3) as $stat)
                <div class="text-center p-4 rounded-xl border" style="background-color: color-mix(in srgb, var(--color-primary) 4%, white); border-color: color-mix(in srgb, var(--color-primary) 12%, #e5e7eb)">
                    <div class="text-2xl font-extrabold font-heading" style="color: var(--color-primary)">{{ $stat['value'] }}</div>
                    <div class="text-xs text-gray-500 mt-1 leading-snug">{{ $stat['label'] ?? '' }}</div>
                </div>
                @endforeach
            </div>
            @endif

            @if(!empty($config['cta_label']) && !empty($config['cta_url']))
            <a href="{{ $config['cta_url'] }}"
               class="inline-block mt-8 font-semibold px-6 py-3 rounded-lg text-white hover:opacity-90 transition"
               style="background-color: var(--color-primary)">
                {{ $config['cta_label'] }}
            </a>
            @endif
        </div>
    </div>
</section>
