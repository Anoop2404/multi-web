<nav class="bg-white shadow-sm sticky top-0 z-50" x-data="{ open: false }">
    <div class="max-w-7xl mx-auto px-4">
        <div class="flex items-center justify-center py-4">
            <a href="/" class="flex items-center gap-3">
                @if(isset($tenant) && $tenant->getFirstMediaUrl('logo'))
                <img loading="lazy" src="{{ $tenant->getFirstMediaUrl('logo') }}" class="h-12" alt="{{ $tenant->name }}">
                @else
                <span class="text-2xl font-bold font-heading" style="color: var(--color-primary)">{{ $tenant->name ?? 'School' }}</span>
                @endif
            </a>
        </div>
        <div class="hidden md:flex items-center justify-center gap-8 pb-3 border-t border-gray-100 pt-3">
            @foreach($items as $item)
            <a href="{{ $item['url'] }}" class="text-sm font-medium text-gray-600 hover:text-primary transition-colors">{{ $item['label'] }}</a>
            @endforeach
        </div>
        <div class="md:hidden flex justify-end pb-3">
            <button @click="open = !open" class="p-2 text-gray-600">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </div>
    <div x-show="open" x-collapse class="md:hidden border-t px-4 py-3 space-y-2">
        @foreach($items as $item)
        <a href="{{ $item['url'] }}" class="block text-gray-700 py-1 text-sm">{{ $item['label'] }}</a>
        @endforeach
    </div>
</nav>