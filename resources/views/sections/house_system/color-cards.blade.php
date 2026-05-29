<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['houses']) && is_array($config['houses']))
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($config['houses'] as $house)
            <div class="rounded-xl overflow-hidden shadow-sm border text-center" style="border-top: 4px solid {{ $house['color'] ?? '#ccc' }};">
                <div class="p-6">
                    <div class="w-16 h-16 rounded-full mx-auto mb-3 flex items-center justify-center text-white text-2xl font-bold font-heading" style="background-color: {{ $house['color'] ?? '#ccc' }};">
                        {{ $house['initial'] ?? substr($house['name'], 0, 1) }}
                    </div>
                    <h3 class="font-bold font-heading text-gray-800">{{ $house['name'] }}</h3>
                    @if(!empty($house['motto']))
                    <p class="text-xs text-gray-500 italic mt-1">"{{ $house['motto'] }}"</p>
                    @endif
                    @if(!empty($house['captain']))
                    <p class="text-sm mt-3"><span class="font-medium">Captain:</span> {{ $house['captain'] }}</p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>