<section class="bg-gray-900 text-white overflow-hidden py-2" x-data="{ paused: false }">
    <div class="flex items-center gap-4 max-w-7xl mx-auto px-4">
        @if(!empty($config['label']))
        <span class="shrink-0 text-xs font-bold uppercase tracking-wider px-3 py-1 rounded"
              style="background-color: var(--color-primary);">{{ $config['label'] }}</span>
        @endif
        <div class="overflow-hidden relative flex-1" @mouseenter="paused = true" @mouseleave="paused = false">
            <div class="flex whitespace-nowrap animate-marquee gap-12" :class="{ 'animate-pause': paused }">
                @if(!empty($config['items']) && is_array($config['items']))
                @foreach($config['items'] as $item)
                <span class="text-sm text-gray-300">{{ $item['text'] ?? $item }}</span>
                @endforeach
                @endif
            </div>
        </div>
    </div>
    <style>
        @keyframes marquee {
            0% { transform: translateX(0); }
            100% { transform: translateX(-50%); }
        }
        .animate-marquee { animation: marquee 30s linear infinite; }
        .animate-pause { animation-play-state: paused; }
    </style>
</section>