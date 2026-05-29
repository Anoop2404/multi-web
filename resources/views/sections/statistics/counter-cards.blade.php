<section class="py-16 px-4" style="background-color: var(--color-primary);" x-data="{ visible: false }" x-init="() => { const observer = new IntersectionObserver(([entry]) => { if (entry.isIntersecting) { visible = true; observer.disconnect(); } }); observer.observe($el); }">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl font-bold font-heading text-center text-white mb-10">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['stats']) && is_array($config['stats']))
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center text-white">
            @foreach($config['stats'] as $stat)
            <div class="p-6" x-data="{ count: 0 }" x-show="visible" x-init="() => { if ($root.visible) { const target = {{ $stat['value'] ?? 0 }}; const duration = 2000; const steps = 60; const increment = target / steps; let current = 0; const timer = setInterval(() => { current += increment; if (current >= target) { count = target; clearInterval(timer); } else { count = Math.floor(current); } }, duration / steps); } }">
                <div class="text-4xl md:text-5xl font-bold font-heading mb-2" x-text="count.toLocaleString()">0</div>
                <div class="text-sm text-white/80 uppercase tracking-wider">{{ $stat['label'] ?? '' }}</div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>