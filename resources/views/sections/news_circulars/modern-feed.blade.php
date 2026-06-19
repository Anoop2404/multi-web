@php use App\Support\SahodayaPublicData; $items = SahodayaPublicData::announcements($tenant->id, $config, 8); @endphp
@if($items->isNotEmpty())
<section class="py-12 px-4">
    <div class="max-w-7xl mx-auto">
        <h2 class="font-heading text-2xl font-bold text-gray-900 mb-6">{{ $config['heading'] ?? 'Latest Updates' }}</h2>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($items as $item)
            <a href="{{ $item->url }}" class="block bg-white rounded-2xl border border-gray-100 p-5 hover:shadow-md hover:border-purple-100 transition group">
                <span class="text-[10px] font-bold uppercase tracking-wider text-purple-600">{{ $item->badge }}</span>
                @if($item->date)<p class="text-xs text-gray-400 mt-1">{{ $item->date }}</p>@endif
                <h3 class="font-semibold text-gray-800 mt-2 group-hover:text-purple-700 transition line-clamp-2">{{ $item->title }}</h3>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif
