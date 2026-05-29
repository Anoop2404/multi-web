<section class="py-12" style="background-color: var(--color-primary);">
    <div class="max-w-7xl mx-auto px-4">
        @if(!empty($config['stats']) && is_array($config['stats']))
        <div class="grid grid-cols-2 md:grid-cols-{{ min(count($config['stats']), 6) }} gap-px">
            @foreach($config['stats'] as $stat)
            <div class="p-6 text-center text-white">
                <div class="text-3xl md:text-4xl font-bold font-heading">{{ $stat['value'] ?? 0 }}</div>
                <div class="text-sm text-white/80 mt-1">{{ $stat['label'] ?? '' }}</div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>