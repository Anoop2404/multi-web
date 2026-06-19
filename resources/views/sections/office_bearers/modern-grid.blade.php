@php
    use App\Support\SahodayaPublicData;
    $bearers = SahodayaPublicData::officeBearers($tenant->id);
@endphp
<section id="office-bearers" class="py-16 lg:py-20 px-4 scroll-mt-24">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            <p class="text-sm font-bold uppercase tracking-wider text-purple-600 mb-1">Leadership</p>
            <h2 class="font-heading text-3xl font-bold text-gray-900">{{ $config['heading'] ?? 'Office Bearers' }}</h2>
        </div>
        @if($bearers->isNotEmpty())
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
            @foreach($bearers as $bearer)
            <article class="bg-white rounded-2xl border border-gray-100 p-6 text-center shadow-sm hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
                @if($bearer->photo)
                <img src="{{ str_starts_with($bearer->photo, 'http') ? $bearer->photo : asset('storage/'.$bearer->photo) }}"
                     alt="{{ $bearer->name }}" class="w-20 h-20 rounded-2xl mx-auto object-cover ring-4 ring-purple-50 mb-4">
                @else
                <div class="w-20 h-20 rounded-2xl mx-auto mb-4 flex items-center justify-center text-white text-2xl font-bold"
                     style="background: linear-gradient(135deg, var(--color-primary), var(--color-secondary));">
                    {{ strtoupper(substr($bearer->name, 0, 1)) }}
                </div>
                @endif
                <p class="text-xs font-bold uppercase tracking-wider text-purple-600">{{ $bearer->role }}</p>
                <h3 class="font-heading font-bold text-gray-900 mt-1">{{ $bearer->name }}</h3>
                @if($bearer->school_name)<p class="text-xs text-gray-500 mt-2">{{ $bearer->school_name }}</p>@endif
                @if($bearer->phone)<p class="text-xs text-gray-600 mt-2">{{ $bearer->phone }}</p>@endif
            </article>
            @endforeach
        </div>
        @endif
    </div>
</section>
