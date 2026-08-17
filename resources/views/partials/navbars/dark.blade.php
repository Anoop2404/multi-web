<nav x-data="{ open: false }" class="sticky top-0 z-50 text-white shadow"
     style="background-color: var(--color-primary)">
    <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-16">
        <a href="{{ $homeUrl ?? '/' }}" class="flex items-center gap-3 min-w-0">
            @if(isset($tenant) && !empty($logo = \App\Support\TenantBranding::logoUrl($tenant)))
                <img loading="lazy" src="{{ $logo }}" class="h-10 w-auto shrink-0 brightness-0 invert" alt="{{ $tenant->name }}">
            @endif
            <div class="min-w-0">
                <p class="font-heading font-extrabold text-base sm:text-lg text-white leading-tight tracking-tight truncate">{{ $tenant->name ?? 'Sahodaya' }}</p>
                <p class="text-[10px] sm:text-xs font-bold text-amber-300 uppercase tracking-widest leading-none hidden sm:block">CBSE Sahodaya Network</p>
            </div>
        </a>

        <div class="hidden md:flex items-center gap-6">
            @foreach($items as $item)
            <a href="{{ $item['url'] }}"
               class="text-sm font-medium text-white/80 hover:text-white transition-colors">
                {{ $item['label'] }}
            </a>
            @endforeach
        </div>

        <button @click="open = !open" class="md:hidden p-2 text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      :d="open ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16'"/>
            </svg>
        </button>
    </div>

    <div x-show="open" x-collapse class="md:hidden border-t border-white/20 px-4 py-3 space-y-1">
        @foreach($items as $item)
        <a href="{{ $item['url'] }}" class="block text-white/80 hover:text-white py-2 text-sm font-medium">
            {{ $item['label'] }}
        </a>
        @endforeach
    </div>
</nav>
