<nav class="bg-white shadow-sm sticky top-0 z-50" x-data="{ open: false }">
    <div class="max-w-7xl mx-auto px-4">
        {{-- Top row: logo centered --}}
        <div class="flex items-center justify-center py-3 border-b border-gray-100">
            <a href="/" class="flex items-center gap-3">
                @if(isset($tenant) && !empty($logo = $tenant->getSetting('logo')))
                    <img loading="lazy" src="{{ $logo }}" class="h-14" alt="{{ $tenant->name }}">
                @else
                    <div class="text-center">
                        <div class="text-xl font-bold font-heading" style="color: var(--color-primary)">{{ $tenant->name ?? 'School' }}</div>
                    </div>
                @endif
            </a>
        </div>

        {{-- Bottom nav row --}}
        <div class="hidden md:flex items-center justify-center gap-8 h-11">
            @foreach($items as $item)
            <a href="{{ $item['url'] }}"
               class="text-sm font-medium text-gray-600 hover:text-white hover:px-3 hover:py-1 hover:rounded-full transition-all"
               onmouseover="this.style.backgroundColor='var(--color-primary)'; this.style.color='white'"
               onmouseout="this.style.backgroundColor=''; this.style.color=''">
                {{ $item['label'] }}
            </a>
            @endforeach
        </div>

        {{-- Mobile toggle --}}
        <div class="md:hidden flex justify-end py-2">
            <button @click="open = !open" class="p-2 text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          :d="open ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16'"/>
                </svg>
            </button>
        </div>
    </div>

    <div x-show="open" x-collapse class="md:hidden border-t px-4 py-3 space-y-1">
        @foreach($items as $item)
        <a href="{{ $item['url'] }}" class="block text-gray-700 py-2 text-sm font-medium hover:text-primary transition">
            {{ $item['label'] }}
        </a>
        @endforeach
    </div>
</nav>
