<nav class="fixed top-0 inset-x-0 z-50 transition-all duration-300 backdrop-blur-md"
     x-data="{ scrolled: false, open: false, activeSubmenu: null }"
     x-init="window.addEventListener('scroll', () => { scrolled = window.scrollY > 50; })"
     :class="scrolled ? 'bg-white/95 text-slate-900 shadow-md border-b border-slate-200/80' : 'bg-slate-950/40 text-white border-b border-white/10'">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16 sm:h-20">
        <a href="{{ $homeUrl ?? '/' }}" class="flex items-center gap-3 shrink-0 group">
            @if(!empty($logo))
            <img loading="lazy" src="{{ $logo }}" class="h-10 sm:h-11 w-auto transition-transform group-hover:scale-105" alt="{{ $tenant->name }}"
                 :class="scrolled ? '' : 'brightness-0 invert'">
            @else
            <span class="text-lg sm:text-xl font-bold font-heading transition-colors truncate max-w-[240px]"
                  :class="scrolled ? 'text-primary' : 'text-white'" style="color: var(--color-primary, #4f46e5);">{{ $tenant->name ?? 'School' }}</span>
            @endif
        </a>

        <div class="hidden md:flex items-center gap-1.5 lg:gap-3 overflow-x-auto no-scrollbar ml-4">
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
                                class="px-3 py-2 text-xs lg:text-sm font-semibold rounded-lg transition-colors whitespace-nowrap flex items-center gap-1.5"
                                :class="scrolled ? 'text-slate-700 hover:text-slate-900 hover:bg-slate-100' : 'text-white/90 hover:text-white hover:bg-white/10'">
                            <span>{{ $label }}</span>
                            <svg class="w-3.5 h-3.5 shrink-0 transition-transform" :class="activeSubmenu === '{{ md5($label) }}' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="activeSubmenu === '{{ md5($label) }}'" x-cloak x-transition
                             class="absolute left-0 top-full pt-1.5 w-56 z-50">
                            <div class="bg-white rounded-xl shadow-xl border border-slate-100 py-2 overflow-hidden text-slate-800">
                                @foreach($item['children'] as $child)
                                <a href="{{ $child['url'] }}"
                                   class="block px-4 py-2.5 text-xs lg:text-sm font-medium hover:bg-slate-50 hover:text-primary transition whitespace-nowrap"
                                   @if($child['external'] ?? false) target="_blank" rel="noopener" @endif>
                                    {{ $child['label'] }}
                                </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @elseif($isCta)
                    <a href="{{ $url }}"
                       class="px-3.5 py-1.5 text-xs lg:text-sm font-bold rounded-lg transition whitespace-nowrap shrink-0 shadow-sm flex items-center gap-1 {{ Str::contains($url, ['login']) ? 'bg-white/20 text-white border border-white/30' : 'bg-primary text-white hover:opacity-95' }}"
                       @if($item['external'] ?? false) target="_blank" rel="noopener" @endif>
                        <span>{{ $label }}</span>
                    </a>
                @else
                    <a href="{{ $url }}"
                       class="px-3 py-2 text-xs lg:text-sm font-semibold transition-colors whitespace-nowrap shrink-0 rounded-lg"
                       :class="scrolled ? 'text-slate-700 hover:text-slate-900 hover:bg-slate-100' : 'text-white/90 hover:text-white hover:bg-white/10'">
                        {{ $label }}
                    </a>
                @endif
            @endforeach
        </div>

        <button @click="open = !open" class="md:hidden p-2 rounded-xl" :class="scrolled ? 'text-slate-700 hover:bg-slate-100' : 'text-white hover:bg-white/10'">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
    </div>

    {{-- Mobile menu --}}
    <div x-show="open" x-collapse class="md:hidden border-t bg-white text-slate-800 px-4 py-4 space-y-1.5 shadow-xl">
        @foreach($items as $item)
            @php
                $label = $item['label'] ?? '';
                $url = $item['url'] ?? '#';
                $isCta = in_array(strtolower($label), ['school registration', 'school login', 'register', 'login']) 
                      || Str::contains($url, ['/school-register', '/school-login', '/login']);
            @endphp
            @if(!empty($item['children']))
                <div x-data="{ subOpen: false }">
                    <button @click="subOpen = !subOpen"
                            class="w-full flex items-center justify-between px-3 py-2 text-sm font-bold text-slate-700 rounded-lg hover:bg-slate-50">
                        <span>{{ $label }}</span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="subOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="subOpen" x-cloak class="pl-4 space-y-1 mt-1 border-l-2 border-slate-100">
                        @foreach($item['children'] as $child)
                        <a href="{{ $child['url'] }}" class="block px-3 py-2 text-xs font-semibold text-slate-600 rounded-lg hover:bg-slate-50">{{ $child['label'] }}</a>
                        @endforeach
                    </div>
                </div>
            @elseif($isCta)
                <a href="{{ $url }}"
                   class="block text-center px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm transition bg-primary text-white">
                    {{ $label }}
                </a>
            @else
                <a href="{{ $url }}" class="block text-slate-700 py-2 px-3 text-sm font-semibold rounded-lg hover:bg-slate-50">{{ $label }}</a>
            @endif
        @endforeach
    </div>
</nav>
{{-- Spacer for fixed nav --}}
<div class="h-16 sm:h-20"></div>
