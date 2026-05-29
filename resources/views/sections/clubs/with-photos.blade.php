<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['clubs']) && is_array($config['clubs']))
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
            @foreach($config['clubs'] as $club)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition">
                @if(!empty($club['photo']))
                <img loading="lazy" src="{{ $club['photo'] }}" alt="{{ $club['name'] }}" class="w-full h-40 object-cover">
                @endif
                <div class="p-5">
                    <h3 class="font-bold font-heading text-gray-800">{{ $club['name'] }}</h3>
                    <p class="text-sm text-gray-600 mt-1">{{ $club['description'] ?? '' }}</p>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>