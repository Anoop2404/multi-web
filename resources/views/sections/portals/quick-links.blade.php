<section class="py-12 px-4 bg-gray-50">
    <div class="max-w-5xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-2xl md:text-3xl font-bold font-heading text-center mb-8" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['links']) && is_array($config['links']))
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($config['links'] as $link)
            <a href="{{ $link['url'] }}" target="_blank" rel="noopener"
               class="flex items-center gap-4 p-4 bg-white rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition group">
                @if(!empty($link['icon']))
                <div class="text-2xl">{{ $link['icon'] }}</div>
                @endif
                <div>
                    <h3 class="font-semibold text-sm text-gray-800 group-hover:text-primary transition">{{ $link['label'] }}</h3>
                    @if(!empty($link['description']))
                    <p class="text-xs text-gray-500">{{ $link['description'] }}</p>
                    @endif
                </div>
            </a>
            @endforeach
        </div>
        @endif
    </div>
</section>