@php
    $schools = \App\Support\SahodayaPublicData::memberSchools($tenant->id);
    $schoolCount = $schools->count();
    $districtCount = $schools->pluck('district')->filter()->unique()->count();
@endphp
<section class="py-14 px-4 sm:px-6 lg:px-8">
    <div class="max-w-5xl mx-auto text-center space-y-6">
        @if(!empty($config['eyebrow']))
        <span class="inline-block text-xs font-bold uppercase tracking-widest v2-badge-primary px-3.5 py-1.5 rounded-full">
            {{ $config['eyebrow'] }}
        </span>
        @endif

        <h2 class="text-3xl sm:text-4xl font-extrabold font-heading text-slate-900 tracking-tight">
            {{ $config['heading'] ?? 'Our member schools' }}
        </h2>

        @if(!empty($config['subheading']))
        <p class="text-base text-slate-600 max-w-2xl mx-auto leading-relaxed">{{ $config['subheading'] }}</p>
        @endif

        <div class="flex flex-wrap items-center justify-center gap-4 pt-2">
            <div class="v2-card px-8 py-5 min-w-[9rem]">
                <p class="text-3xl font-extrabold font-heading" style="color: var(--color-primary)">{{ $schoolCount }}</p>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mt-1">{{ $schoolCount === 1 ? 'Member School' : 'Member Schools' }}</p>
            </div>
            @if($districtCount > 0)
            <div class="v2-card px-8 py-5 min-w-[9rem]">
                <p class="text-3xl font-extrabold font-heading" style="color: var(--color-primary)">{{ $districtCount }}</p>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mt-1">{{ $districtCount === 1 ? 'District' : 'Districts' }} Covered</p>
            </div>
            @endif
        </div>

        <div class="pt-2">
            <a href="{{ $config['cta_url'] ?? '/member-schools' }}" class="v2-btn-primary inline-flex items-center gap-2 font-bold px-7 py-3.5 rounded-xl shadow-sm text-sm">
                {{ $config['cta_label'] ?? 'Find a Member School' }}
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </a>
        </div>
    </div>
</section>
