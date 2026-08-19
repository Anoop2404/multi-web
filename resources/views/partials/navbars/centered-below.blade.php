<nav class="bg-white shadow-sm sticky top-0 z-50 border-b border-gray-100" x-data="{ open: false, activeSubmenu: null }">
    <div class="max-w-[1440px] mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Top Centered Logo --}}
        <div class="flex items-center justify-center py-3 sm:py-4">
            <a href="{{ $homeUrl ?? '/' }}" class="flex items-center gap-3 group">
                @if(!empty($logo))
                <img loading="lazy" src="{{ $logo }}" class="h-10 sm:h-12 w-auto shrink-0 group-hover:scale-105 transition-transform" alt="{{ $tenant->name }}">
                @else
                <span class="text-xl sm:text-2xl font-bold font-heading group-hover:opacity-90 transition-opacity" style="color: var(--color-primary, #4f46e5)">{{ $tenant->name ?? 'School' }}</span>
                @endif
            </a>
        </div>

        {{-- Centered Nav Row --}}
        <div class="hidden md:flex items-center justify-center gap-1.5 lg:gap-3 pb-3 border-t border-gray-100 pt-2.5 overflow-x-auto no-scrollbar">
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
                                class="px-3 py-1.5 text-xs lg:text-sm font-semibold rounded-lg transition flex items-center gap-1 whitespace-nowrap {{ $isCurrent ? 'bg-gray-100 text-gray-900 font-bold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}">
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
                       class="px-3 py-1.5 text-xs lg:text-sm font-semibold rounded-lg transition whitespace-nowrap shrink-0 {{ $isCurrent ? 'bg-gray-100 text-gray-900 font-bold' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-50' }}"
                       @if($item['external'] ?? false) target="_blank" rel="noopener" @endif>
                        {{ $label }}
                    </a>
                @endif
            @endforeach
        </div>

        {{-- Mobile toggle --}}
        <div class="md:hidden flex justify-end pb-3">
            <button @click="open = !open" class="p-2 text-gray-600 rounded-lg hover:bg-gray-100">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
    </div>

    {{-- Mobile menu drawer --}}
    <div x-show="open" x-cloak x-collapse class="md:hidden border-t px-4 py-3 space-y-1.5 bg-white">
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
                <a href="{{ $url }}" class="block px-3 py-2 rounded-lg text-sm font-semibold text-gray-700 hover:bg-gray-50 hover:text-gray-900">{{ $label }}</a>
            @endif
        @endforeach
    </div>
</nav>
