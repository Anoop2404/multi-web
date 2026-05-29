<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-4" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        @if(!empty($config['subheading']))
        <p class="text-center text-gray-500 mb-10 max-w-2xl mx-auto">{{ $config['subheading'] }}</p>
        @endif
        @if(!empty($config['members']) && is_array($config['members']))
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            @foreach($config['members'] as $member)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden text-center p-6 hover:shadow-md transition">
                @if(!empty($member['photo']))
                <img loading="lazy" src="{{ $member['photo'] }}" alt="{{ $member['name'] }}"
                     class="w-24 h-24 rounded-full mx-auto object-cover mb-4">
                @else
                <div class="w-24 h-24 rounded-full mx-auto bg-gray-100 flex items-center justify-center mb-4">
                    <span class="text-2xl text-gray-400 font-bold">{{ substr($member['name'] ?? '?', 0, 1) }}</span>
                </div>
                @endif
                <h3 class="font-bold font-heading text-gray-800">{{ $member['name'] }}</h3>
                <p class="text-sm" style="color: var(--color-primary)">{{ $member['designation'] ?? '' }}</p>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</section>