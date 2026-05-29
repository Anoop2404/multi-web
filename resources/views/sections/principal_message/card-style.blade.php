<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-5xl mx-auto">
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden md:flex">
            @if(!empty($config['photo']))
            <div class="md:w-80 shrink-0">
                <img loading="lazy" src="{{ $config['photo'] }}" alt="Principal"
                     class="w-full h-80 md:h-full object-cover">
            </div>
            @endif
            <div class="p-8 md:p-12 flex flex-col justify-center">
                <p class="text-sm uppercase tracking-widest font-semibold" style="color: var(--color-primary)">
                    {{ $config['label'] ?? 'Principal\'s Message' }}
                </p>
                <h2 class="text-2xl md:text-3xl font-bold font-heading mt-2 mb-4">
                    {{ $config['heading'] ?? 'From the Principal\'s Desk' }}
                </h2>
                <div class="prose max-w-none text-gray-600">
                    {!! nl2br(e($config['message'] ?? '')) !!}
                </div>
                @if(!empty($config['name']))
                <div class="mt-6 pt-6 border-t">
                    <p class="font-bold font-heading" style="color: var(--color-primary)">{{ $config['name'] }}</p>
                    <p class="text-sm text-gray-500">{{ $config['designation'] ?? 'Principal' }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</section>