<nav class="sticky top-0 z-50 border-b border-white/10 bg-white/80 backdrop-blur-lg shadow-sm"
     x-data="{ open: false, academicOpen: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 lg:h-[4.25rem]">
            <a href="/" class="flex items-center gap-3 min-w-0">
                @if(!empty($logo))
                    <img src="{{ $logo }}" alt="{{ $tenant->name }}" class="h-10 w-auto shrink-0">
                @endif
                <div class="min-w-0">
                    <p class="font-heading font-bold text-sm sm:text-base text-gray-900 truncate leading-tight">{{ $tenant->name }}</p>
                    <p class="text-[10px] sm:text-xs text-gray-500 hidden sm:block">CBSE Sahodaya School Complex</p>
                </div>
            </a>

            <div class="hidden lg:flex items-center gap-3 shrink-0">
                @php $phone = \App\Support\SahodayaPublicData::contactPhone($tenant); @endphp
                @if($phone)
                <a href="tel:{{ preg_replace('/\s+/', '', $phone) }}"
                   class="inline-flex items-center gap-2 text-sm font-semibold px-4 py-2 rounded-full text-white"
                   style="background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    {{ $phone }}
                </a>
                @endif
                @include('partials.navbars.portal-cta', ['navConfig' => $navConfig ?? []])
            </div>

            <button @click="open = !open" class="lg:hidden p-2 rounded-lg hover:bg-gray-100" aria-label="Menu">
                <svg class="w-6 h-6 text-gray-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>

        <div class="hidden lg:flex items-center gap-1 pb-3 -mt-1">
            @foreach($items as $item)
                @if(!empty($item['children']))
                <div class="relative" @mouseenter="academicOpen = true" @mouseleave="academicOpen = false">
                    <button type="button"
                            class="px-4 py-2 text-sm font-semibold text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-50 transition flex items-center gap-1">
                        {{ $item['label'] }}
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="academicOpen" x-cloak x-transition
                         class="absolute left-0 top-full pt-1 w-56">
                        <div class="bg-white rounded-xl shadow-xl border border-gray-100 py-2 overflow-hidden">
                            @foreach($item['children'] as $child)
                            <a href="{{ $child['url'] }}"
                               class="block px-4 py-2.5 text-sm text-gray-700 hover:bg-purple-50 hover:text-purple-700 transition"
                               @if($child['external'] ?? false) target="_blank" rel="noopener" @endif>
                                {{ $child['label'] }}
                            </a>
                            @endforeach
                        </div>
                    </div>
                </div>
                @else
                <a href="{{ $item['url'] }}"
                   class="px-4 py-2 text-sm font-semibold text-gray-600 hover:text-gray-900 rounded-lg hover:bg-gray-50 transition"
                   @if($item['external'] ?? false) target="_blank" rel="noopener" @endif>
                    {{ $item['label'] }}
                </a>
                @endif
            @endforeach
        </div>
    </div>

    <div x-show="open" x-cloak x-transition class="lg:hidden border-t bg-white px-4 py-4 space-y-1">
        @foreach($items as $item)
            @if(!empty($item['children']))
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider px-2 pt-2">{{ $item['label'] }}</p>
                @foreach($item['children'] as $child)
                <a href="{{ $child['url'] }}" class="block px-4 py-2 text-sm text-gray-700 rounded-lg hover:bg-gray-50">{{ $child['label'] }}</a>
                @endforeach
            @else
                <a href="{{ $item['url'] }}" class="block px-4 py-2 text-sm font-semibold text-gray-700 rounded-lg hover:bg-gray-50">{{ $item['label'] }}</a>
            @endif
        @endforeach
        @include('partials.navbars.portal-cta-mobile', ['navConfig' => $navConfig ?? []])
    </div>
</nav>
