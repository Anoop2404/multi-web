@php
    // Member schools are child tenants of this sahodaya
    $schools = \App\Models\Tenant::where('parent_id', $tenant->id)
        ->where('is_active', true)
        ->orderBy('name')
        ->get();
@endphp
@if($schools->isNotEmpty())
<section class="py-16 px-4 bg-white">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            @if(!empty($config['eyebrow']))
            <p class="text-sm font-semibold uppercase tracking-widest mb-1" style="color: var(--color-primary)">{{ $config['eyebrow'] }}</p>
            @endif
            <h2 class="text-3xl font-bold font-heading text-gray-900">{{ $config['heading'] ?? 'Member Schools' }}</h2>
            <p class="text-gray-500 mt-2">{{ $schools->count() }} schools in our cluster</p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            @foreach($schools as $school)
            @php
                $logo = $school->getSetting('logo');
                $url  = $school->domain ? 'https://' . $school->domain : '#';
            @endphp
            <a href="{{ $url }}" target="_blank" rel="noopener"
               class="flex flex-col items-center gap-3 p-4 rounded-xl border border-gray-100
                      hover:border-primary/30 hover:shadow-sm transition group"
               style="--primary: var(--color-primary)">
                @if($logo)
                <img loading="lazy" src="{{ $logo }}" alt="{{ $school->name }}"
                     class="h-14 w-auto object-contain">
                @else
                <div class="h-14 w-14 rounded-full flex items-center justify-center text-white font-bold text-xl"
                     style="background-color: var(--color-primary)">
                    {{ strtoupper(substr($school->name, 0, 1)) }}
                </div>
                @endif
                <p class="text-xs text-center text-gray-600 font-medium leading-snug group-hover:text-gray-900 transition">
                    {{ $school->name }}
                </p>
            </a>
            @endforeach
        </div>
    </div>
</section>
@endif
