<nav class="bg-white shadow-sm sticky top-0 z-50 border-b border-gray-100" x-data="{ open: false, activeSubmenu: null }">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Top row: logo centered --}}
        <div class="flex items-center justify-center py-3 border-b border-gray-100">
            <a href="{{ $homeUrl ?? '/' }}" class="flex items-center gap-3 group">
                @if(isset($tenant) && !empty($logo = \App\Support\TenantBranding::logoUrl($tenant)))
                    <img loading="lazy" src="{{ $logo }}" class="h-12 sm:h-14 w-auto group-hover:scale-105 transition-transform" alt="{{ $tenant->name }}">
                @else
                    <div class="text-center">
                        <div class="text-xl sm:text-2xl font-bold font-heading" style="color: var(--color-primary, #4f46e5)">{{ $tenant->name ?? 'School' }}</div>
                    </div>
                @endif
            </a>
        </div>

        {{-- Bottom nav row --}}
        <div class="hidden md:flex items-center justify-center gap-1.5 lg:gap-3 h-12 overflow-x-auto no-scrollbar">
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
                                class="px-3 py-1.5 text-xs lg:text-sm font-semibold rounded-lg transition flex items-center gap-1 whitespace-nowrap text-gray-600 hover:text-gray-900 hover:bg-gray-50">
                            <span>{{ $label }}</span>
                            <svg class="w-3.5 h-3.5 shrink-0 transition-transform" :class="activeSubmenu === '{{ md5($label) }}' ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </button>
                        <div x-show="activeSubmenu === '{{ md5($label) }}'" x-cloak x-transition
                             class="absolute left-1/2 -translate-x-1/2 top-full pt-1.5 w-56 z-50">
                            <div class="bg-white rounded-xl shadow-xl border border-gray-100 py-2 overflow-hidden">
                                @foreach($item['children'] as $child)
                                <a href="{{ $child['url'] }}"
                                   class="block px-4 py-2.5 text-xs lg:text-sm font-medium text-gray-700 hover:bg-gray-50 hover:text-primary transition whitespace-nowrap"
                                   @if($child['external'] ?? false) target="_blank" rel="noopener" @endif>
                                    {{ $child['label'] }}
                                </a>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @elseif($isCta)
                    <a href="{{ $url }}"
                       class="px-3.5 py-1.5 text-xs lg:text-sm font-bold rounded-lg transition whitespace-nowrap shrink-0 shadow-sm flex items-center gap-1 {{ Str::contains($url, ['login']) ? 'bg-gray-100 text-gray-800 hover:bg-gray-200 border border-gray-300' : 'text-white' }}"
                       @if(!Str::contains($url, ['login'])) style="background-color: var(--color-primary, #4f46e5)" @endif
                       @if($item['external'] ?? false) target="_blank" rel="noopener" @endif>
                        <span>{{ $label }}</span>
                    </a>
                @else
                    <a href="{{ $url }}"
                       class="px-3 py-1.5 text-xs lg:text-sm font-semibold text-gray-600 hover:text-white hover:rounded-full transition-all whitespace-nowrap shrink-0"
                       onmouseover="this.style.backgroundColor='var(--color-primary, #4f46e5)'; this.style.color='white'"
                       onmouseout="this.style.backgroundColor=''; this.style.color=''">
                        {{ $label }}
                    </a>
                @endif
            @endforeach
        </div>

        {{-- Mobile toggle --}}
        <div class="md:hidden flex justify-end py-2">
            <button @click="open = !open" class="p-2 text-gray-600 rounded-lg hover:bg-gray-100">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          :d="open ? 'M6 18L18 6M6 6l12 12' : 'M4 6h16M4 12h16M4 18h16'"/>
                </svg>
            </button>
        </div>
    </div>

    <div x-show="open" x-collapse class="md:hidden border-t px-4 py-3 space-y-1.5 bg-white">
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
                            class="w-full flex items-center justify-between px-3 py-2 text-sm font-bold text-gray-700 rounded-lg hover:bg-gray-50">
                        <span>{{ $label }}</span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="subOpen ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="subOpen" x-cloak class="pl-4 space-y-1 mt-1 border-l-2 border-gray-100">
                        @foreach($item['children'] as $child)
                        <a href="{{ $child['url'] }}" class="block px-3 py-2 text-xs font-semibold text-gray-600 rounded-lg hover:bg-gray-50">{{ $child['label'] }}</a>
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
                <a href="{{ $url }}" class="block text-gray-700 py-2 text-sm font-semibold hover:text-primary transition">{{ $label }}</a>
            @endif
        @endforeach
    </div>
</nav>
