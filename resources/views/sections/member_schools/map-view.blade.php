@php
    $schools = collect($config['schools'] ?? [])->filter(fn ($school) => !empty($school['name']));
    if ($schools->isEmpty() && isset($tenant)) {
        $schools = \App\Support\SahodayaPublicData::memberSchools($tenant->id)->map(fn ($school) => [
            'name' => $school->name,
            'location' => $school->city ?? $school->district ?? '',
            'district' => $school->district ?? $school->city ?? 'Other',
            'type' => $school->school_type ?? 'Member school',
            'logo' => \App\Support\TenantBranding::logoUrl($school),
        ]);
    }
    $districts = $schools->pluck('district')->filter()->unique()->sort()->values();
@endphp
<section class="py-16 px-4" x-data="{ query: '', district: 'all' }" aria-labelledby="member-directory-heading">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 id="member-directory-heading" class="text-3xl md:text-4xl font-bold font-heading mb-3" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @if(!empty($config['subheading']))<p class="text-gray-600 max-w-2xl mb-7">{{ $config['subheading'] }}</p>@endif
        @endif
        @if(!empty($config['map_embed']))
        <div class="rounded-xl overflow-hidden shadow-lg mb-8">{!! \App\Support\HtmlSanitizer::embed($config['map_embed'] ?? '') !!}</div>
        @endif
        @if($schools->isNotEmpty())
        <div class="grid sm:grid-cols-[1fr_auto] gap-3 mb-6" role="search">
            <label class="sr-only" for="school-query">Search member schools</label>
            <input id="school-query" x-model.debounce.150ms="query" type="search" placeholder="Search school or location" class="rounded-xl border-gray-300 focus:border-primary focus:ring-primary">
            <label class="sr-only" for="school-district">Filter by district</label>
            <select id="school-district" x-model="district" class="rounded-xl border-gray-300 focus:border-primary focus:ring-primary"><option value="all">All districts</option>@foreach($districts as $district)<option value="{{ strtolower($district) }}">{{ $district }}</option>@endforeach</select>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($schools as $school)
            <article x-show="(query === '' || '{{ strtolower(addslashes(($school['name'] ?? '').' '.($school['location'] ?? ''))) }}'.includes(query.toLowerCase())) && (district === 'all' || district === '{{ strtolower($school['district'] ?? 'other') }}')" class="flex items-center gap-3 p-4 rounded-lg bg-white shadow-sm border border-gray-100">
                @if(!empty($school['logo']))
                <img loading="lazy" src="{{ $school['logo'] }}" alt="{{ $school['name'] ?? 'School logo' }}" class="w-10 h-10 rounded object-contain">
                @endif
                <div class="min-w-0">
                    <p class="font-medium text-sm text-gray-800">{{ $school['name'] }}</p>
                    <p class="text-xs text-gray-500">{{ $school['location'] ?? '' }}</p>
                    <p class="text-[11px] mt-1 font-semibold text-primary">{{ $school['type'] ?? 'Member school' }}</p>
                </div>
            </article>
            @endforeach
        </div>
        @else
            <div class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-600">The member directory will appear here once schools are published.</div>
        @endif
    </div>
</section>
