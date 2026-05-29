@php
    $tickerCfg = $widgets['news_ticker'] ?? [];
    $show      = $tickerCfg['show'] ?? false;
@endphp
@if($show)
@php
    // Pull latest 6 published headlines; cached per tenant
    $headlines = \Illuminate\Support\Facades\Cache::remember(
        "site:{$tenant->id}:ticker",
        now()->addMinutes(15),
        fn() => \App\Models\NewsArticle::where('tenant_id', $tenant->id)
                    ->whereNotNull('published_at')
                    ->where('published_at', '<=', now())
                    ->orderByDesc('published_at')
                    ->limit(6)
                    ->pluck('title')
                    ->toArray()
    );
@endphp
@if(!empty($headlines))
<div class="bg-[var(--color-primary)] text-white text-sm py-1.5 flex items-stretch overflow-hidden select-none">
    <div class="shrink-0 bg-black/20 px-4 flex items-center font-semibold tracking-wide uppercase text-xs whitespace-nowrap">
        Latest News
    </div>
    <div class="flex-1 overflow-hidden relative flex items-center">
        <div class="animate-marquee whitespace-nowrap flex gap-10 px-6">
            @foreach($headlines as $headline)
                <span>&#9679; {{ $headline }}</span>
            @endforeach
            {{-- duplicate for seamless loop --}}
            @foreach($headlines as $headline)
                <span>&#9679; {{ $headline }}</span>
            @endforeach
        </div>
    </div>
</div>
@endif
@endif
