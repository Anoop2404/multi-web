@php
    $limit = $config['limit'] ?? 8;
    $albums = \App\Models\GalleryAlbum::where('tenant_id', $tenant->id)
        ->orderBy('display_order')->get();
    $items = \App\Models\GalleryItem::whereIn('album_id', $albums->pluck('id'))
        ->orderBy('display_order')->limit($limit)->get();
@endphp
@if($items->isNotEmpty())
<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-end justify-between mb-8">
            <div>
                @if(!empty($config['eyebrow']))
                <p class="text-sm font-semibold uppercase tracking-widest mb-1" style="color: var(--color-primary)">{{ $config['eyebrow'] }}</p>
                @endif
                <h2 class="text-3xl font-bold font-heading text-gray-900">{{ $config['heading'] ?? 'Moments Beyond Learning' }}</h2>
            </div>
            <a href="{{ $config['view_more_url'] ?? '/gallery' }}"
               class="hidden sm:inline-block font-semibold px-5 py-2.5 rounded-full border-2 hover:opacity-80 transition"
               style="border-color: var(--color-primary); color: var(--color-primary)">
                {{ $config['view_more_label'] ?? 'View full gallery' }}
            </a>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
            @foreach($items as $item)
            <figure class="aspect-square rounded-lg overflow-hidden shadow-sm">
                <img loading="lazy" src="{{ $item->image_path }}" alt="{{ $item->caption ?? '' }}"
                     class="w-full h-full object-cover">
            </figure>
            @endforeach
        </div>

        <div class="mt-6 text-center sm:hidden">
            <a href="{{ $config['view_more_url'] ?? '/gallery' }}"
               class="inline-block font-semibold px-5 py-2.5 rounded-full border-2"
               style="border-color: var(--color-primary); color: var(--color-primary)">
                {{ $config['view_more_label'] ?? 'View full gallery' }}
            </a>
        </div>
    </div>
</section>
@endif
