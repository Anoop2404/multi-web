@php use App\Support\SahodayaPublicData; $links = SahodayaPublicData::usefulLinks($config); @endphp
<section id="useful-links" class="py-16 px-4 bg-gray-50 scroll-mt-24">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            <h2 class="font-heading text-3xl font-bold text-gray-900">{{ $config['heading'] ?? 'Useful Links' }}</h2>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($links as $link)
            <a href="{{ $link['url'] }}" target="_blank" rel="noopener"
               class="flex items-center gap-4 bg-white rounded-2xl p-5 border border-gray-100 hover:shadow-lg hover:border-purple-100 transition">
                <span class="text-3xl">{{ $link['icon'] ?? '🔗' }}</span>
                <span class="font-semibold text-gray-800">{{ $link['label'] }}</span>
            </a>
            @endforeach
        </div>
    </div>
</section>
