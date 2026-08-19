{{-- Pill navbar — uses tenant theme CSS variables --}}
<nav class="fixed top-0 left-0 right-0 z-50 px-3 sm:px-5 pt-3 sm:pt-4 backdrop-blur-md cksc-pill-nav"
     x-data="{ open: false, activeSubmenu: null }">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-8 py-3 sm:py-3.5 bg-white/95 rounded-[19px] shadow-lg flex items-center justify-between relative">
        @if(!empty($logo))
        <a href="{{ $homeUrl ?? '/' }}" class="flex items-center gap-3 shrink-0 z-10">
            <img src="{{ $logo }}" alt="{{ $tenant->name }}" class="h-9 w-9 sm:h-11 sm:w-11 rounded-full object-cover border-2 border-white shadow-md">
            <span class="font-heading font-bold text-sm sm:text-base text-gray-900 hidden md:inline-block truncate max-w-[200px]">{{ $tenant->name }}</span>
        </a>
        @else
        <a href="{{ $homeUrl ?? '/' }}" class="flex items-center gap-2 shrink-0 z-10">
            <div class="h-9 w-9 sm:h-10 sm:w-10 rounded-full bg-primary text-white flex items-center justify-center font-bold text-xs shadow-md">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.5M4.5 21V10.5"/></svg>
            </div>
            <span class="font-heading font-bold text-sm sm:text-base text-gray-900 hidden md:inline-block truncate max-w-[200px]">{{ $tenant->name }}</span>
        </a>
        @endif

        <button @click="open = !open" class="md:hidden p-2 rounded-lg hover:bg-gray-100" aria-label="Menu">
            <div class="flex flex-col gap-1">
                <span class="block w-6 h-0.5 rounded bg-primary"></span>
                <span class="block w-6 h-0.5 rounded bg-primary"></span>
                <span class="block w-6 h-0.5 rounded bg-primary"></span>
            </div>
        </button>

        <div class="hidden md:flex items-center gap-1.5 lg:gap-3 overflow-x-auto no-scrollbar mx-auto">
            @foreach($items as $item)
                @php
                    $label = $item['label'] ?? '';
                    $url = $item['url'] ?? '#';
                    $isCta = in_array(strtolower($label), ['school registration', 'school login', 'register', 'login']) 
                          || Str::contains($url, ['/school-register', '/school-login', '/login']);
                    $isCurrent = request()->url() === url($url);
                @endphp

                @if(!empty($item['children']))
                <div class="relative shrink-0" @mouseenter="activeSubmenu = '{{ md5($label) }}'" @mouseleave="activeSubmenu = null">
                    <button type="button"
                            class="px-3.5 py-2 text-xs xl:text-sm font-semibold rounded-xl transition-all flex items-center gap-1.5 whitespace-nowrap text-primary hover:bg-primary/10"
                            :class="activeSubmenu === '{{ md5($label) }}' ? 'bg-primary text-white hover:bg-primary' : ''">
                        <span>{{ $label }}</span>
                        <svg class="w-3.5 h-3.5 transition-transform" :class="activeSubmenu === '{{ md5($label) }}' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="activeSubmenu === '{{ md5($label) }}'" x-cloak x-transition
                         class="absolute left-0 top-full pt-2 min-w-[220px] z-50">
                        <div class="bg-white/98 backdrop-blur-xl rounded-xl shadow-xl py-2 overflow-hidden border border-gray-100">
                            @foreach($item['children'] as $child)
                            <a href="{{ $child['url'] }}"
                               class="block px-4 py-2.5 text-xs xl:text-sm font-medium text-primary hover:bg-primary hover:text-white transition whitespace-nowrap"
                               @if($child['external'] ?? false) target="_blank" rel="noopener" @endif>
                                {{ $child['label'] }}
                            </a>
                            @endforeach
                        </div>
                    </div>
                </div>
                @elseif($isCta)
                <a href="{{ $url }}"
                   class="px-4 py-2 text-xs xl:text-sm font-bold rounded-xl transition-all whitespace-nowrap shrink-0 shadow-sm flex items-center gap-1 {{ Str::contains($url, ['login']) ? 'bg-gray-100 text-primary border border-gray-200 hover:bg-gray-200' : 'bg-primary text-white hover:opacity-95' }}"
                   @if($item['external'] ?? false) target="_blank" rel="noopener" @endif>
                    <span>{{ $label }}</span>
                </a>
                @else
                <a href="{{ $url }}"
                   class="px-3.5 py-2 text-xs xl:text-sm font-semibold rounded-xl transition-all whitespace-nowrap shrink-0 text-primary hover:bg-primary hover:text-white {{ $isCurrent ? 'bg-primary text-white font-bold' : '' }}"
                   @if($item['external'] ?? false) target="_blank" rel="noopener" @endif>
                    {{ $label }}
                </a>
                @endif
            @endforeach
        </div>

        @php $cta = ($navConfig ?? [])['portal_cta'] ?? []; @endphp
        @if($cta['show_in_navbar'] ?? false)
        <div class="hidden lg:flex items-center shrink-0 ml-2">
            <a href="{{ $cta['portal_url'] ?? $cta['login_url'] ?? '/portal' }}"
               class="px-4 py-2 text-xs font-bold rounded-xl text-white whitespace-nowrap bg-primary shadow-sm hover:opacity-95 transition">
                {{ $cta['portal_label'] ?? $cta['login_label'] ?? 'Login' }}
            </a>
        </div>
        @endif
    </div>

    {{-- Mobile drawer --}}
    <div x-show="open" x-cloak x-transition
         class="md:hidden max-w-[1440px] mx-auto mt-1 px-4 py-4 bg-white/98 rounded-b-[19px] shadow-lg space-y-1">
        @foreach($items as $item)
            @php
                $label = $item['label'] ?? '';
                $url = $item['url'] ?? '#';
                $isCta = in_array(strtolower($label), ['school registration', 'school login', 'register', 'login']) 
                      || Str::contains($url, ['/school-register', '/school-login', '/login']);
            @endphp
            @if(!empty($item['children']))
            <div x-data="{ sub: false }">
                <button @click="sub = !sub" type="button"
                        class="w-full flex items-center justify-between px-4 py-2.5 text-sm font-semibold rounded-lg text-primary">
                    <span>{{ $label }}</span>
                    <svg class="w-4 h-4 transition-transform" :class="sub ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="sub" x-cloak class="pl-4 space-y-1 pb-2 border-l-2 border-gray-100">
                    @foreach($item['children'] as $child)
                    <a href="{{ $child['url'] }}" class="block px-4 py-2 text-xs font-semibold rounded-lg hover:bg-gray-50 text-primary">{{ $child['label'] }}</a>
                    @endforeach
                </div>
            </div>
            @elseif($isCta)
            <a href="{{ $url }}"
               class="block text-center px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm transition {{ Str::contains($url, ['login']) ? 'bg-gray-100 text-primary border border-gray-200' : 'bg-primary text-white' }}">
                {{ $label }}
            </a>
            @else
            <a href="{{ $url }}" class="block px-4 py-2.5 text-sm font-semibold rounded-lg hover:bg-gray-50 text-primary">{{ $label }}</a>
            @endif
        @endforeach
        @if($cta['show_in_navbar'] ?? false)
        <div class="pt-3 mt-2 border-t space-y-2">
            <a href="{{ $cta['portal_url'] ?? $cta['login_url'] ?? '/portal' }}" class="block text-center px-4 py-2.5 rounded-xl text-sm font-bold text-white bg-primary">{{ $cta['portal_label'] ?? $cta['login_label'] ?? 'Login' }}</a>
        </div>
        @endif
    </div>
</nav>
<div class="h-[80px] sm:h-[88px]" aria-hidden="true"></div>

<style>
.cksc-pill-nav .text-primary { color: var(--color-primary, #4f46e5); }
.cksc-pill-nav .border-primary { border-color: var(--color-primary, #4f46e5); }
.cksc-pill-nav .bg-primary { background-color: var(--color-primary, #4f46e5); }
.cksc-pill-nav a.bg-primary:hover,
.cksc-pill-nav button.bg-primary:hover { filter: brightness(1.08); }
</style>
