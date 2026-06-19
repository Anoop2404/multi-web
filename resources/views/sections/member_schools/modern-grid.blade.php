@php use App\Support\SahodayaPublicData; $schools = SahodayaPublicData::memberSchools($tenant->id); @endphp
@if($schools->isNotEmpty())
<section id="member-schools" class="py-16 px-4 scroll-mt-24">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            <h2 class="font-heading text-3xl font-bold text-gray-900">{{ $config['heading'] ?? 'Member Schools' }}</h2>
            <p class="text-gray-500 mt-2">{{ $schools->count() }} schools in our cluster</p>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
            @foreach($schools as $school)
            @php $sLogo = $school->getSetting('logo'); @endphp
            <div class="bg-white rounded-2xl border border-gray-100 p-4 text-center hover:shadow-md transition">
                @if($sLogo)<img src="{{ $sLogo }}" alt="" class="h-12 mx-auto mb-2 object-contain">@endif
                <p class="text-xs font-semibold text-gray-700">{{ $school->name }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
