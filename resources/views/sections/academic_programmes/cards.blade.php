<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-4" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['streams']) && is_array($config['streams']))
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($config['streams'] as $stream)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 hover:shadow-md transition">
                @if(!empty($stream['icon']))
                <div class="text-4xl mb-3">{{ $stream['icon'] }}</div>
                @endif
                <h3 class="text-xl font-bold font-heading mb-2" style="color: var(--color-primary)">{{ $stream['name'] }}</h3>
                <p class="text-gray-600 text-sm">{{ $stream['description'] ?? '' }}</p>
                @if(!empty($stream['subjects']) && is_array($stream['subjects']))
                <div class="mt-4 pt-4 border-t border-gray-100">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Subjects</p>
                    <div class="flex flex-wrap gap-1">
                        @foreach($stream['subjects'] as $subject)
                        <span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600">{{ $subject }}</span>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>