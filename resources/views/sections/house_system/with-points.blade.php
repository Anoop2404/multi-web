<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-5xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['houses']) && is_array($config['houses']))
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($config['houses'] as $house)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="p-6 text-center" style="background-color: {{ $house['color'] ?? '#f3f4f6' }}40;">
                    <h3 class="font-bold font-heading text-lg text-gray-800">{{ $house['name'] }}</h3>
                    <div class="text-3xl font-bold font-heading mt-3" style="color: {{ $house['color'] ?? 'var(--color-primary)' }};">{{ $house['points'] ?? 0 }}</div>
                    <p class="text-xs text-gray-500 mt-1">Points</p>
                </div>
                <div class="p-4 text-xs text-gray-600 space-y-1">
                    @if(!empty($house['captain']))<p>Captain: {{ $house['captain'] }}</p>@endif
                    @if(!empty($house['vice_captain']))<p>Vice Captain: {{ $house['vice_captain'] }}</p>@endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>