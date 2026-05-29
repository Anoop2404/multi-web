<section class="py-16 px-4">
    <div class="max-w-5xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-4" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['subheading']))
        <p class="text-center text-gray-500 mb-10 max-w-2xl mx-auto">{{ $config['subheading'] }}</p>
        @endif
        <div class="grid md:grid-cols-2 gap-8">
            <div>
                <h3 class="font-bold font-heading text-lg mb-4" style="color: var(--color-secondary)">{{ $config['streams_title'] ?? 'Academic Streams' }}</h3>
                @if(!empty($config['streams']) && is_array($config['streams']))
                <div class="space-y-4">
                    @foreach($config['streams'] as $stream)
                    <div class="flex gap-3 p-4 rounded-lg bg-gray-50">
                        @if(!empty($stream['icon']))<span class="text-2xl shrink-0">{{ $stream['icon'] }}</span>@endif
                        <div>
                            <h4 class="font-semibold text-gray-800 text-sm">{{ $stream['name'] }}</h4>
                            <p class="text-xs text-gray-600 mt-1">{{ $stream['description'] ?? '' }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            <div>
                @if(!empty($config['counsellor_name']))
                <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100 mb-6">
                    <h3 class="font-bold font-heading text-lg mb-3" style="color: var(--color-primary)">Career Counsellor</h3>
                    <p class="font-semibold text-gray-800">{{ $config['counsellor_name'] }}</p>
                    @if(!empty($config['counsellor_contact']))<p class="text-sm text-gray-500 mt-1">{{ $config['counsellor_contact'] }}</p>@endif
                </div>
                @endif
                @if(!empty($config['description']))
                <div class="prose max-w-none text-gray-600 text-sm">
                    {!! nl2br(e($config['description'])) !!}
                </div>
                @endif
            </div>
        </div>
    </div>
</section>