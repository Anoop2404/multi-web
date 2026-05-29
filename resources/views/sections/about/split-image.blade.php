<section class="py-16 px-4 bg-white">
    <div class="max-w-7xl mx-auto grid md:grid-cols-2 gap-12 items-center">
        {{-- Image --}}
        @if(!empty($config['image']))
        <div class="{{ ($config['image_side'] ?? 'left') === 'right' ? 'md:order-2' : '' }}">
            <div class="rounded-2xl overflow-hidden shadow-lg aspect-[4/3]">
                <img loading="lazy" src="{{ $config['image'] }}" alt="{{ $config['image_alt'] ?? 'About us' }}"
                     class="w-full h-full object-cover">
            </div>
        </div>
        @endif

        {{-- Text --}}
        <div>
            @if(!empty($config['eyebrow']))
            <p class="text-sm font-semibold uppercase tracking-widest mb-3" style="color: var(--color-primary)">{{ $config['eyebrow'] }}</p>
            @endif
            <h2 class="text-3xl font-bold font-heading text-gray-900 mb-4">
                {{ $config['heading'] ?? 'About Us' }}
            </h2>
            @if(!empty($config['body']))
            <div class="text-gray-600 leading-relaxed mb-6 space-y-4">
                {!! nl2br(e($config['body'])) !!}
            </div>
            @endif

            {{-- Stats row --}}
            @if(!empty($config['stats']))
            <div class="grid grid-cols-3 gap-4 mt-6">
                @foreach($config['stats'] as $stat)
                <div class="text-center p-4 bg-gray-50 rounded-xl">
                    <div class="text-2xl font-bold font-heading" style="color: var(--color-primary)">{{ $stat['value'] }}</div>
                    <div class="text-xs text-gray-500 mt-1">{{ $stat['label'] }}</div>
                </div>
                @endforeach
            </div>
            @endif

            @if(!empty($config['cta_label']) && !empty($config['cta_url']))
            <a href="{{ $config['cta_url'] }}"
               class="inline-block mt-8 font-semibold px-6 py-3 rounded-full text-white hover:opacity-90 transition"
               style="background-color: var(--color-primary)">
                {{ $config['cta_label'] }}
            </a>
            @endif
        </div>
    </div>
</section>
