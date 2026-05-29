<section class="py-16 px-4" x-data="{ activeCategory: 'all' }">
    <div class="max-w-6xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-4" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['categories']) && is_array($config['categories']))
        <div class="flex flex-wrap justify-center gap-2 mb-8">
            <button @click="activeCategory = 'all'" class="px-4 py-2 rounded-full text-sm transition"
                    :class="activeCategory === 'all' ? 'text-white' : 'bg-gray-100 text-gray-600'"
                    x-bind:style="activeCategory === 'all' ? 'background-color: var(--color-primary);' : ''">All</button>
            @foreach($config['categories'] as $cat)
            <button @click="activeCategory = '{{ $cat['key'] }}'" class="px-4 py-2 rounded-full text-sm transition"
                    :class="activeCategory === '{{ $cat['key'] }}' ? 'text-white' : 'bg-gray-100 text-gray-600'"
                    x-bind:style="activeCategory === '{{ $cat['key'] }}' ? 'background-color: var(--color-primary);' : ''">{{ $cat['label'] }}</button>
            @endforeach
        </div>
        @endif
        @if(!empty($config['circulars']) && is_array($config['circulars']))
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($config['circulars'] as $circular)
            <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100 hover:shadow-md transition"
                 x-show="activeCategory === 'all' || activeCategory === '{{ $circular['category_key'] ?? '' }}'" x-cloak>
                <p class="text-xs font-semibold" style="color: var(--color-primary);">{{ $circular['date'] ?? '' }}</p>
                <h3 class="font-semibold text-gray-800 mt-1 text-sm">{{ $circular['title'] }}</h3>
                @if(!empty($circular['url']))
                <a href="{{ $circular['url'] }}" target="_blank" class="inline-flex items-center gap-1 mt-3 text-xs font-semibold hover:underline" style="color: var(--color-primary);">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Download
                </a>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>