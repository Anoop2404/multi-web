<nav class="fixed top-0 inset-x-0 z-50 transition-all duration-300"
     x-data="{ scrolled: false }"
     x-init="window.addEventListener('scroll', () => { scrolled = window.scrollY > 50; })"
     :class="scrolled ? 'bg-white shadow-sm' : 'bg-transparent'">
    <div class="max-w-7xl mx-auto px-4 flex items-center justify-between h-16">
        <a href="/" class="flex items-center gap-3">
            @if(isset($tenant) && $tenant->getFirstMediaUrl('logo'))
            <img loading="lazy" src="{{ $tenant->getFirstMediaUrl('logo') }}" class="h-10" alt="{{ $tenant->name }}"
                 :class="scrolled ? '' : 'brightness-0 invert'">
            @else
            <span class="text-xl font-bold font-heading transition-colors"
                  :class="scrolled ? 'text-primary' : 'text-white'" style="color: var(--color-primary);">{{ $tenant->name ?? 'School' }}</span>
            @endif
        </a>
        <div class="hidden md:flex items-center gap-6">
            @foreach($items as $item)
            <a href="{{ $item['url'] }}"
               class="text-sm font-medium transition-colors"
               :class="scrolled ? 'text-gray-600 hover:text-primary' : 'text-white/90 hover:text-white'">{{ $item['label'] }}</a>
            @endforeach
        </div>
        <button @click="open = !open" class="md:hidden p-2" :class="scrolled ? 'text-gray-600' : 'text-white'" x-data="{ open: false }">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>
    {{-- Mobile menu --}}
    <div x-show="open" x-collapse class="md:hidden border-t bg-white px-4 py-3 space-y-2" x-data="{ open: false }">
        @foreach($items as $item)
        <a href="{{ $item['url'] }}" class="block text-gray-700 py-1 text-sm">{{ $item['label'] }}</a>
        @endforeach
    </div>
</nav>
{{-- Spacer for fixed nav --}}
<div class="h-16"></div>