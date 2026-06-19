@php use App\Support\SahodayaPublicData; $programmes = SahodayaPublicData::programmes($config); @endphp
<section id="programmes" class="py-16 px-4 bg-gray-50 scroll-mt-24">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            <p class="text-sm font-bold uppercase tracking-wider text-purple-600 mb-1">{{ $config['eyebrow'] ?? 'What We Do' }}</p>
            <h2 class="font-heading text-3xl font-bold text-gray-900">{{ $config['heading'] ?? 'Programmes & Services' }}</h2>
            @if(!empty($config['subheading']))
            <p class="text-gray-500 mt-2 max-w-2xl mx-auto">{{ $config['subheading'] }}</p>
            @endif
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($programmes as $prog)
            <a href="{{ $prog['url'] ?? '#' }}"
               class="group bg-white rounded-2xl border border-gray-100 p-6 hover:shadow-lg hover:border-purple-100 transition-all"
               @if(str_starts_with($prog['url'] ?? '', 'http')) target="_blank" rel="noopener" @endif>
                <span class="text-3xl mb-4 block group-hover:scale-110 transition">{{ $prog['icon'] ?? '📌' }}</span>
                <h3 class="font-heading font-bold text-gray-900 group-hover:text-purple-700 transition">{{ $prog['label'] }}</h3>
                @if(!empty($prog['description']))
                <p class="text-sm text-gray-500 mt-2">{{ $prog['description'] }}</p>
                @endif
            </a>
            @endforeach
        </div>
    </div>
</section>
