<section class="relative" style="background-color: var(--color-primary);">
    <div class="max-w-7xl mx-auto px-4 py-20 text-center">
        @if(!empty($config['background_image']))
        <div class="absolute inset-0 bg-cover bg-center opacity-20"
             style="background-image: url('{{ $config['background_image'] }}')"></div>
        @endif
        <div class="relative z-10">
            <h1 class="text-4xl md:text-5xl font-bold font-heading text-white mb-4">
                {{ $config['heading'] ?? $tenant->name }}
            </h1>
            @if(!empty($config['tagline']))
            <p class="text-lg text-white/80 mb-10 max-w-2xl mx-auto">{{ $config['tagline'] }}</p>
            @endif
        </div>
    </div>
    {{-- Quick Link Buttons overlapping hero bottom --}}
    @if(!empty($config['quicklinks']) && is_array($config['quicklinks']))
    <div class="relative z-20 -mt-10 pb-10">
        <div class="max-w-4xl mx-auto px-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
            @foreach($config['quicklinks'] as $link)
            <a href="{{ $link['url'] }}"
               class="bg-white rounded-xl shadow-lg p-5 text-center hover:shadow-xl transition-shadow group">
                @if(!empty($link['icon']))
                <div class="text-3xl mb-2">{{ $link['icon'] }}</div>
                @endif
                <div class="font-semibold text-gray-800 group-hover:text-primary transition-colors">
                    {{ $link['label'] }}
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif
</section>