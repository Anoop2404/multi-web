<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['items']) && is_array($config['items']))
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($config['items'] as $item)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition">
                <div class="p-5">
                    <span class="text-xs font-semibold px-2 py-1 rounded" style="background-color: color-mix(in srgb, var(--color-primary) 10%, transparent); color: var(--color-primary);">
                        {{ $item['type'] ?? 'Update' }}
                    </span>
                    @if(!empty($item['date']))<p class="text-xs text-gray-400 mt-2">{{ $item['date'] }}</p>@endif
                    <h3 class="font-semibold text-gray-800 mt-1">{{ $item['title'] }}</h3>
                    @if(!empty($item['url']))
                    <a href="{{ $item['url'] }}" class="inline-block mt-3 text-xs font-semibold hover:underline" style="color: var(--color-primary);">Read More →</a>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>