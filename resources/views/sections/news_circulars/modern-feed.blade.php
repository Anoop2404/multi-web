@php use App\Support\SahodayaPublicData; $items = SahodayaPublicData::announcements($tenant->id, $config, 8); @endphp
<section class="py-12 lg:py-16 px-4 sm:px-6 lg:px-8 bg-slate-50 border-t border-slate-200">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
            <div>
                <span class="inline-block text-xs font-bold uppercase tracking-widest v2-badge-primary px-3.5 py-1 rounded-full mb-1">
                    Official Announcements
                </span>
                <h2 class="font-heading text-3xl font-extrabold text-slate-900 tracking-tight">
                    {{ $config['heading'] ?? 'Latest Updates & Circulars' }}
                </h2>
            </div>
            
            <a href="/m/v2/announcements" class="v2-btn-primary inline-flex items-center gap-1.5 text-xs font-bold px-4 py-2.5 rounded-xl shadow-sm transition">
                <span>View All Circulars</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
            </a>
        </div>

        @if($items->isNotEmpty())
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
            @foreach($items as $item)
            <a href="{{ $item->url ?? '#' }}" class="v2-card p-5 flex flex-col justify-between group">
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider v2-badge-primary">
                            <svg class="w-3 h-3 text-primary" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                            <span>{{ $item->badge ?? 'Circular' }}</span>
                        </span>
                        @if(!empty($item->date))
                        <span class="text-[11px] font-medium text-slate-400">
                            {{ $item->date }}
                        </span>
                        @endif
                    </div>

                    <h3 class="font-heading font-bold text-slate-900 text-sm leading-snug group-hover:text-primary transition-colors line-clamp-3">
                        {{ $item->title }}
                    </h3>
                </div>

                <div class="pt-4 mt-3 border-t border-slate-100 flex items-center justify-between text-xs font-bold text-primary">
                    <span class="inline-flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                        Download Document
                    </span>
                    <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
                </div>
            </a>
            @endforeach
        </div>
        @else
        <div class="rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center bg-white">
            <svg class="w-12 h-12 text-slate-400 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
            <h3 class="text-base font-bold text-slate-800">No Circulars Published Yet</h3>
            <p class="text-sm text-slate-500 mt-1">Official announcements and circulars will appear here.</p>
        </div>
        @endif
    </div>
</section>
