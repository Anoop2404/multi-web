<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['bearers']) && is_array($config['bearers']))
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach($config['bearers'] as $bearer)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center hover:shadow-md transition">
                @if(!empty($bearer['photo']))
                <img loading="lazy" src="{{ $bearer['photo'] }}" alt="{{ $bearer['name'] }}" class="w-24 h-24 rounded-full mx-auto object-cover mb-4">
                @endif
                <h3 class="font-bold font-heading text-gray-800">{{ $bearer['name'] }}</h3>
                <p class="text-sm" style="color: var(--color-primary)">{{ $bearer['role'] ?? '' }}</p>
                @if(!empty($bearer['term_from']) || !empty($bearer['term_to']))
                <p class="text-xs text-gray-500 mt-2">Term: {{ $bearer['term_from'] ?? '' }} - {{ $bearer['term_to'] ?? 'Present' }}</p>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>