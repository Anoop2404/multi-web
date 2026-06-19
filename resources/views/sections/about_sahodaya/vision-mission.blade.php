{{-- about_sahodaya/vision-mission.blade.php — Two-column: vision on left, mission on right --}}
<section class="py-16 lg:py-20 px-4 bg-white">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <div class="text-center mb-12">
            <p class="text-xs font-bold uppercase tracking-[0.2em] mb-2" style="color: var(--color-secondary)">
                {{ $config['eyebrow'] ?? 'Our Purpose' }}
            </p>
            <h2 class="font-heading text-3xl sm:text-4xl font-bold text-gray-900">{{ $config['heading'] }}</h2>
        </div>
        @endif

        <div class="grid lg:grid-cols-2 gap-8">
            {{-- Vision card --}}
            <div class="relative overflow-hidden rounded-2xl p-8 text-white"
                 style="background: linear-gradient(135deg, var(--color-primary) 0%, var(--color-secondary) 100%);">
                <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2"></div>
                <div class="relative z-10">
                    <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center text-2xl mb-5">👁️</div>
                    <h3 class="font-heading text-2xl font-bold mb-3">{{ $config['vision_heading'] ?? 'Our Vision' }}</h3>
                    <p class="text-white/85 leading-relaxed text-base">
                        {{ $config['vision'] ?? 'Fostering excellence in education by creating a collaborative network of CBSE schools.' }}
                    </p>
                </div>
            </div>

            {{-- Mission card --}}
            <div class="rounded-2xl p-8 bg-gray-50 border border-gray-100">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center text-2xl mb-5"
                     style="background: color-mix(in srgb, var(--color-primary) 15%, white);">🎯</div>
                <h3 class="font-heading text-2xl font-bold text-gray-900 mb-3">{{ $config['mission_heading'] ?? 'Our Mission' }}</h3>
                <p class="text-gray-600 leading-relaxed text-base">
                    {{ $config['mission'] ?? 'To promote academic excellence, sports, cultural activities, and professional development among CBSE affiliated schools.' }}
                </p>

                @if(!empty($config['values']) && is_array($config['values']))
                <ul class="mt-5 space-y-2">
                    @foreach($config['values'] as $value)
                    <li class="flex items-start gap-3 text-sm text-gray-600">
                        <span class="w-5 h-5 rounded-full flex items-center justify-center text-white text-[10px] font-bold shrink-0 mt-0.5"
                              style="background: var(--color-primary)">✓</span>
                        {{ $value }}
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>
        </div>

        {{-- Motto --}}
        @if(!empty($config['motto']))
        <div class="mt-10 text-center">
            <blockquote class="inline-block max-w-2xl">
                <p class="text-xl sm:text-2xl font-heading font-bold text-gray-700 italic">"{{ $config['motto'] }}"</p>
                @if(!empty($config['motto_source']))
                <footer class="text-sm text-gray-400 mt-2">— {{ $config['motto_source'] }}</footer>
                @endif
            </blockquote>
        </div>
        @endif
    </div>
</section>
