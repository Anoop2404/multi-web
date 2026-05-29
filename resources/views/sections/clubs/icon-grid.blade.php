<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['clubs']) && is_array($config['clubs']))
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            @foreach($config['clubs'] as $club)
            <div class="bg-white rounded-xl p-5 shadow-sm border border-gray-100 text-center hover:shadow-md transition group">
                @if(!empty($club['icon']))
                <div class="text-3xl mb-2">{{ $club['icon'] }}</div>
                @endif
                <h3 class="font-semibold text-sm text-gray-800 group-hover:text-primary transition">{{ $club['name'] }}</h3>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>