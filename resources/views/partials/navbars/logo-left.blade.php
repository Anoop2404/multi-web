<nav class="bg-white shadow-sm sticky top-0 z-50" x-data="{ open: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
        <a href="/" class="flex items-center gap-3">
            @if(isset($tenant) && $tenant->getFirstMediaUrl('logo'))
                <img loading="lazy" src="{{ $tenant->getFirstMediaUrl('logo') }}" class="h-10" alt="{{ $tenant->name }}">
            @else
                <span class="text-xl font-bold text-primary font-heading">{{ $tenant->name ?? 'School' }}</span>
            @endif
        </a>

        <div class="hidden md:flex items-center gap-6">
            @foreach($items as $item)
                <a href="{{ $item['url'] }}" class="text-gray-600 hover:text-primary transition-colors text-sm font-medium">
                    {{ $item['label'] }}
                </a>
            @endforeach
        </div>

        <button @click="open = !open" class="md:hidden p-2">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>

    <div x-show="open" class="md:hidden border-t px-4 py-3 space-y-2">
        @foreach($items as $item)
            <a href="{{ $item['url'] }}" class="block text-gray-700 hover:text-primary py-1 text-sm">
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
