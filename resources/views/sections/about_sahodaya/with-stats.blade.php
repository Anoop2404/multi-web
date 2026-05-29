<section class="py-16 px-4" style="background-color: var(--color-primary);">
    <div class="max-w-7xl mx-auto">
        <div class="grid md:grid-cols-2 gap-10 items-center">
            <div class="text-white">
                @if(!empty($config['heading']))
                <h2 class="text-3xl md:text-4xl font-bold font-heading mb-4">{{ $config['heading'] }}</h2>
                @endif
                <div class="prose prose-invert max-w-none">{!! nl2br(e($config['content'] ?? '')) !!}</div>
            </div>
            <div class="grid grid-cols-2 gap-6">
                @if(!empty($config['stats']) && is_array($config['stats']))
                @foreach($config['stats'] as $stat)
                <div class="text-center text-white p-6 rounded-xl" style="background-color: rgba(255,255,255,0.1);">
                    <div class="text-4xl font-bold font-heading">{{ $stat['value'] ?? 0 }}</div>
                    <div class="text-sm text-white/80 mt-1">{{ $stat['label'] ?? '' }}</div>
                </div>
                @endforeach
                @endif
            </div>
        </div>
    </div>
</section>