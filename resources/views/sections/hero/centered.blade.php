<section class="relative bg-primary text-white py-24 px-4 text-center" style="background-color: var(--color-primary)">
    @if(!empty($config['background_image']))
        <div class="absolute inset-0 bg-cover bg-center opacity-30"
             style="background-image: url('{{ $config['background_image'] }}')"></div>
    @endif
    <div class="relative z-10 max-w-4xl mx-auto">
        <h1 class="text-4xl md:text-6xl font-bold font-heading mb-4">
            {{ $config['heading'] ?? $tenant->name }}
        </h1>
        @if(!empty($config['tagline']))
            <p class="text-xl md:text-2xl opacity-90 mb-8">{{ $config['tagline'] }}</p>
        @endif
        @if(!empty($config['cta_label']) && !empty($config['cta_url']))
            <a href="{{ $config['cta_url'] }}"
               class="inline-block bg-white text-primary font-semibold px-8 py-3 rounded-full hover:bg-opacity-90 transition"
               style="color: var(--color-primary)">
                {{ $config['cta_label'] }}
            </a>
        @endif
    </div>
</section>
