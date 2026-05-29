<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['events']) && is_array($config['events']))
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($config['events'] as $event)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition">
                @if(!empty($event['image']))
                <img loading="lazy" src="{{ $event['image'] }}" alt="{{ $event['title'] }}" class="w-full h-40 object-cover">
                @endif
                <div class="p-5">
                    <p class="text-xs font-semibold" style="color: var(--color-primary)">{{ $event['date'] ?? '' }}</p>
                    <h3 class="font-bold font-heading text-gray-800 mt-1">{{ $event['title'] }}</h3>
                    @if(!empty($event['venue']))<p class="text-xs text-gray-500 mt-1">📍 {{ $event['venue'] }}</p>@endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>