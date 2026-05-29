<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-4" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        <div class="grid lg:grid-cols-2 gap-10">
            <div class="space-y-6">
                @if(!empty($config['streams']) && is_array($config['streams']))
                @foreach($config['streams'] as $stream)
                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                    <h3 class="text-lg font-bold font-heading" style="color: var(--color-primary)">{{ $stream['name'] }}</h3>
                    <p class="text-gray-600 text-sm mt-1">{{ $stream['description'] ?? '' }}</p>
                </div>
                @endforeach
                @endif
            </div>
            <div>
                @if(!empty($config['results']) && is_array($config['results']))
                <div class="bg-white rounded-xl p-6 shadow-sm">
                    <h3 class="text-xl font-bold font-heading mb-4" style="color: var(--color-primary)">{{ $config['results_title'] ?? 'Board Results' }}</h3>
                    @foreach($config['results'] as $result)
                    <div class="flex items-center justify-between py-3 border-b border-gray-100 last:border-0">
                        <span class="text-sm font-medium text-gray-700">{{ $result['year'] ?? '' }}</span>
                        <span class="text-sm font-bold" style="color: var(--color-primary)">{{ $result['pass_percentage'] ?? $result['value'] ?? '' }}%</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </div>
</section>