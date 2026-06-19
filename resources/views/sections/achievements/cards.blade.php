@php
    $limit = $config['limit'] ?? 8;
    $category = $config['category'] ?? null;
    $query = \App\Models\Achievement::where('tenant_id', $tenant->id);
    if ($category) $query->where('category', $category);
    $achievements = $query->orderBy('display_order')->limit($limit)->get();
@endphp
@if($achievements->isNotEmpty())
<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            @if(!empty($config['eyebrow']))
            <p class="text-sm font-semibold uppercase tracking-widest mb-1" style="color: var(--color-primary)">{{ $config['eyebrow'] }}</p>
            @endif
            <h2 class="text-3xl font-bold font-heading text-gray-900">{{ $config['heading'] ?? 'Achievements' }}</h2>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($achievements as $ach)
            <div class="bg-white rounded-2xl overflow-hidden shadow-sm hover:shadow-md transition group">
                @if($ach->image)
                <div class="aspect-video overflow-hidden">
                    <img loading="lazy" src="{{ $ach->image }}" alt="{{ $ach->title }}"
                         class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                </div>
                @else
                <div class="h-2 w-full" style="background-color: var(--color-primary)"></div>
                @endif
                <div class="p-5">
                    @if($ach->category)
                    <span class="text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full mb-3 inline-block"
                          style="background-color: color-mix(in srgb, var(--color-primary) 15%, transparent); color: var(--color-primary)">
                        {{ $ach->category }}
                    </span>
                    @endif
                    <h3 class="font-bold text-gray-900 mb-2">{{ $ach->title }}</h3>
                    @if($ach->description)
                    <p class="text-sm text-gray-500 line-clamp-2">{{ $ach->description }}</p>
                    @endif
                    @if($ach->level)
                    <div class="mt-3 flex items-center gap-1 text-xs text-gray-400">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138..."/>
                        </svg>
                        {{ $ach->level }}
                        @if($ach->achieved_at)
                        &nbsp;&bull;&nbsp; {{ \Carbon\Carbon::parse($ach->achieved_at)->format('Y') }}
                        @endif
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
