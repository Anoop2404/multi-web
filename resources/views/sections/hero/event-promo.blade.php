<section class="relative py-24 px-4 text-center text-white overflow-hidden" style="background: linear-gradient(135deg, var(--color-primary), #e91e63);">
    <div class="relative z-10 max-w-4xl mx-auto">
        <p class="text-sm uppercase tracking-widest font-semibold opacity-80 mb-2">{{ $config['event_label'] ?? 'Upcoming Event' }}</p>
        <h1 class="text-4xl md:text-6xl font-bold font-heading mb-4">{{ $config['heading'] ?? $tenant->name }}</h1>
        @if(!empty($config['date']))
        <p class="text-lg opacity-90 mb-2">📅 {{ $config['date'] }}</p>
        @endif
        @if(!empty($config['venue']))
        <p class="text-lg opacity-90 mb-6">📍 {{ $config['venue'] }}</p>
        @endif
        <div class="flex flex-wrap justify-center gap-3">
            @if(!empty($config['cta_label']))
            <a href="{{ $config['cta_url'] ?? '#' }}" class="bg-white text-gray-800 font-semibold px-8 py-3 rounded-full hover:bg-opacity-90 transition">{{ $config['cta_label'] }}</a>
            @endif
            @if(!empty($config['secondary_cta_label']))
            <a href="{{ $config['secondary_cta_url'] ?? '#' }}" class="border border-white/60 text-white font-semibold px-8 py-3 rounded-full hover:bg-white/10 transition">{{ $config['secondary_cta_label'] }}</a>
            @endif
        </div>
    </div>
</section>