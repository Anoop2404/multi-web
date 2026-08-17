{{-- gallery/grid.blade.php — Interactive Photo & Event Media Gallery --}}
@php
    $galleryItems = collect($config['items'] ?? [])->filter(fn ($item) => !empty($item['image']));
    $categories = collect(['All'])->concat($galleryItems->pluck('category')->filter()->unique()->values());
@endphp

<section id="gallery" class="py-14 lg:py-20 px-4 sm:px-6 lg:px-8 bg-slate-900 text-white relative overflow-hidden"
         x-data="{ activeTab: 'All', activeLightboxImage: null, activeLightboxTitle: null }"
         style="background: linear-gradient(135deg, var(--color-primary) 0%, #0f172a 100%);">

    {{-- Background mesh texture --}}
    <div class="absolute inset-0 opacity-10 bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:24px_24px] pointer-events-none"></div>

    <div class="max-w-7xl mx-auto relative z-10">
        {{-- Section Header --}}
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-10">
            <div class="space-y-3">
                <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-widest bg-[color-mix(in_srgb,var(--color-accent)_20%,transparent)] text-accent px-3.5 py-1.5 rounded-full border border-[color-mix(in_srgb,var(--color-accent)_30%,transparent)] backdrop-blur-md">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0c-.693.04-1.346.42-1.736 1.039l-.821 1.316z"/><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z"/></svg>
                    <span>Media Showcase</span>
                </div>

                <h2 class="text-3xl sm:text-4xl font-extrabold font-heading text-white tracking-tight">
                    {{ $config['heading'] ?? 'Sahodaya Event Gallery' }}
                </h2>

                <p class="text-slate-300 text-sm sm:text-base max-w-2xl font-normal leading-relaxed">
                    Capturing moments of academic excellence, athletic spirit, and cultural vibrancy across {{ $tenant->name }}.
                </p>
            </div>

            @if($categories->count() > 1)
            {{-- Category Filter Tabs --}}
            <div class="flex flex-wrap items-center gap-2 bg-white/10 p-1.5 rounded-2xl border border-white/10 backdrop-blur-md">
                @foreach($categories as $cat)
                <button @click="activeTab = '{{ $cat }}'"
                        :class="activeTab === '{{ $cat }}' ? 'bg-accent text-white font-bold shadow-lg' : 'text-slate-300 hover:text-white font-medium'"
                        class="px-4 py-2 rounded-xl text-xs transition duration-200 cursor-pointer">
                    {{ $cat }}
                </button>
                @endforeach
            </div>
            @endif
        </div>

        @if($galleryItems->isEmpty())
        <div class="rounded-2xl border-2 border-dashed border-white/15 p-12 text-center bg-white/5">
            <svg class="w-12 h-12 text-slate-500 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 15.75l5.159-5.159a2.25 2.25 0 013.182 0l5.159 5.159m-1.5-1.5l1.409-1.409a2.25 2.25 0 013.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 001.5-1.5V6a1.5 1.5 0 00-1.5-1.5H3.75A1.5 1.5 0 002.25 6v12a1.5 1.5 0 001.5 1.5zm10.5-11.25h.008v.008h-.008V8.25zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
            <h3 class="text-base font-bold text-white">Gallery Coming Soon</h3>
            <p class="text-sm text-slate-400 mt-1">Event photos and highlights will appear here.</p>
        </div>
        @else
        {{-- Photo Cards Grid --}}
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($galleryItems as $item)
            <article x-show="activeTab === 'All' || activeTab === '{{ $item['category'] }}'"
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     class="group relative rounded-2xl overflow-hidden bg-slate-800 border border-white/10 shadow-xl cursor-pointer"
                     @click="activeLightboxImage = '{{ $item['image'] }}'; activeLightboxTitle = '{{ addslashes($item['title']) }}'">

                <div class="aspect-[4/3] w-full overflow-hidden relative">
                    <img loading="lazy" src="{{ $item['image'] }}" alt="{{ $item['title'] }}"
                         class="v2-media w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">

                    {{-- Dark Gradient Overlay --}}
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/90 via-slate-950/40 to-transparent opacity-80 group-hover:opacity-95 transition-opacity duration-300"></div>

                    {{-- Top Category Pill --}}
                    <div class="absolute top-3 left-3 z-10">
                        <span class="inline-block px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-900/80 backdrop-blur-md text-accent border border-[color-mix(in_srgb,var(--color-accent)_30%,transparent)]">
                            {{ $item['category'] }}
                        </span>
                    </div>

                    {{-- Zoom Icon Overlay --}}
                    <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-20">
                        <div class="w-12 h-12 rounded-full bg-accent text-white flex items-center justify-center shadow-xl transform group-hover:scale-110 transition-transform">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607zM10.5 7.5v6m3-3h-6"/></svg>
                        </div>
                    </div>

                    {{-- Card Bottom Info --}}
                    <div class="absolute bottom-0 inset-x-0 p-5 z-10 space-y-1">
                        <span class="text-[11px] font-semibold text-slate-300 uppercase tracking-wider">{{ $item['date'] }}</span>
                        <h3 class="font-heading font-bold text-white text-lg group-hover:text-[var(--color-accent)] transition-colors leading-snug">
                            {{ $item['title'] }}
                        </h3>
                        <p class="text-xs text-slate-300 line-clamp-2 leading-relaxed">
                            {{ $item['caption'] }}
                        </p>
                    </div>
                </div>
            </article>
            @endforeach
        </div>
        @endif

        {{-- Lightbox Modal --}}
        <div x-show="activeLightboxImage !== null"
             x-cloak
             @keydown.escape.window="activeLightboxImage = null"
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/90 backdrop-blur-xl p-4 sm:p-6">

            <div class="relative max-w-4xl w-full max-h-[90vh] flex flex-col items-center justify-center space-y-4">
                {{-- Close Button --}}
                <button @click="activeLightboxImage = null"
                        class="absolute -top-12 right-0 text-white hover:text-[var(--color-accent)] bg-white/10 hover:bg-white/20 p-2.5 rounded-full backdrop-blur-md transition cursor-pointer">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>

                {{-- Fullscreen Image --}}
                <img :src="activeLightboxImage" :alt="activeLightboxTitle"
                     class="v2-media max-h-[75vh] w-auto rounded-2xl shadow-2xl border border-white/20 object-contain">

                <h3 class="text-lg font-bold font-heading text-white text-center" x-text="activeLightboxTitle"></h3>
            </div>
        </div>
    </div>
</section>
