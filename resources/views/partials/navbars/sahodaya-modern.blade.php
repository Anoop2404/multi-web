<nav class="sticky top-0 z-50 border-b border-gray-200/80 bg-white/95 backdrop-blur-lg shadow-sm"
     x-data="{ open: false, activeSubmenu: null }">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Top Brand & Contact Row --}}
        <div class="flex items-center justify-between h-16 lg:h-[4.25rem]">
            <a href="{{ $homeUrl ?? '/' }}" class="flex items-center gap-3 min-w-0 group">
                @if(!empty($logo))
                    <img src="{{ $logo }}" alt="{{ $tenant->name }}" class="h-10 w-auto max-w-[140px] shrink-0 object-contain group-hover:scale-105 transition-transform">
                @else
                    <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-bold shadow-sm shrink-0" style="background-color: var(--color-primary, #4f46e5)">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.5M4.5 21V10.5"/>
                        </svg>
                    </div>
                @endif
                <div class="min-w-0">
                    <p class="font-heading font-bold text-sm sm:text-base text-gray-900 truncate leading-tight group-hover:text-primary transition-colors">{{ $tenant->name }}</p>
                    <p class="text-[10px] sm:text-xs text-gray-500 font-medium hidden sm:block">CBSE Sahodaya School Complex</p>
                </div>
            </a>

            <div class="hidden lg:flex items-center gap-3 shrink-0">
                @php $phone = \App\Support\SahodayaPublicData::contactPhone($tenant); @endphp
                @if($phone)
                <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}"
                   class="inline-flex items-center gap-2 text-xs xl:text-sm font-semibold px-3.5 py-1.5 rounded-full text-white shadow-sm hover:opacity-95 transition"
                   style="background: linear-gradient(135deg, var(--color-primary, #4f46e5), var(--color-secondary, #7c3aed));">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <span>{{ $phone }}</span>
                </a>
                @endif
                @include('partials.navbars.portal-cta', ['navConfig' => $navConfig ?? []])
            </div>

            <button @click="open = !open" class="lg:hidden p-2 rounded-xl text-gray-700 hover:bg-gray-100 transition" aria-label="Menu">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          :d="open ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16'"/>
                </svg>
            </button>
        </div>

        {{-- Primary Navigation Row --}}
        {{-- No overflow-x here on purpose: any overflow-x value forces the browser to also
             clip overflow-y (a CSS overflow spec quirk), which silently hides every
             dropdown's flyout panel — see logo-left.blade.php for the same fix.
             Text/gap size bumps are deferred to 2xl (1536px), not xl (1280px), for the
             same reason as logo-left.blade.php: bumping right at 1280 widens the row
             exactly where it has the least slack to spare. --}}
        <div class="hidden lg:flex items-center gap-1 2xl:gap-2 pb-2.5 -mt-0.5 flex-wrap">
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
                            class="px-3 py-1.5 text-xs 2xl:text-sm font-semibold rounded-lg transition flex items-center gap-1.5 whitespace-nowrap {{ $isCurrent ? 'bg-gray-100 text-gray-900 font-bold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100/70' }}">
                        <span>{{ $label }}</span>
                        <svg class="w-3.5 h-3.5 shrink-0 transition-transform duration-200" :class="activeSubmenu === '{{ md5($label) }}' ? 'rotate-180 text-gray-900' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="activeSubmenu === '{{ md5($label) }}'"
                         x-cloak x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-100"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 translate-y-1"
                         class="absolute left-0 top-full pt-1.5 w-60 z-50">
                        <div class="bg-white rounded-xl shadow-xl border border-gray-100 py-2 overflow-hidden">
                            @foreach($item['children'] as $child)
                            <a href="{{ $child['url'] }}"
                               class="block px-4 py-2.5 text-xs xl:text-sm font-medium text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition whitespace-nowrap"
                               @if($child['external'] ?? false) target="_blank" rel="noopener" @endif>
                                {{ $child['label'] }}
                            </a>
                            @endforeach
                        </div>
                    </div>
                </div>
                @elseif($isCta)
                <a href="{{ $url }}"
                   class="px-3.5 py-1.5 text-xs 2xl:text-sm font-bold rounded-lg transition whitespace-nowrap shrink-0 shadow-sm flex items-center gap-1.5 {{ Str::contains($url, ['login']) ? 'bg-gray-100 text-gray-800 hover:bg-gray-200 border border-gray-300/80' : 'text-white' }}"
                   @if(!Str::contains($url, ['login'])) style="background-color: var(--color-primary, #4f46e5)" @endif
                   @if($item['external'] ?? false) target="_blank" rel="noopener" @endif>
                    <span>{{ $label }}</span>
                </a>
                @else
                <a href="{{ $url }}"
                   class="px-3 py-1.5 text-xs 2xl:text-sm font-semibold rounded-lg transition whitespace-nowrap shrink-0 {{ $isCurrent ? 'bg-gray-100 text-gray-900 font-bold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100/70' }}"
                   @if($item['external'] ?? false) target="_blank" rel="noopener" @endif>
                    {{ $label }}
                </a>
                @endif
            @endforeach
        </div>
    </div>

    {{-- Mobile Menu --}}
    <div x-show="open" x-cloak x-collapse class="lg:hidden border-t bg-white px-4 py-4 space-y-1.5 shadow-lg">
        @foreach($items as $item)
            @php
                $label = $item['label'] ?? '';
                $url = $item['url'] ?? '#';
                $isCta = in_array(strtolower($label), ['school registration', 'school login', 'register', 'login']) 
                      || Str::contains($url, ['/school-register', '/school-login', '/login']);
            @endphp
            @if(!empty($item['children']))
                <div x-data="{ subOpen: false }">
                    <button @click="subOpen = !subOpen" type="button"
                            class="w-full flex items-center justify-between px-3 py-2 text-sm font-bold text-gray-700 rounded-lg hover:bg-gray-50">
                        <span>{{ $label }}</span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="subOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="subOpen" x-cloak class="pl-4 space-y-1 mt-1 border-l-2 border-gray-100">
                        @foreach($item['children'] as $child)
                        <a href="{{ $child['url'] }}" class="block px-3 py-2 text-xs font-semibold text-gray-600 rounded-lg hover:bg-gray-50 hover:text-gray-900">{{ $child['label'] }}</a>
                        @endforeach
                    </div>
                </div>
            @elseif($isCta)
                <a href="{{ $url }}"
                   class="block text-center px-4 py-2.5 rounded-xl text-sm font-bold shadow-sm transition {{ Str::contains($url, ['login']) ? 'bg-gray-100 text-gray-800 border border-gray-300' : 'text-white' }}"
                   @if(!Str::contains($url, ['login'])) style="background-color: var(--color-primary, #4f46e5)" @endif>
                    {{ $label }}
                </a>
            @else
                <a href="{{ $url }}" class="block px-3 py-2 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50 hover:text-gray-900">{{ $label }}</a>
            @endif
        @endforeach
        @include('partials.navbars.portal-cta-mobile', ['navConfig' => $navConfig ?? []])
    </div>
</nav>
