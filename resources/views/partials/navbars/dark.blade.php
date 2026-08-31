<nav x-data="{ open: false, activeSubmenu: null }"
     class="sticky top-0 z-50 text-white shadow-lg border-b border-white/10 backdrop-blur-md"
     style="background-color: var(--color-primary, #1e1b4b)">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 sm:h-20">
            {{-- Brand / Logo area --}}
            <a href="{{ $homeUrl ?? '/' }}" class="flex items-center gap-3 min-w-0 group">
                @if(isset($tenant) && !empty($logo = \App\Support\TenantBranding::logoUrl($tenant)))
                    <img loading="lazy" src="{{ $logo }}" class="h-10 sm:h-11 w-auto max-w-[140px] shrink-0 object-contain group-hover:scale-105 transition-transform" alt="{{ $tenant->name }}">
                @else
                    <div class="w-10 h-10 rounded-full bg-gradient-to-tr from-amber-400 to-amber-200 text-slate-950 flex items-center justify-center font-bold shadow-md shrink-0">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.5M4.5 21V10.5"/>
                        </svg>
                    </div>
                @endif
                <div class="min-w-0">
                    <p class="font-heading font-extrabold text-sm sm:text-base text-white leading-tight tracking-tight truncate group-hover:text-amber-300 transition-colors">
                        {{ $tenant->name ?? 'Sahodaya' }}
                    </p>
                    <p class="text-[10px] sm:text-xs font-bold text-amber-300 uppercase tracking-widest leading-none hidden sm:block mt-0.5">
                        CBSE Sahodaya Network
                    </p>
                </div>
            </a>

            {{-- Desktop Navigation Items --}}
            <div class="hidden lg:flex items-center gap-1 xl:gap-2.5 min-w-0 ml-4">
                <div class="flex items-center gap-1 xl:gap-2 nav-scroll">
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
                                        class="px-3 py-2 text-xs xl:text-sm font-semibold rounded-lg transition whitespace-nowrap flex items-center gap-1.5 {{ $isCurrent ? 'bg-white/15 text-white' : 'text-white/85 hover:text-white hover:bg-white/10' }}">
                                    <span>{{ $label }}</span>
                                    <svg class="w-3.5 h-3.5 shrink-0 transition-transform duration-200" :class="activeSubmenu === '{{ md5($label) }}' ? 'rotate-180 text-amber-300' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div x-show="activeSubmenu === '{{ md5($label) }}'"
                                     x-cloak x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0 translate-y-1"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                     x-transition:leave="transition ease-in duration-100"
                                     x-transition:leave-start="opacity-100 translate-y-0"
                                     x-transition:leave-end="opacity-0 translate-y-1"
                                     class="absolute left-0 top-full pt-2 min-w-[220px] z-50">
                                    <div class="bg-slate-900/95 backdrop-blur-xl rounded-xl shadow-2xl border border-white/15 py-2 overflow-hidden">
                                        @foreach($item['children'] as $child)
                                            <a href="{{ $child['url'] }}"
                                               class="block px-4 py-2.5 text-xs xl:text-sm font-medium text-slate-200 hover:bg-white/10 hover:text-amber-300 transition whitespace-nowrap"
                                               @if($child['external'] ?? false) target="_blank" rel="noopener" @endif>
                                                {{ $child['label'] }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @elseif($isCta)
                            <a href="{{ $url }}"
                               class="px-3.5 py-1.5 rounded-xl text-xs xl:text-sm font-bold whitespace-nowrap shrink-0 transition-all shadow-sm flex items-center gap-1.5 {{ Str::contains($url, ['login']) ? 'bg-white/15 text-white hover:bg-white/25 border border-white/20' : 'bg-gradient-to-r from-amber-400 to-amber-500 text-slate-950 hover:from-amber-300 hover:to-amber-400' }}"
                               @if($item['external'] ?? false) target="_blank" rel="noopener" @endif>
                                <span>{{ $label }}</span>
                            </a>
                        @else
                            <a href="{{ $url }}"
                               class="px-2.5 xl:px-3 py-2 text-xs xl:text-sm font-semibold rounded-lg transition whitespace-nowrap shrink-0 {{ $isCurrent ? 'bg-white/15 text-white font-bold' : 'text-white/85 hover:text-white hover:bg-white/10' }}"
                               @if($item['external'] ?? false) target="_blank" rel="noopener" @endif>
                                {{ $label }}
                            </a>
                        @endif
                    @endforeach
                </div>

                @include('partials.navbars.portal-cta', ['navConfig' => $navConfig ?? []])
            </div>

            {{-- Mobile menu button --}}
            <button @click="open = !open"
                    class="lg:hidden p-2 rounded-xl text-white/90 hover:bg-white/10 transition focus:outline-none"
                    aria-label="Toggle navigation menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          :d="open ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16'"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile Drawer Navigation --}}
    <div x-show="open" x-cloak x-collapse class="lg:hidden border-t border-white/15 px-4 py-4 space-y-2 bg-slate-950/95 backdrop-blur-xl">
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
                            class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-sm font-bold text-slate-200 hover:bg-white/10">
                        <span>{{ $label }}</span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="subOpen ? 'rotate-180 text-amber-300' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="subOpen" x-cloak class="pl-4 space-y-1 mt-1 border-l-2 border-white/15">
                        @foreach($item['children'] as $child)
                            <a href="{{ $child['url'] }}"
                               class="block px-3 py-2 text-xs font-semibold text-slate-300 hover:text-amber-300 hover:bg-white/5 rounded-lg transition"
                               @if($child['external'] ?? false) target="_blank" rel="noopener" @endif>
                                {{ $child['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>
            @elseif($isCta)
                <a href="{{ $url }}"
                   class="block text-center px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm transition {{ Str::contains($url, ['login']) ? 'bg-white/15 text-white border border-white/20' : 'bg-gradient-to-r from-amber-400 to-amber-500 text-slate-950' }}"
                   @if($item['external'] ?? false) target="_blank" rel="noopener" @endif>
                    {{ $label }}
                </a>
            @else
                <a href="{{ $url }}"
                   class="block px-3 py-2.5 rounded-lg text-sm font-semibold text-slate-200 hover:bg-white/10 hover:text-white transition"
                   @if($item['external'] ?? false) target="_blank" rel="noopener" @endif>
                    {{ $label }}
                </a>
            @endif
        @endforeach

        @include('partials.navbars.portal-cta-mobile', ['navConfig' => $navConfig ?? []])
    </div>
</nav>
