<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-12" style="color: var(--color-primary)">
            {{ $config['heading'] }}
        </h2>
        @endif
        <div class="grid md:grid-cols-2 gap-12">
            <div>
                <h3 class="text-xl font-bold font-heading mb-4" style="color: var(--color-secondary)">{{ $config['left_title'] ?? 'Our History' }}</h3>
                <div class="prose max-w-none text-gray-600">
                    {!! nl2br(e($config['left_content'] ?? '')) !!}
                </div>
            </div>
            <div>
                <h3 class="text-xl font-bold font-heading mb-4" style="color: var(--color-secondary)">{{ $config['right_title'] ?? 'Vision & Mission' }}</h3>
                <div class="prose max-w-none text-gray-600">
                    {!! nl2br(e($config['right_content'] ?? '')) !!}
                </div>
                @if(!empty($config['vision']))
                <div class="mt-6 p-4 rounded-lg" style="background-color: color-mix(in srgb, var(--color-primary) 10%, transparent)">
                    <p class="font-semibold" style="color: var(--color-primary)">Our Vision</p>
                    <p class="text-gray-600 text-sm mt-1">{{ $config['vision'] }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</section>