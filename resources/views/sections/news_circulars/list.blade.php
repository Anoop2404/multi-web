<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-4xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['items']) && is_array($config['items']))
        <div class="space-y-4">
            @foreach($config['items'] as $item)
            <div class="flex items-center gap-4 p-4 rounded-lg bg-white shadow-sm border border-gray-100">
                @if(!empty($item['date']))
                <div class="text-center shrink-0 w-14">
                    <div class="text-lg font-bold font-heading" style="color: var(--color-primary)">{{ \Carbon\Carbon::parse($item['date'])->format('d') }}</div>
                    <div class="text-xs text-gray-500 uppercase">{{ \Carbon\Carbon::parse($item['date'])->format('M') }}</div>
                </div>
                @endif
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-sm text-gray-800 truncate">{{ $item['title'] }}</h3>
                </div>
                @if(!empty($item['url']))
                <a href="{{ $item['url'] }}" class="shrink-0 text-xs font-semibold hover:underline" style="color: var(--color-primary);">View</a>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>