@php use App\Support\SahodayaPublicData; $years = SahodayaPublicData::academicYears($config); @endphp
<section id="academic" class="py-16 px-4 scroll-mt-24" x-data="{ tab: '{{ $years[0]['year'] ?? '2025-26' }}' }">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-8">
            <p class="text-sm font-bold uppercase tracking-wider text-purple-600 mb-1">Academic</p>
            <h2 class="font-heading text-3xl font-bold text-gray-900">{{ $config['heading'] ?? 'Programs & Results' }}</h2>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-xl overflow-hidden">
            <div class="flex gap-1 p-2 bg-gray-50 border-b overflow-x-auto">
                @foreach($years as $yearBlock)
                <button type="button" @click="tab = '{{ $yearBlock['year'] }}'"
                        :class="tab === '{{ $yearBlock['year'] }}' ? 'bg-white shadow font-bold text-purple-700' : 'text-gray-500'"
                        class="shrink-0 px-4 py-2 rounded-lg text-sm transition">{{ $yearBlock['year'] }}</button>
                @endforeach
            </div>
            <div class="p-4">
                @foreach($years as $yearBlock)
                <ul x-show="tab === '{{ $yearBlock['year'] }}'" x-cloak class="grid sm:grid-cols-2 gap-2">
                    @foreach($yearBlock['links'] ?? [] as $link)
                    <li>
                        <a href="{{ $link['url'] ?? '#' }}" class="flex items-center gap-3 p-3 rounded-xl hover:bg-purple-50 transition text-sm font-medium text-gray-700"
                           @if(str_starts_with($link['url'] ?? '', 'http')) target="_blank" rel="noopener" @endif>
                            <span>{{ $link['icon'] ?? '🔗' }}</span> {{ $link['label'] }}
                        </a>
                    </li>
                    @endforeach
                </ul>
                @endforeach
            </div>
        </div>
    </div>
</section>
