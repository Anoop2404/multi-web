<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto grid md:grid-cols-2 gap-12 items-center">
        <div>
            @if(!empty($config['image']))
            <img loading="lazy" src="{{ $config['image'] }}" alt="{{ $config['heading'] ?? 'About' }}"
                 class="rounded-lg w-full h-auto object-cover shadow-lg">
            @else
            <div class="rounded-lg bg-gray-200 h-80 flex items-center justify-center text-gray-400">
                <svg class="w-16 h-16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            @endif
        </div>
        <div>
            @if(!empty($config['heading']))
            <h2 class="text-3xl md:text-4xl font-bold font-heading mb-4" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
            @endif
            <div class="prose max-w-none text-gray-600">
                {!! nl2br(e($config['content'] ?? '')) !!}
            </div>
            @if(!empty($config['cta_label']) && !empty($config['cta_url']))
            <a href="{{ $config['cta_url'] }}" class="inline-block mt-6 px-6 py-3 rounded-lg text-white font-semibold transition"
               style="background-color: var(--color-primary)">
                {{ $config['cta_label'] }}
            </a>
            @endif
        </div>
    </div>
</section>