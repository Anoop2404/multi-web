<section class="relative py-24 px-4 text-center text-white overflow-hidden" style="background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));">
    <div class="relative z-10 max-w-4xl mx-auto">
        <h1 class="text-4xl md:text-6xl font-bold font-heading mb-4">{{ $config['heading'] ?? $tenant->name }}</h1>
        @if(!empty($config['tagline']))
        <p class="text-xl md:text-2xl opacity-90 mb-6">{{ $config['tagline'] }}</p>
        @endif
        @if(!empty($config['affiliated_board']))
        <p class="text-sm opacity-80 mb-2">{{ $config['affiliated_board'] }}</p>
        @endif
        @if(!empty($config['cluster_info']))
        <p class="text-sm opacity-80">{{ $config['cluster_info'] }}</p>
        @endif
        @if(!empty($config['cta_label']) && !empty($config['cta_url']))
        <a href="{{ $config['cta_url'] }}" class="inline-block mt-8 bg-white text-primary font-semibold px-8 py-3 rounded-full hover:bg-opacity-90 transition"
           style="color: var(--color-primary);">{{ $config['cta_label'] }}</a>
        @endif
    </div>
</section>