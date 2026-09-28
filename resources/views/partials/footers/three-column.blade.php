<footer class="text-slate-300 mt-20 relative overflow-hidden bg-slate-950 border-t border-slate-800" style="background-color: var(--color-primary, #0f172a)">
    <div class="absolute inset-0 bg-gradient-to-b from-black/20 to-black/60 pointer-events-none"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 grid md:grid-cols-3 gap-10 lg:gap-12">
        {{-- Brand / Sahodaya Identity --}}
        <div class="space-y-4">
            @if(!empty($logo = \App\Support\TenantBranding::logoUrl($tenant)))
            <div class="inline-flex items-center gap-3 bg-white/95 rounded-2xl p-2.5 shadow-sm">
                <img loading="lazy" src="{{ $logo }}" class="h-11 w-auto max-w-[160px] object-contain block" alt="{{ $tenant->name }}">
            </div>
            @else
            <h3 class="text-white font-extrabold text-xl font-heading tracking-tight">{{ $tenant->name ?? 'Sahodaya Complex' }}</h3>
            @endif

            <p class="text-sm text-slate-400 leading-relaxed max-w-sm">{{ $content['tagline'] ?? 'Fostering excellence and collaboration across affiliated institutions.' }}</p>

            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/5 border border-white/10 text-xs font-medium text-slate-300">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Affiliated CBSE Sahodaya Complex</span>
            </div>

            @if(isset($content['sahodaya_link']))
                <a href="{{ $content['sahodaya_link']['url'] }}" class="text-sm text-indigo-300 font-semibold hover:text-white transition-colors block">
                    {{ $content['sahodaya_link']['label'] ?? 'Sahodaya Cluster' }} →
                </a>
            @endif
        </div>

        {{-- Quick Links --}}
        @if(!empty($content['quick_links']))
        <div>
            <h3 class="site-footer-heading text-white font-bold text-sm uppercase tracking-wider mb-4 pb-1 border-b border-white/10 inline-block">{{ $content['quick_links_heading'] ?? 'Quick Links' }}</h3>
            <ul class="space-y-2.5 text-sm">
                @foreach($content['quick_links'] as $link)
                    <li>
                        <a href="{{ $link['url'] }}" class="text-slate-400 hover:text-white hover:translate-x-1 transition-all inline-flex items-center gap-1.5">
                            <span class="text-slate-600 text-xs">›</span>
                            <span>{{ $link['label'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Contact Details --}}
        @if(!empty($content['address']) || !empty($content['phone']) || !empty($content['email']))
        <div>
            <h3 class="site-footer-heading text-white font-bold text-sm uppercase tracking-wider mb-4 pb-1 border-b border-white/10 inline-block">{{ $content['contact_heading'] ?? 'Get In Touch' }}</h3>
            <address class="text-sm not-italic space-y-3 text-slate-300">
                @if(!empty($content['address']))
                <div class="flex items-start gap-3">
                    <svg class="w-5 h-5 text-indigo-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span class="leading-relaxed">{{ $content['address'] }}</span>
                </div>
                @endif
                @if(!empty($content['phone']))
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <a href="tel:{{ preg_replace('/\s+/', '', $content['phone']) }}" class="hover:text-white transition">{{ $content['phone'] }}</a>
                </div>
                @endif
                @if(!empty($content['email']))
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <a href="mailto:{{ $content['email'] }}" class="hover:text-white transition">{{ $content['email'] }}</a>
                </div>
                @endif
            </address>
        </div>
        @endif
    </div>

    {{-- Bottom Bar --}}
    <div class="relative border-t border-white/10 px-4 py-6 bg-black/30">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-400 text-center sm:text-left">
            <p>{{ $content['copyright'] ?? '© ' . date('Y') . ' ' . ($tenant->name ?? 'School') . '. All rights reserved.' }}</p>
            <p class="text-slate-500">CBSE Sahodaya Complex Management Platform</p>
        </div>
    </div>
</footer>
