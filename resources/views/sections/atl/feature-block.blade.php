<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-4" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        <div class="grid md:grid-cols-2 gap-10 items-center">
            <div>
                @if(!empty($config['photos']) && is_array($config['photos']))
                <div class="grid grid-cols-2 gap-3">
                    @foreach($config['photos'] as $photo)
                    <img loading="lazy" src="{{ $photo }}" alt="ATL Lab" class="rounded-lg object-cover h-40 w-full">
                    @endforeach
                </div>
                @elseif(!empty($config['image']))
                <img loading="lazy" src="{{ $config['image'] }}" alt="ATL Lab" class="rounded-lg w-full h-64 object-cover">
                @endif
            </div>
            <div>
                <div class="prose max-w-none text-gray-600">
                    {!! nl2br(e($config['description'] ?? '')) !!}
                </div>
                @if(!empty($config['features']) && is_array($config['features']))
                <ul class="mt-4 space-y-2">
                    @foreach($config['features'] as $feature)
                    <li class="flex gap-2 text-sm text-gray-600">
                        <span style="color: var(--color-primary)">✓</span>
                        {{ $feature['text'] ?? $feature }}
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>
        </div>
    </div>
</section>