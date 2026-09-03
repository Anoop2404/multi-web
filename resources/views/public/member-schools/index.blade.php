@extends('layouts.public')

@section('content')
<section class="py-12 px-4" x-data="{ query: '', district: '' }">
    <div class="max-w-6xl mx-auto">
        <a href="/" class="inline-flex items-center gap-1 text-sm font-semibold mb-8 hover:underline" style="color: var(--color-primary)">
            &larr; Back to home
        </a>

        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
            <div>
                <h1 class="text-3xl md:text-4xl font-bold font-heading text-gray-900">Member Schools</h1>
                <p class="text-gray-500 mt-1">{{ $schools->count() }} {{ $schools->count() === 1 ? 'active member school' : 'active member schools' }}</p>
            </div>
        </div>

        @if($schools->isNotEmpty())
        <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm mb-8 flex flex-col sm:flex-row gap-3" role="search">
            <div class="relative flex-1">
                <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/></svg>
                </div>
                <input x-model.debounce.150ms="query" type="search" placeholder="Search by school name or location..."
                       class="w-full pl-11 pr-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 placeholder:text-slate-400 focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/10 transition">
            </div>
            @if($districts->isNotEmpty())
            <select x-model="district" class="px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm font-medium text-slate-900 focus:bg-white focus:border-primary focus:ring-2 focus:ring-primary/10 transition">
                <option value="">All districts</option>
                @foreach($districts as $d)
                <option value="{{ strtolower($d) }}">{{ $d }}</option>
                @endforeach
            </select>
            @endif
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach($schools as $school)
            @php
                $location = $school->city ?? $school->district ?? '';
                $district = strtolower($school->district ?? '');
                $logo = \App\Support\TenantBranding::logoUrl($school);
            @endphp
            <article x-show="(query === '' || '{{ strtolower(addslashes($school->name.' '.$location)) }}'.includes(query.toLowerCase())) && (district === '' || district === '{{ addslashes($district) }}')"
                     class="v2-card p-5 flex flex-col gap-3">
                <div class="flex items-start justify-between gap-3">
                    <div class="w-12 h-12 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center shrink-0 overflow-hidden">
                        @if($logo)
                        <img loading="lazy" src="{{ $logo }}" alt="{{ $school->name }}" class="w-10 h-10 object-contain">
                        @else
                        <span class="text-lg font-bold font-heading text-primary">{{ substr($school->name, 0, 1) }}</span>
                        @endif
                    </div>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[11px] font-bold uppercase tracking-wider v2-badge-primary">
                        {{ $school->school_type ?? 'Member School' }}
                    </span>
                </div>
                <div>
                    <h3 class="font-heading font-bold text-slate-900 text-base leading-snug">{{ $school->name }}</h3>
                    @if($location)
                    <div class="flex items-center gap-1.5 text-xs text-slate-500 mt-1">
                        <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/></svg>
                        <span>{{ $location }}</span>
                    </div>
                    @endif
                </div>
            </article>
            @endforeach
        </div>
        @else
        <p class="text-gray-500 text-center py-12">Member schools will be listed here once added.</p>
        @endif
    </div>
</section>
@endsection
