{{-- Top Micro Announcement Ticker Bar --}}
<div class="bg-slate-950 text-white text-[11px] font-semibold py-1.5 px-4 border-b border-white/10 hidden sm:block">
    <div class="max-w-[1440px] mx-auto flex items-center justify-between gap-4">
        <div class="flex items-center gap-2 truncate">
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-accent text-white" style="background-color: var(--color-accent, #f59e0b);">
                <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                Urgent Circular
            </span>
            <span class="text-slate-300 truncate">CBSE Affiliation Renewal Guidelines & Event Schedules Published</span>
        </div>
        <div class="flex items-center gap-4 shrink-0 text-slate-300 text-[10px]">
            <span class="flex items-center gap-1">
                <svg class="w-3 h-3 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582"/></svg>
                80+ CBSE Schools
            </span>
            <a href="/#contact" class="hover:text-amber-400 font-bold transition">Secretariat Helpdesk</a>
        </div>
    </div>
</div>

<nav class="bg-white/95 backdrop-blur-md shadow-sm sticky top-0 z-50 border-b border-slate-200" x-data="{ open: false, activeSubmenu: null }">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16 sm:h-20">
        <a href="{{ $homeUrl ?? '/' }}" class="flex items-center gap-3 min-w-0 shrink-0 group">
            @if(!empty($logo))
                <img loading="lazy" src="{{ $logo }}" class="h-10 sm:h-11 w-auto shrink-0 group-hover:scale-105 transition-transform" alt="{{ $tenant->name ?? 'Sahodaya' }}">
            @else
                <div class="w-10 h-10 rounded-full bg-slate-900 text-white flex items-center justify-center font-bold shadow-sm shrink-0">
                    <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.5M4.5 21V10.5"/>
                    </svg>
                </div>
            @endif
            <div class="min-w-0">
                <p class="font-heading font-extrabold text-base sm:text-lg text-slate-900 leading-tight tracking-tight truncate group-hover:text-primary transition-colors">{{ $tenant->name ?? 'Sahodaya' }}</p>
                <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest leading-none hidden sm:block mt-0.5">CBSE Sahodaya Network</p>
            </div>
        </a>

        <div class="hidden lg:flex items-center gap-3 xl:gap-4 min-w-0 ml-4">
            <div class="flex items-center gap-1 xl:gap-2.5 nav-scroll overflow-x-auto no-scrollbar">
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
                                    class="px-3 py-2 text-xs xl:text-sm font-semibold rounded-lg transition whitespace-nowrap flex items-center gap-1.5 {{ $isCurrent ? 'text-primary font-bold bg-slate-100' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' }}">
                                <span>{{ $label }}</span>
                                <svg class="w-3.5 h-3.5 shrink-0 transition-transform duration-200" :class="activeSubmenu === '{{ md5($label) }}' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                            </button>
                            <div x-show="activeSubmenu === '{{ md5($label) }}'"
                                 x-cloak x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0"
                                 x-transition:leave-end="opacity-0 translate-y-1"
                                 class="absolute left-0 top-full pt-1.5 w-60 z-50">
                                <div class="bg-white rounded-xl shadow-xl border border-slate-200/80 py-2 overflow-hidden">
                                    @foreach($item['children'] as $child)
                                        <a href="{{ $child['url'] }}"
                                           class="block px-4 py-2.5 text-xs xl:text-sm font-medium text-slate-700 hover:bg-slate-50 hover:text-primary transition whitespace-nowrap"
                                           @if($child['external'] ?? false) target="_blank" rel="noopener" @endif>
                                            {{ $child['label'] }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @elseif($isCta)
                        <a href="{{ $url }}"
                           class="px-3.5 py-1.5 rounded-lg text-xs xl:text-sm font-bold whitespace-nowrap shrink-0 transition-all shadow-sm flex items-center gap-1.5 {{ Str::contains($url, ['login']) ? 'bg-slate-100 text-slate-800 hover:bg-slate-200 border border-slate-300' : 'bg-primary text-white hover:opacity-95' }}"
                           @if($item['external'] ?? false) target="_blank" rel="noopener" @endif>
                            <span>{{ $label }}</span>
                        </a>
                    @else
                        <a href="{{ $url }}"
                           class="px-3 py-2 text-xs xl:text-sm font-semibold rounded-lg transition whitespace-nowrap shrink-0 {{ $isCurrent ? 'text-primary font-bold bg-slate-100' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' }}"
                           @if($item['external'] ?? false) target="_blank" rel="noopener" @endif>
                            {{ $label }}
                        </a>
                    @endif
                @endforeach
            </div>
            @include('partials.navbars.portal-cta', ['navConfig' => $navConfig ?? []])
        </div>

        <button @click="open = !open" class="lg:hidden p-2 rounded-xl text-slate-700 hover:bg-slate-100 transition">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      :d="open ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16'"/>
            </svg>
        </button>
    </div>

    <div x-show="open" x-cloak class="lg:hidden border-t border-slate-200 px-4 py-4 space-y-1.5 bg-white shadow-lg">
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
                   class="block text-center px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm transition {{ Str::contains($url, ['login']) ? 'bg-slate-100 text-slate-800 border border-slate-300' : 'bg-primary text-white' }}">
                    {{ $label }}
                </a>
            @else
                <a href="{{ $url }}"
                   class="block px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition">
                    {{ $label }}
                </a>
            @endif
        @endforeach
        @include('partials.navbars.portal-cta-mobile', ['navConfig' => $navConfig ?? []])
    </div>
</nav>
