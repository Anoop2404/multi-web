{{-- Top Micro Announcement Ticker Bar --}}
<div class="bg-slate-950 text-white text-[11px] font-semibold py-1.5 px-4 border-b border-white/10 hidden sm:block">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
        <div class="flex items-center gap-2 truncate">
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-accent text-white">
                <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                Urgent Circular
            </span>
            <span class="text-slate-300 truncate">CBSE Affiliation Renewal Guidelines 2025-26 & Kalotsav Event Schedule Published</span>
        </div>
        <div class="flex items-center gap-4 shrink-0 text-slate-300 text-[10px]">
            <span class="flex items-center gap-1">
                <svg class="w-3 h-3 text-accent" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582"/></svg>
                80+ CBSE Schools
            </span>
            <a href="/m/v2/contact" class="hover:text-[var(--color-accent)] font-bold transition">Secretariat Helpdesk</a>
        </div>
    </div>
</div>

<nav class="bg-white/95 backdrop-blur-md shadow-sm sticky top-0 z-50 border-b border-slate-200" x-data="{ open: false }">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
        <a href="{{ $homeUrl ?? '/' }}" class="flex items-center gap-3 min-w-0 group">
            @if(!empty($logo))
                <img loading="lazy" src="{{ $logo }}" class="h-10 w-auto shrink-0 group-hover:scale-105 transition-transform" alt="{{ $tenant->name ?? 'Sahodaya' }}">
            @endif
            <div class="min-w-0">
                <p class="font-heading font-extrabold text-base sm:text-lg text-slate-900 leading-tight tracking-tight truncate group-hover:text-primary transition-colors">{{ $tenant->name ?? 'Sahodaya' }}</p>
                <p class="text-[10px] font-bold text-primary uppercase tracking-widest leading-none hidden sm:block">CBSE Sahodaya Network</p>
            </div>
        </a>

        <div class="hidden lg:flex items-center gap-4 min-w-0">
            <div class="flex items-center gap-4 nav-scroll">
                @foreach($items as $item)
                    @php
                        $isCurrent = request()->url() === url($item['url']);
                    @endphp
                    <a href="{{ $item['url'] }}"
                       class="text-xs font-bold transition-colors py-1.5 border-b-2 whitespace-nowrap {{ $isCurrent ? 'text-primary border-primary font-extrabold' : 'text-slate-600 border-transparent hover:text-primary hover:border-slate-300' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
            @include('partials.navbars.portal-cta', ['navConfig' => $navConfig ?? []])
        </div>

        <button @click="open = !open" class="lg:hidden p-2 rounded-lg text-slate-700 hover:bg-slate-100 transition">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
    </div>

    <div x-show="open" x-cloak class="lg:hidden border-t border-slate-200 px-4 py-3 space-y-1 bg-white">
        @foreach($items as $item)
            @php
                $isCurrent = request()->url() === url($item['url']);
            @endphp
            <a href="{{ $item['url'] }}"
               class="block px-3 py-2 rounded-lg text-sm font-bold transition-colors {{ $isCurrent ? 'bg-slate-100 text-primary' : 'text-slate-700 hover:bg-slate-50 hover:text-primary' }}">
                {{ $item['label'] }}
            </a>
        @endforeach
        @include('partials.navbars.portal-cta-mobile', ['navConfig' => $navConfig ?? []])
    </div>
</nav>
