<section class="py-14 px-4" style="background-color: var(--color-primary)">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-center text-white text-3xl font-bold font-heading mb-10">{{ $config['heading'] }}</h2>
        @endif

        @if(!empty($config['stats']) && is_array($config['stats']))
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            @foreach($config['stats'] as $stat)
            <div class="text-center text-white">
                <div class="text-5xl font-bold font-heading mb-1">{{ $stat['value'] }}</div>
                <div class="text-white/70 text-sm font-medium uppercase tracking-wide">{{ $stat['label'] }}</div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>
