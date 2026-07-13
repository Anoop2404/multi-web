<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['map_embed']))
        <div class="rounded-xl overflow-hidden shadow-lg mb-8">{!! \App\Support\HtmlSanitizer::embed($config['map_embed'] ?? '') !!}</div>
        @endif
        @if(!empty($config['schools']) && is_array($config['schools']))
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($config['schools'] as $school)
            <div class="flex items-center gap-3 p-3 rounded-lg bg-white shadow-sm border border-gray-100">
                @if(!empty($school['logo']))
                <img loading="lazy" src="{{ $school['logo'] }}" alt="{{ $school['name'] ?? 'School logo' }}" class="w-10 h-10 rounded object-contain">
                @endif
                <div>
                    <p class="font-medium text-sm text-gray-800">{{ $school['name'] }}</p>
                    <p class="text-xs text-gray-500">{{ $school['location'] ?? '' }}</p>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>