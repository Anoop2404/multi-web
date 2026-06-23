<section id="about" class="py-16 px-4 scroll-mt-24">
    <div class="max-w-4xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-6" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        <div class="prose max-w-none text-gray-600">
            {!! nl2br(e($config['content'] ?? '')) !!}
        </div>
        @if(!empty($config['objectives']) && is_array($config['objectives']))
        <div class="mt-8 grid sm:grid-cols-2 gap-4">
            @foreach($config['objectives'] as $obj)
            <div class="flex gap-3 p-4 rounded-lg bg-gray-50">
                <span style="color: var(--color-primary);">✓</span>
                <p class="text-sm text-gray-600">{{ $obj['text'] ?? $obj }}</p>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>