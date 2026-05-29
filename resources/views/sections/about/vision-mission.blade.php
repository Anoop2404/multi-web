<section class="py-16 px-4">
    @php $bg = $config['background'] ?? 'light'; @endphp
    <div class="{{ $bg === 'dark' ? 'bg-gray-900 text-white' : 'bg-white' }}">
        <div class="max-w-7xl mx-auto px-4 py-16">
            @if(!empty($config['heading']))
            <h2 class="text-3xl font-bold font-heading text-center mb-12
                        {{ $bg === 'dark' ? 'text-white' : 'text-gray-900' }}">
                {{ $config['heading'] }}
            </h2>
            @endif

            <div class="grid md:grid-cols-2 gap-8">
                {{-- Vision --}}
                @if(!empty($config['vision']))
                <div class="rounded-2xl p-8 border {{ $bg === 'dark' ? 'border-white/10 bg-white/5' : 'border-gray-100 bg-gray-50' }}">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mb-4"
                         style="background-color: color-mix(in srgb, var(--color-primary) 15%, transparent)">
                        <svg class="w-6 h-6" style="color: var(--color-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold font-heading mb-3 {{ $bg === 'dark' ? 'text-white' : 'text-gray-900' }}">Our Vision</h3>
                    <p class="{{ $bg === 'dark' ? 'text-gray-300' : 'text-gray-600' }} leading-relaxed">{{ $config['vision'] }}</p>
                </div>
                @endif

                {{-- Mission --}}
                @if(!empty($config['mission']))
                <div class="rounded-2xl p-8 border {{ $bg === 'dark' ? 'border-white/10 bg-white/5' : 'border-gray-100 bg-gray-50' }}">
                    <div class="w-12 h-12 rounded-full flex items-center justify-center mb-4"
                         style="background-color: color-mix(in srgb, var(--color-secondary,#f59e0b) 15%, transparent)">
                        <svg class="w-6 h-6" style="color: var(--color-secondary, #f59e0b)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold font-heading mb-3 {{ $bg === 'dark' ? 'text-white' : 'text-gray-900' }}">Our Mission</h3>
                    <p class="{{ $bg === 'dark' ? 'text-gray-300' : 'text-gray-600' }} leading-relaxed">{{ $config['mission'] }}</p>
                </div>
                @endif
            </div>

            {{-- Core values --}}
            @if(!empty($config['values']) && is_array($config['values']))
            <div class="mt-10 grid grid-cols-2 md:grid-cols-4 gap-4">
                @foreach($config['values'] as $value)
                <div class="text-center p-4 rounded-xl {{ $bg === 'dark' ? 'bg-white/5' : 'bg-gray-50' }}">
                    <div class="text-2xl mb-2">{{ $value['icon'] ?? '★' }}</div>
                    <div class="font-semibold text-sm {{ $bg === 'dark' ? 'text-white' : 'text-gray-900' }}">{{ $value['label'] }}</div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</section>
