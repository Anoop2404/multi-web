<section class="py-16 px-4">
    <div class="max-w-4xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['events']) && is_array($config['events']))
        <div class="space-y-4">
            @foreach($config['events'] as $event)
            <div class="flex items-center gap-4 p-4 rounded-lg bg-white shadow-sm border border-gray-100">
                <div class="text-center shrink-0 w-16">
                    <div class="text-2xl font-bold font-heading" style="color: var(--color-primary)">{{ \Carbon\Carbon::parse($event['date'] ?? '')->format('d') }}</div>
                    <div class="text-xs text-gray-500 uppercase">{{ \Carbon\Carbon::parse($event['date'] ?? '')->format('M') }}</div>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800">{{ $event['title'] }}</h3>
                    @if(!empty($event['venue']))<p class="text-xs text-gray-500 mt-1">📍 {{ $event['venue'] }}</p>@endif
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>