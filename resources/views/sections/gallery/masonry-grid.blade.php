@php
    $limit  = $config['albums_limit'] ?? 4;
    $albums = \App\Models\GalleryAlbum::with('items')
        ->where('tenant_id', $tenant->id)
        ->orderBy('display_order')->limit($limit)->get();
@endphp
@if($albums->isNotEmpty())
<section class="py-16 px-4 bg-white">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            @if(!empty($config['eyebrow']))
            <p class="text-sm font-semibold uppercase tracking-widest mb-1" style="color: var(--color-primary)">{{ $config['eyebrow'] }}</p>
            @endif
            <h2 class="text-3xl font-bold font-heading text-gray-900">{{ $config['heading'] ?? 'Gallery' }}</h2>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($albums as $i => $album)
            @php
                $images   = $album->items->pluck('image_path')->values()->all();
                $captions = $album->items->map(fn ($item) => $item->caption ?: $album->title)->values()->all();
                if (empty($images) && $album->cover_image) {
                    $images   = [$album->cover_image];
                    $captions = [$album->title];
                }
            @endphp
            <div class="relative group {{ $i === 0 ? 'sm:col-span-2 sm:row-span-2' : '' }}">
            <button type="button"
                    @if(!empty($images))
                    @click="$dispatch('lightbox-open', { images: {{ json_encode($images) }}, captions: {{ json_encode($captions) }}, index: 0 })"
                    @endif
                    class="relative rounded-xl overflow-hidden shadow-sm hover:shadow-lg transition text-left w-full"
                    aria-label="View {{ $album->title }} gallery">
                <div class="{{ $i === 0 ? 'aspect-square' : 'aspect-video' }} overflow-hidden bg-gray-100">
                    @if($album->cover_image)
                    <img loading="lazy" src="{{ $album->cover_image }}" alt="{{ $album->title }}"
                         class="w-full h-full object-cover group-hover:scale-110 transition duration-500">
                    @else
                    <div class="w-full h-full flex items-center justify-center text-gray-300">
                        <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                  d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    @endif
                </div>

                <div class="absolute inset-0 bg-black/0 group-hover:bg-black/40 transition flex items-end pointer-events-none">
                    <div class="p-4 translate-y-2 opacity-0 group-hover:translate-y-0 group-hover:opacity-100 transition">
                        <h3 class="text-white font-bold">{{ $album->title }}</h3>
                        @php $count = $album->items->count(); @endphp
                        @if($count)
                        <p class="text-white/80 text-sm">{{ $count }} photos</p>
                        @endif
                    </div>
                </div>
            </button>
            <a href="/gallery/{{ $album->slug }}"
               class="absolute bottom-3 right-3 z-10 text-xs font-semibold text-white bg-black/50 hover:bg-black/70 px-3 py-1.5 rounded-full opacity-0 group-hover:opacity-100 transition">
                View album
            </a>
            </div>
            @endforeach
        </div>

        @if(!empty($config['view_all_url']))
        <div class="text-center mt-8">
            <a href="{{ $config['view_all_url'] }}"
               class="inline-block font-semibold px-6 py-3 rounded-full border-2 transition hover:text-white"
               style="border-color: var(--color-primary); color: var(--color-primary);"
               onmouseover="this.style.backgroundColor='var(--color-primary)'"
               onmouseout="this.style.backgroundColor=''">
                View Full Gallery
            </a>
        </div>
        @endif
    </div>
</section>
@endif
