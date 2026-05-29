<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-4xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['events']) && is_array($config['events']))
        <div class="space-y-6 relative before:absolute before:left-6 before:top-0 before:bottom-0 before:w-0.5 before:bg-gray-200">
            @foreach($config['events'] as $event)
            <div class="relative pl-14">
                <div class="absolute left-4 w-4 h-4 rounded-full border-2 border-white shadow" style="background-color: var(--color-primary);"></div>
                <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
                    <p class="text-xs font-semibold" style="color: var(--color-primary);">{{ $event['date'] ?? '' }}</p>
                    <h3 class="font-bold font-heading text-gray-800 mt-1">{{ $event['title'] }}</h3>
                    @if(!empty($event['venue']))<p class="text-sm text-gray-500 mt-1">📍 {{ $event['venue'] }}</p>@endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>