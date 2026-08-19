@php
    $limit = $config['limit'] ?? 8;
    $achievements = \App\Models\Achievement::where('tenant_id', $tenant->id)
        ->orderBy('display_order')->limit($limit)->get();
@endphp
@if($achievements->isNotEmpty())
<section class="py-16 px-4 bg-white">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-end justify-between mb-8">
            <div>
                @if(!empty($config['eyebrow']))
                <p class="text-sm font-semibold uppercase tracking-widest mb-1" style="color: var(--color-primary)">{{ $config['eyebrow'] }}</p>
                @endif
                <h2 class="text-3xl font-bold font-heading text-gray-900">{{ $config['heading'] ?? 'Recent Achievements' }}</h2>
            </div>
        </div>

        <div class="flex gap-4 overflow-x-auto pb-2" style="scrollbar-width: thin;">
            @foreach($achievements as $ach)
            <div class="shrink-0 w-[250px]">
                @if($ach->image)
                <img loading="lazy" src="{{ $ach->image }}" alt="{{ $ach->title }}"
                     class="w-[250px] h-[350px] object-cover rounded-xl shadow-sm">
                @else
                <div class="w-[250px] h-[350px] rounded-xl flex items-center justify-center text-white text-center p-6"
                     style="background-color: var(--color-primary)">
                    {{ $ach->title }}
                </div>
                @endif
                @if($ach->image)
                <p class="mt-3 text-sm font-semibold text-gray-800 line-clamp-2">{{ $ach->title }}</p>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
