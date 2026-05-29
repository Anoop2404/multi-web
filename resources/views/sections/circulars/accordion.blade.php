<section class="py-16 px-4" x-data="{ openYear: null }">
    <div class="max-w-4xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['years']) && is_array($config['years']))
        <div class="space-y-3">
            @foreach($config['years'] as $i => $year)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <button @click="openYear = openYear === {{ $i }} ? null : {{ $i }}" class="w-full flex items-center justify-between p-5 text-left font-semibold text-gray-800 hover:bg-gray-50 transition">
                    <span>{{ $year['label'] }}</span>
                    <svg class="w-5 h-5 transition-transform" :class="openYear === {{ $i }} ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="openYear === {{ $i }}" x-collapse x-cloak>
                    <div class="px-5 pb-5 space-y-2">
                        @if(!empty($year['circulars']) && is_array($year['circulars']))
                        @foreach($year['circulars'] as $circular)
                        <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                            <span class="text-sm text-gray-700">{{ $circular['title'] }}</span>
                            @if(!empty($circular['url']))
                            <a href="{{ $circular['url'] }}" target="_blank" class="text-xs font-semibold hover:underline shrink-0" style="color: var(--color-primary);">Download</a>
                            @endif
                        </div>
                        @endforeach
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>