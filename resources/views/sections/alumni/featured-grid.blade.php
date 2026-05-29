@php
    $limit  = $config['limit'] ?? 8;
    $alumni = \App\Models\Alumni::where('tenant_id', $tenant->id)
        ->where('is_featured', true)->where('is_approved', true)
        ->orderByDesc('batch_year')->limit($limit)->get();
@endphp
@if($alumni->isNotEmpty())
<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            @if(!empty($config['eyebrow']))
            <p class="text-sm font-semibold uppercase tracking-widest mb-1" style="color: var(--color-primary)">{{ $config['eyebrow'] }}</p>
            @endif
            <h2 class="text-3xl font-bold font-heading text-gray-900">{{ $config['heading'] ?? 'Notable Alumni' }}</h2>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($alumni as $al)
            <div class="bg-white rounded-2xl p-6 text-center shadow-sm hover:shadow-md transition">
                @if($al->photo)
                <div class="w-24 h-24 mx-auto rounded-full overflow-hidden border-4 border-gray-100 mb-4">
                    <img loading="lazy" src="{{ $al->photo }}" alt="{{ $al->name }}" class="w-full h-full object-cover">
                </div>
                @else
                <div class="w-24 h-24 mx-auto rounded-full flex items-center justify-center text-white text-3xl font-bold mb-4"
                     style="background-color: var(--color-primary)">
                    {{ strtoupper(substr($al->name, 0, 1)) }}
                </div>
                @endif
                <h3 class="font-bold text-gray-900">{{ $al->name }}</h3>
                <p class="text-xs text-gray-400 mb-2">Batch of {{ $al->batch_year }}</p>
                @if($al->current_role || $al->current_organisation)
                <p class="text-sm font-medium" style="color: var(--color-primary)">{{ $al->current_role }}</p>
                <p class="text-xs text-gray-500">{{ $al->current_organisation }}</p>
                @endif
                @if($al->message)
                <p class="text-xs text-gray-400 italic mt-3 line-clamp-2">"{{ $al->message }}"</p>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
