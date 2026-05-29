<section class="py-16 px-4">
    <div class="max-w-6xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-12" style="color: var(--color-primary)">
            {{ $config['heading'] }}
        </h2>
        @endif
        <div class="grid md:grid-cols-3 gap-8">
            {{-- Principal --}}
            <div class="text-center">
                @if(!empty($config['principal_photo']))
                <img loading="lazy" src="{{ $config['principal_photo'] }}" alt="Principal"
                     class="w-40 h-40 rounded-full mx-auto object-cover shadow-lg mb-4">
                @endif
                <h3 class="text-xl font-bold font-heading" style="color: var(--color-primary)">{{ $config['principal_name'] ?? 'Principal' }}</h3>
                <p class="text-sm text-gray-500">Principal</p>
                <div class="mt-3 text-gray-600 text-sm">{!! nl2br(e($config['principal_message'] ?? '')) !!}</div>
            </div>
            {{-- Chairman --}}
            <div class="text-center">
                @if(!empty($config['chairman_photo']))
                <img loading="lazy" src="{{ $config['chairman_photo'] }}" alt="Chairman"
                     class="w-40 h-40 rounded-full mx-auto object-cover shadow-lg mb-4">
                @endif
                <h3 class="text-xl font-bold font-heading" style="color: var(--color-primary)">{{ $config['chairman_name'] ?? 'Chairman' }}</h3>
                <p class="text-sm text-gray-500">Chairman</p>
                <div class="mt-3 text-gray-600 text-sm">{!! nl2br(e($config['chairman_message'] ?? '')) !!}</div>
            </div>
            {{-- Director --}}
            <div class="text-center">
                @if(!empty($config['director_photo']))
                <img loading="lazy" src="{{ $config['director_photo'] }}" alt="Director"
                     class="w-40 h-40 rounded-full mx-auto object-cover shadow-lg mb-4">
                @endif
                <h3 class="text-xl font-bold font-heading" style="color: var(--color-primary)">{{ $config['director_name'] ?? 'Director' }}</h3>
                <p class="text-sm text-gray-500">Director</p>
                <div class="mt-3 text-gray-600 text-sm">{!! nl2br(e($config['director_message'] ?? '')) !!}</div>
            </div>
        </div>
    </div>
</section>