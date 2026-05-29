<section class="py-16 px-4">
    <div class="max-w-4xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['items']) && is_array($config['items']))
        <div class="space-y-8 relative before:absolute before:left-4 md:before:left-1/2 before:top-0 before:bottom-0 before:w-0.5 before:bg-gray-200">
            @foreach($config['items'] as $item)
            <div class="relative pl-12 md:pl-0 md:even:text-right md:even:pl-[50%] md:odd:pr-[50%] md:odd:pr-12">
                <div class="absolute left-3 md:left-1/2 md:-translate-x-1/2 w-4 h-4 rounded-full border-2 border-white shadow"
                     style="background-color: var(--color-primary);"></div>
                <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100">
                    @if(!empty($item['date']))<p class="text-xs font-semibold" style="color: var(--color-primary)">{{ $item['date'] }}</p>@endif
                    <h3 class="font-bold font-heading text-gray-800 mt-1">{{ $item['title'] }}</h3>
                    <p class="text-sm text-gray-600 mt-1">{{ $item['description'] ?? '' }}</p>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>