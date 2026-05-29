<section class="py-20 px-4 text-center" style="background-color: var(--color-primary);">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-4xl md:text-5xl font-bold font-heading text-white mb-3">
            {{ $config['heading'] ?? $tenant->name }}
        </h1>
        @if(!empty($config['tagline']))
        <p class="text-lg text-white/80">{{ $config['tagline'] }}</p>
        @endif
    </div>
</section>