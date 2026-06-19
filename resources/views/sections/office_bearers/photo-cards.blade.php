@php
    use App\Support\SahodayaPublicData;
    $bearers = SahodayaPublicData::officeBearers($tenant->id);
    if ($bearers->isEmpty() && !empty($config['bearers']) && is_array($config['bearers'])) {
        $bearers = collect($config['bearers'])->map(fn ($b) => (object) $b);
    }
@endphp
<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if($bearers->isNotEmpty())
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach($bearers as $bearer)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center hover:shadow-md transition">
                @if(!empty($bearer->photo ?? $bearer['photo'] ?? null))
                <img loading="lazy" src="{{ $bearer->photo ?? $bearer['photo'] }}" alt="{{ $bearer->name ?? $bearer['name'] }}" class="w-24 h-24 rounded-full mx-auto object-cover mb-4">
                @endif
                <h3 class="font-bold font-heading text-gray-800">{{ $bearer->name ?? $bearer['name'] }}</h3>
                <p class="text-sm" style="color: var(--color-primary)">{{ $bearer->role ?? $bearer['role'] ?? '' }}</p>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>