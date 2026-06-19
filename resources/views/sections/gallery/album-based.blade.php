@php
    $limit  = $config['albums_limit'] ?? 6;
    $albums = \App\Models\GalleryAlbum::with('items')
        ->where('tenant_id', $tenant->id)
        ->orderBy('display_order')->limit($limit)->get();
@endphp
@if($albums->isNotEmpty())
<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($albums as $album)
            @php
                $images   = $album->items->pluck('image_path')->values()->all();
                $captions = $album->items->map(fn ($item) => $item->caption ?: $album->title)->values()->all();
                if (empty($images) && $album->cover_image) {
                    $images   = [$album->cover_image];
                    $captions = [$album->title];
                }
            @endphp
            <a href="/gallery/{{ $album->slug }}"
               class="group block rounded-xl overflow-hidden shadow-sm border border-gray-100 bg-white hover:shadow-md transition">
                @if($album->cover_image)
                <div class="aspect-video overflow-hidden">
                    <img loading="lazy" src="{{ $album->cover_image }}" alt="{{ $album->title }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                </div>
                @endif
                <div class="p-4">
                    <h3 class="font-bold font-heading text-gray-800 group-hover:text-primary transition">{{ $album->title }}</h3>
                    @php $count = $album->items->count(); @endphp
                    @if($count)
                    <p class="text-sm text-gray-500 mt-1">{{ $count }} photos</p>
                    @endif
                </div>
            </a>
            @endforeach
        </div>
    </div>
</section>
@elseif(!empty($config['albums']) && is_array($config['albums']))
<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($config['albums'] as $album)
            @php
                $photos   = collect($album['photos'] ?? [])->filter()->values();
                $images   = $photos->all();
                $captions = $photos->map(fn () => $album['title'] ?? 'Gallery image')->all();
            @endphp
            <button type="button"
                    @if(!empty($images))
                    @click="$dispatch('lightbox-open', { images: {{ json_encode($images) }}, captions: {{ json_encode($captions) }}, index: 0 })"
                    @endif
                    class="group block rounded-xl overflow-hidden shadow-sm border border-gray-100 bg-white text-left w-full"
                    aria-label="View {{ $album['title'] ?? 'album' }}">
                @if(!empty($album['cover_image']))
                <div class="aspect-video overflow-hidden">
                    <img loading="lazy" src="{{ $album['cover_image'] }}" alt="{{ $album['title'] ?? 'Album cover' }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                </div>
                @endif
                <div class="p-4">
                    <h3 class="font-bold font-heading text-gray-800 group-hover:text-primary transition">{{ $album['title'] }}</h3>
                    @if(!empty($album['photo_count']))
                    <p class="text-sm text-gray-500 mt-1">{{ $album['photo_count'] }} photos</p>
                    @endif
                </div>
            </button>
            @endforeach
        </div>
    </div>
</section>
@endif
