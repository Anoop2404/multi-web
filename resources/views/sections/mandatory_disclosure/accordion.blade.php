<section class="py-16 px-4" x-data="{ openSection: null }">
    <div class="max-w-4xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['sections']) && is_array($config['sections']))
        <div class="space-y-3">
            @foreach($config['sections'] as $i => $section)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <button @click="openSection = openSection === {{ $i }} ? null : {{ $i }}"
                        class="w-full flex items-center justify-between p-5 text-left font-semibold text-gray-800 hover:bg-gray-50 transition">
                    <span>{{ $section['title'] }}</span>
                    <svg class="w-5 h-5 transition-transform" :class="openSection === {{ $i }} ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="openSection === {{ $i }}" x-collapse x-cloak>
                    <div class="px-5 pb-5 text-sm text-gray-600 prose max-w-none">
                        {!! nl2br(e($section['content'] ?? '')) !!}
                        @if(!empty($section['documents']) && is_array($section['documents']))
                        <div class="mt-3 space-y-2">
                            @foreach($section['documents'] as $doc)
                            <a href="{{ $doc['url'] }}" target="_blank" class="flex items-center gap-2 text-primary hover:underline">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                {{ $doc['label'] ?? 'Download' }}
                            </a>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>