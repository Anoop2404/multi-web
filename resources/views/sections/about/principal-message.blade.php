<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-5xl mx-auto">
        <div class="bg-white rounded-2xl shadow-sm overflow-hidden">
            <div class="grid md:grid-cols-3">
                {{-- Principal photo + details --}}
                <div class="p-8 flex flex-col items-center justify-center text-center" style="background-color: var(--color-primary)">
                    @if(!empty($config['photo']))
                    <div class="w-36 h-36 rounded-full overflow-hidden border-4 border-white/30 mb-4">
                        <img loading="lazy" src="{{ $config['photo'] }}" alt="{{ $config['name'] ?? 'Principal' }}"
                             class="w-full h-full object-cover">
                    </div>
                    @else
                    <div class="w-36 h-36 rounded-full bg-white/20 flex items-center justify-center mb-4">
                        <svg class="w-16 h-16 text-white/50" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z"/>
                        </svg>
                    </div>
                    @endif
                    <h3 class="text-white font-bold text-lg">{{ $config['name'] ?? 'Principal' }}</h3>
                    <p class="text-white/80 text-sm">{{ $config['designation'] ?? 'Principal' }}</p>
                    @if(!empty($config['qualification']))
                    <p class="text-white/60 text-xs mt-1">{{ $config['qualification'] }}</p>
                    @endif
                </div>

                {{-- Message --}}
                <div class="md:col-span-2 p-8 md:p-10 flex flex-col justify-center">
                    <p class="text-sm font-semibold uppercase tracking-widest mb-3" style="color: var(--color-primary)">
                        {{ $config['eyebrow'] ?? "Principal's Message" }}
                    </p>
                    <h2 class="text-2xl font-bold font-heading text-gray-900 mb-4">
                        {{ $config['heading'] ?? 'A Message from Our Principal' }}
                    </h2>
                    <blockquote class="text-gray-600 leading-relaxed italic border-l-4 pl-4 mb-6"
                                style="border-color: var(--color-primary)">
                        {{ $config['message'] ?? '' }}
                    </blockquote>
                    <div class="flex items-center gap-3">
                        <div class="h-px flex-1 bg-gray-100"></div>
                        <span class="text-sm text-gray-400">{{ $config['name'] ?? '' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
