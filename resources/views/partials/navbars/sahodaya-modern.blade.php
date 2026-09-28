<nav class="sticky top-0 z-50 border-b border-slate-200/70 bg-white/90 backdrop-blur-xl shadow-xs transition-all duration-200"
     x-data="{ open: false, activeSubmenu: null }">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Top Brand & Contact Row --}}
        <div class="flex items-center justify-between h-16 lg:h-18">
            <a href="{{ $homeUrl ?? '/' }}" class="flex items-center gap-3.5 min-w-0 group py-1">
                @if(!empty($logo))
                    <div class="h-11 w-11 sm:h-12 sm:w-12 rounded-xl bg-white border border-slate-200/80 shadow-2xs flex items-center justify-center p-1 shrink-0 group-hover:scale-105 group-hover:shadow-xs transition-all">
                        <img src="{{ $logo }}" alt="{{ $tenant->name }}" class="h-full w-full object-contain">
                    </div>
                @else
                    <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-indigo-600 to-indigo-800 text-white flex items-center justify-center font-bold shadow-xs shrink-0 group-hover:scale-105 transition-all" style="background-color: var(--color-primary, #4f46e5)">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.5M4.5 21V10.5"/>
                        </svg>
                    </div>
                @endif
                <div class="min-w-0">
                    <p class="font-heading font-extrabold text-sm sm:text-base text-slate-900 truncate leading-snug group-hover:text-indigo-600 transition-colors">{{ $tenant->name }}</p>
                    <div class="flex items-center gap-1.5 mt-0.5">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                        <p class="text-[10px] sm:text-[11px] text-slate-500 font-semibold tracking-wide uppercase">CBSE Sahodaya School Complex</p>
                    </div>
                </div>
            </a>

            <div class="hidden lg:flex items-center gap-3 shrink-0">
                @php $phone = \App\Support\SahodayaPublicData::contactPhone($tenant); @endphp
                @if($phone)
                <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}"
                   class="inline-flex items-center gap-2 text-xs xl:text-sm font-bold px-3.5 py-1.5 rounded-full text-white shadow-xs hover:shadow transition-all hover:scale-105"
                   style="background: linear-gradient(135deg, var(--color-primary, #4f46e5), var(--color-secondary, #7c3aed));">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <span>{{ $phone }}</span>
                </a>
                @endif
                @include('partials.navbars.portal-cta', ['navConfig' => $navConfig ?? []])
            </div>

            <button @click="open = !open" class="lg:hidden p-2.5 rounded-xl border border-slate-200 text-slate-700 hover:bg-slate-100 transition" aria-label="Menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          :d="open ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16'"/>
                </svg>
            </button>
        </div>

        {{-- Primary Navigation Row --}}
        <div class="hidden lg:flex items-center gap-1 xl:gap-1.5 pb-2.5 -mt-0.5 flex-wrap">
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
                            class="px-3.5 py-1.5 text-xs xl:text-[13px] font-semibold rounded-lg transition-all flex items-center gap-1.5 whitespace-nowrap {{ $isCurrent ? 'bg-indigo-50 text-indigo-950 font-bold border border-indigo-200/80 shadow-2xs' : 'text-slate-700 hover:text-slate-950 hover:bg-slate-100' }}">
                        <span>{{ $label }}</span>
                        <svg class="w-3.5 h-3.5 shrink-0 transition-transform duration-200 text-slate-400" :class="activeSubmenu === '{{ md5($label) }}' ? 'rotate-180 text-slate-900' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div :class="activeSubmenu === '{{ md5($label) }}' ? 'opacity-100 visible translate-y-0' : 'opacity-0 invisible -translate-y-1 pointer-events-none'"
                         class="absolute left-0 top-full pt-1.5 w-64 z-50 transition duration-150 ease-out">
                        <div class="bg-white rounded-2xl shadow-xl border border-slate-100 p-2 overflow-hidden ring-1 ring-black/5">
                            @foreach($item['children'] as $child)
                            <a href="{{ $child['url'] }}"
                               class="flex items-center justify-between px-3.5 py-2.5 text-xs xl:text-sm font-semibold text-slate-700 hover:bg-slate-50 hover:text-indigo-600 rounded-xl transition whitespace-nowrap"
                               @if($child['external'] ?? false) target="_blank" rel="noopener" @endif>
                                <span>{{ $child['label'] }}</span>
                                <svg class="w-3 h-3 text-slate-400 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                            </a>
                            @endforeach
                        </div>
                    </div>
                </div>
                @elseif($isCta)
                <a href="{{ $url }}"
                   class="px-3.5 py-1.5 text-xs xl:text-[13px] font-bold rounded-lg transition whitespace-nowrap shrink-0 shadow-2xs flex items-center gap-1.5 {{ Str::contains($url, ['login']) ? 'bg-slate-100 text-slate-800 hover:bg-slate-200 border border-slate-300/80' : 'text-white' }}"
                   @if(!Str::contains($url, ['login'])) style="background-color: var(--color-primary, #4f46e5)" @endif
                   @if($item['external'] ?? false) target="_blank" rel="noopener" @endif>
                    <span>{{ $label }}</span>
                </a>
                @else
                <a href="{{ $url }}"
                   class="px-3.5 py-1.5 text-xs xl:text-[13px] font-semibold rounded-lg transition-all whitespace-nowrap shrink-0 {{ $isCurrent ? 'bg-indigo-50 text-indigo-950 font-bold border border-indigo-200/80 shadow-2xs' : 'text-slate-700 hover:text-slate-950 hover:bg-slate-100' }}"
                   @if($item['external'] ?? false) target="_blank" rel="noopener" @endif>
                    {{ $label }}
                </a>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Mobile Menu --}}
    <div x-show="open" x-cloak x-collapse class="lg:hidden border-t border-slate-200 bg-white/98 backdrop-blur-xl px-4 py-5 space-y-2 shadow-2xl">
        @foreach($items as $item)
            @php
                $label = $item['label'] ?? '';
                $url = $item['url'] ?? '#';
                $isCta = in_array(strtolower($label), ['school registration', 'school login', 'register', 'login']) 
                      || Str::contains($url, ['/school-register', '/school-login', '/login']);
            @endphp
            @if(!empty($item['children']))
                <div x-data="{ subOpen: false }" class="bg-slate-50/70 rounded-xl p-1.5 border border-slate-100">
                    <button @click="subOpen = !subOpen" type="button"
                            class="w-full flex items-center justify-between px-3 py-2 text-sm font-bold text-slate-800 rounded-lg hover:bg-white transition">
                        <span>{{ $label }}</span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="subOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="subOpen" x-cloak class="pl-3 pr-2 space-y-1 mt-1 border-l-2 border-indigo-200 ml-3">
                        @foreach($item['children'] as $child)
                        <a href="{{ $child['url'] }}" class="block px-3 py-2 text-xs font-semibold text-slate-600 rounded-lg hover:bg-white hover:text-indigo-600 transition">{{ $child['label'] }}</a>
                        @endforeach
                    </div>
                </div>
            @elseif($isCta)
                <a href="{{ $url }}"
                   class="block text-center px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm transition {{ Str::contains($url, ['login']) ? 'bg-slate-100 text-slate-900 border border-slate-300' : 'text-white' }}"
                   @if(!Str::contains($url, ['login'])) style="background-color: var(--color-primary, #4f46e5)" @endif>
                    {{ $label }}
                </a>
            @else
                <a href="{{ $url }}" class="block px-3.5 py-2.5 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-100 hover:text-slate-950 transition">{{ $label }}</a>
            @endif
        @endforeach
        <div class="pt-2 border-t border-slate-100">
            @include('partials.navbars.portal-cta-mobile', ['navConfig' => $navConfig ?? []])
        </div>
    </div>
</nav>
