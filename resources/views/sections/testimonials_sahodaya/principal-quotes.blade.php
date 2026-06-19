<section class="py-16 px-4">
    <div class="max-w-5xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['quotes']) && is_array($config['quotes']))
        <div class="grid md:grid-cols-2 gap-6">
            @foreach($config['quotes'] as $quote)
            <div class="bg-gray-50 rounded-xl p-6 border border-gray-100 relative">
                <div class="text-4xl leading-none opacity-20" style="color: var(--color-primary);">"</div>
                <blockquote class="text-gray-600 italic -mt-3">{{ $quote['text'] ?? $quote['quote'] ?? '' }}</blockquote>
                <div class="mt-4 flex items-center gap-3">
                    @if(!empty($quote['photo']))
                    <img loading="lazy" src="{{ $quote['photo'] }}" alt="{{ $quote['name'] ?? 'Principal' }}" class="w-10 h-10 rounded-full object-cover">
                    @endif
                    <div>
                        <p class="font-semibold text-sm text-gray-800">{{ $quote['name'] ?? '' }}</p>
                        <p class="text-xs text-gray-500">{{ $quote['school'] ?? '' }}</p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>