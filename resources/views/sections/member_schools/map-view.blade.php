@php
    $rawSchools = collect($config['schools'] ?? [])->filter(fn ($school) => !empty($school['name']));
    if ($rawSchools->isEmpty() && isset($tenant)) {
        $rawSchools = \App\Support\SahodayaPublicData::memberSchools($tenant->id)->map(fn ($school) => [
            'name' => $school->name,
            'location' => $school->city ?? $school->district,
            'district' => $school->district,
            'type' => $school->school_type ?? 'Member School',
            'logo' => \App\Support\TenantBranding::logoUrl($school),
        ]);
    }

    $schools = $rawSchools;
    $districts = $schools->pluck('district')->filter()->unique()->sort()->values();
@endphp
<section class="py-12 lg:py-16 px-4 sm:px-6 lg:px-8" x-data="{ query: '' }" aria-labelledby="member-directory-heading">
    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-6 mb-8">
            <div class="space-y-2">
                @if(!empty($config['eyebrow']))
                <span class="inline-block text-xs font-bold uppercase tracking-widest v2-badge-primary px-3.5 py-1.5 rounded-full mb-1">
                    {{ $config['eyebrow'] }}
                </span>
                @endif
                <h2 id="member-directory-heading" class="text-3xl md:text-4xl font-extrabold font-heading text-slate-900 tracking-tight">
                    {{ $config['heading'] ?? 'Find a Member School' }}
                </h2>
                @if(!empty($config['subheading']))
                <p class="text-base text-slate-600 max-w-2xl font-normal leading-relaxed">
                    {{ $config['subheading'] }}
                </p>
                @endif
            </div>

            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 bg-slate-100 px-3.5 py-2 rounded-lg shrink-0">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>{{ $rawSchools->count() }} Active Member School{{ $rawSchools->count() === 1 ? '' : 's' }}</span>
            </div>
        </div>

        @if(!empty($config['map_embed']))
        <div class="rounded-2xl overflow-hidden shadow-md border border-slate-200 mb-8 bg-white">
            {!! \App\Support\HtmlSanitizer::embed($config['map_embed'] ?? '') !!}
        </div>
        @endif

        @if($schools->isNotEmpty())
        {{-- Search only, per request — no extra filter chrome --}}
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm mb-8" role="search">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                </div>
                <input id="school-query" x-model.debounce.150ms="query" type="search" placeholder="Search by school name, location or city..." class="w-full pl-11 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 placeholder:text-slate-400 focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/10 transition duration-150">
            </div>
        </div>

        {{-- Cards Grid --}}
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($schools as $school)
            <article x-show="query === '' || '{{ strtolower(addslashes(($school['name'] ?? '').' '.($school['location'] ?? ''))) }}'.includes(query.toLowerCase())"
                     class="v2-card p-5 flex flex-col justify-between group">
                <div class="space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div class="w-12 h-12 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center shrink-0 overflow-hidden group-hover:scale-105 transition-transform duration-200">
                            @if(!empty($school['logo']))
                            <img loading="lazy" src="{{ $school['logo'] }}" alt="{{ $school['name'] ?? 'School logo' }}" class="w-10 h-10 object-contain">
                            @else
                            <span class="text-lg font-bold font-heading text-primary">{{ substr($school['name'], 0, 1) }}</span>
                            @endif
                        </div>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider v2-badge-primary">
                            {{ $school['type'] ?? 'Member School' }}
                        </span>
                    </div>

                    <div>
                        <h3 class="font-heading font-bold text-slate-900 text-base leading-snug group-hover:text-primary transition-colors">
                            {{ $school['name'] }}
                        </h3>
                        @if(!empty($school['location']))
                        <div class="flex items-center gap-1.5 text-xs text-slate-500 mt-1">
                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                            <span>{{ $school['location'] }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="pt-4 mt-4 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                    <span class="font-medium text-slate-600">{{ $school['district'] ?? $tenant->region->name ?? '' }}</span>
                    <a href="/m/v2/member-schools" class="text-primary font-bold group-hover:translate-x-0.5 transition-transform inline-flex items-center gap-1">
                        <span>View Details</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                    </a>
                </div>
            </article>
            @endforeach
        </div>
        @else
        <div class="rounded-2xl border-2 border-dashed border-slate-200 p-12 text-center bg-slate-50">
            <svg class="w-12 h-12 text-slate-400 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.5H4.5V21"/></svg>
            <h3 class="text-base font-bold text-slate-800">Member Directory Initializing</h3>
            <p class="text-sm text-slate-500 mt-1">Affiliated member schools will be listed here shortly.</p>
        </div>
        @endif
    </div>
</section>
