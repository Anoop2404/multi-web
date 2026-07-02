{{-- Pill navbar — uses tenant theme CSS variables --}}
<nav class="fixed top-0 left-0 right-0 z-50 px-3 sm:px-5 pt-3 sm:pt-4 backdrop-blur-md cksc-pill-nav"
     x-data="{ open: false, activeSubmenu: null }">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-8 py-3 sm:py-4 bg-white/95 rounded-[19px] shadow-lg flex items-center justify-center relative">
        @if(!empty($logo))
        <a href="/" class="absolute left-4 sm:left-6 top-1/2 -translate-y-1/2 z-10 hidden sm:block">
            <img src="{{ $logo }}" alt="{{ $tenant->name }}" class="h-10 w-10 sm:h-12 sm:w-12 rounded-full object-cover border-2 border-white shadow-md">
        </a>
        @endif
        <button @click="open = !open" class="md:hidden absolute left-4 p-2 rounded-lg hover:bg-gray-100" aria-label="Menu">
            <div class="flex flex-col gap-1">
                <span class="block w-6 h-0.5 rounded bg-primary"></span>
                <span class="block w-6 h-0.5 rounded bg-primary"></span>
                <span class="block w-6 h-0.5 rounded bg-primary"></span>
            </div>
        </button>

        <div class="hidden md:flex items-center gap-3 lg:gap-5 flex-wrap justify-center">
            @foreach($items as $item)
                @if(!empty($item['children']))
                <div class="relative" @mouseenter="activeSubmenu = '{{ md5($item['label']) }}'" @mouseleave="activeSubmenu = null">
                    <button type="button"
                            class="px-4 py-2.5 text-sm font-medium rounded-lg transition-all flex items-center gap-1.5 whitespace-nowrap text-primary"
                            :class="activeSubmenu === '{{ md5($item['label']) }}' ? 'bg-primary text-white' : ''">
                        {{ $item['label'] }}
                        <svg class="w-3.5 h-3.5 transition-transform" :class="activeSubmenu === '{{ md5($item['label']) }}' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="activeSubmenu === '{{ md5($item['label']) }}'" x-cloak x-transition
                         class="absolute left-0 top-full pt-2 min-w-[220px] z-50">
                        <div class="bg-white/98 rounded-lg shadow-xl py-2 overflow-hidden border border-gray-100">
                            @foreach($item['children'] as $child)
                            <a href="{{ $child['url'] }}"
                               class="block px-5 py-2.5 text-sm font-medium text-primary hover:bg-primary hover:text-white transition"
                               @if($child['external'] ?? false) target="_blank" rel="noopener" @endif>
                                {{ $child['label'] }}
                            </a>
                            @endforeach
                        </div>
                    </div>
                </div>
                @else
                <a href="{{ $item['url'] }}"
                   class="px-4 py-2.5 text-sm font-medium rounded-lg transition-all whitespace-nowrap text-primary hover:bg-primary hover:text-white"
                   @if($item['external'] ?? false) target="_blank" rel="noopener" @endif>
                    {{ $item['label'] }}
                </a>
                @endif
            @endforeach
        </div>

        @php $cta = ($navConfig ?? [])['portal_cta'] ?? []; @endphp
        @if($cta['show_in_navbar'] ?? false)
        <div class="hidden lg:flex items-center absolute right-4">
            <a href="{{ $cta['portal_url'] ?? $cta['login_url'] ?? '/portal' }}"
               class="px-4 py-2 text-xs font-bold rounded-lg text-white whitespace-nowrap bg-primary">
                {{ $cta['portal_label'] ?? $cta['login_label'] ?? 'Login' }}
            </a>
        </div>
        @endif
    </div>

    <div x-show="open" x-cloak x-transition
         class="md:hidden max-w-[1440px] mx-auto mt-1 px-4 py-4 bg-white/98 rounded-b-[19px] shadow-lg space-y-1">
        @foreach($items as $item)
            @if(!empty($item['children']))
            <div x-data="{ sub: false }">
                <button @click="sub = !sub" type="button"
                        class="w-full flex items-center justify-between px-4 py-2.5 text-sm font-medium rounded-lg text-primary">
                    {{ $item['label'] }}
                    <svg class="w-4 h-4 transition-transform" :class="sub ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="sub" x-cloak class="pl-4 space-y-1 pb-2">
                    @foreach($item['children'] as $child)
                    <a href="{{ $child['url'] }}" class="block px-4 py-2 text-sm rounded-lg hover:bg-gray-50 text-primary">{{ $child['label'] }}</a>
                    @endforeach
                </div>
            </div>
            @else
            <a href="{{ $item['url'] }}" class="block px-4 py-2.5 text-sm font-medium rounded-lg hover:bg-gray-50 text-primary">{{ $item['label'] }}</a>
            @endif
        @endforeach
        @if($cta['show_in_navbar'] ?? false)
        <div class="pt-3 mt-2 border-t space-y-2">
            <a href="{{ $cta['portal_url'] ?? $cta['login_url'] ?? '/portal' }}" class="block text-center px-4 py-2.5 rounded-lg text-sm font-bold text-white bg-primary">{{ $cta['portal_label'] ?? $cta['login_label'] ?? 'Login' }}</a>
        </div>
        @endif
    </div>
</nav>
<div class="h-[88px] sm:h-[96px]" aria-hidden="true"></div>

<style>
.cksc-pill-nav .text-primary { color: var(--color-primary); }
.cksc-pill-nav .border-primary { border-color: var(--color-primary); }
.cksc-pill-nav .bg-primary { background-color: var(--color-primary); }
.cksc-pill-nav a.bg-primary:hover,
.cksc-pill-nav button.bg-primary:hover { filter: brightness(1.08); }
</style>
