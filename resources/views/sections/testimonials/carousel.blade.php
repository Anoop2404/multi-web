@php
    $testimonials = \App\Models\Testimonial::where('tenant_id', $tenant->id)
        ->where('is_active', true)->orderBy('display_order')->get();
@endphp
@if($testimonials->isNotEmpty())
<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            @if(!empty($config['eyebrow']))
            <p class="text-sm font-semibold uppercase tracking-widest mb-1" style="color: var(--color-primary)">{{ $config['eyebrow'] }}</p>
            @endif
            <h2 class="text-3xl font-bold font-heading text-gray-900">{{ $config['heading'] ?? 'What People Say' }}</h2>
        </div>

        <div x-data="{ active: 0, total: {{ $testimonials->count() }} }" class="relative">
            <div class="overflow-hidden">
                @foreach($testimonials as $i => $t)
                <div x-show="active === {{ $i }}" x-transition:enter="transition duration-300"
                     x-transition:enter-start="opacity-0 translate-x-4"
                     x-transition:enter-end="opacity-100 translate-x-0"
                     class="max-w-2xl mx-auto text-center">
                    <svg class="w-10 h-10 mx-auto mb-4 opacity-20" style="color: var(--color-primary)" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.638-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.638-3.996 5.849h3.983v10h-9.983z"/>
                    </svg>
                    <blockquote class="text-xl text-gray-700 italic mb-6">{{ $t->quote }}</blockquote>

                    <div class="flex items-center justify-center gap-3">
                        @if($t->photo)
                        <img loading="lazy" src="{{ $t->photo }}" alt="{{ $t->name }}"
                             class="w-12 h-12 rounded-full object-cover border-2"
                             style="border-color: var(--color-primary)">
                        @endif
                        <div class="text-left">
                            <p class="font-bold text-gray-900">{{ $t->name }}</p>
                            <p class="text-sm text-gray-400">{{ $t->designation }}</p>
                        </div>
                    </div>

                    @if($t->rating)
                    <div class="flex justify-center gap-1 mt-3">
                        @for($r = 1; $r <= 5; $r++)
                        <svg class="w-4 h-4 {{ $r <= $t->rating ? '' : 'opacity-20' }}"
                             style="color: #f59e0b" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        @endfor
                    </div>
                    @endif
                </div>
                @endforeach
            </div>

            {{-- Navigation dots --}}
            <div class="flex justify-center gap-2 mt-8">
                @foreach($testimonials as $i => $t)
                <button @click="active = {{ $i }}"
                        class="w-2.5 h-2.5 rounded-full transition"
                        :class="active === {{ $i }} ? 'scale-125' : 'bg-gray-300'"
                        :style="active === {{ $i }} ? 'background-color: var(--color-primary)' : ''">
                </button>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endif
