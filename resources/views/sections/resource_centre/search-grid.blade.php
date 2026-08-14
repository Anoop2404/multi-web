@php
    $resources = \App\Support\SahodayaPublicData::resources($tenant->id, 40);
    $years = $resources->pluck('year')->unique()->values();
@endphp
<section id="resource-centre" class="px-4" x-data="{ query: '', year: 'all', category: 'all' }" aria-labelledby="resource-centre-heading">
    <div class="max-w-7xl mx-auto">
        <p class="text-xs font-bold uppercase tracking-[.2em] text-primary mb-2">Knowledge centre</p>
        <h2 id="resource-centre-heading" class="text-3xl md:text-4xl font-bold font-heading text-gray-950">{{ $config['heading'] ?? 'Resource centre' }}</h2>
        <p class="mt-2 text-gray-600 max-w-2xl">{{ $config['subheading'] ?? 'Search circulars and downloads.' }}</p>
        <div class="grid md:grid-cols-[1fr_auto_auto] gap-3 my-8" role="search">
            <label class="sr-only" for="resource-query">Search resources</label>
            <input id="resource-query" x-model.debounce.150ms="query" type="search" placeholder="Search by title or category" class="w-full rounded-xl border-gray-300 focus:border-primary focus:ring-primary">
            <label class="sr-only" for="resource-year">Filter by year</label>
            <select id="resource-year" x-model="year" class="rounded-xl border-gray-300 focus:border-primary focus:ring-primary"><option value="all">All years</option>@foreach($years as $year)<option value="{{ $year }}">{{ $year }}</option>@endforeach</select>
            <label class="sr-only" for="resource-category">Filter by category</label>
            <select id="resource-category" x-model="category" class="rounded-xl border-gray-300 focus:border-primary focus:ring-primary"><option value="all">All categories</option>@foreach($resources->pluck('category')->unique() as $category)<option value="{{ strtolower($category) }}">{{ $category }}</option>@endforeach</select>
        </div>
        @if($resources->isEmpty())
            <div class="rounded-2xl border border-dashed border-gray-300 p-10 text-center text-gray-600">No resources have been published yet.</div>
        @else
            <div class="grid md:grid-cols-2 gap-3">
                @foreach($resources as $resource)
                    <a href="{{ $resource['url'] }}" target="_blank" rel="noopener" x-show="(query === '' || '{{ strtolower(addslashes($resource['title'].' '.$resource['category'])) }}'.includes(query.toLowerCase())) && (year === 'all' || year === '{{ $resource['year'] }}') && (category === 'all' || category === '{{ strtolower($resource['category']) }}')" class="flex items-start justify-between gap-5 rounded-xl border border-gray-200 bg-white p-4 hover:border-primary hover:shadow-sm transition">
                        <span><strong class="block text-gray-950">{{ $resource['title'] }}</strong><span class="mt-1 block text-xs text-gray-500">{{ $resource['category'] }} · {{ $resource['date'] }}@if(!empty($resource['size'])) · {{ $resource['size'] }}@endif</span></span>
                        <span class="shrink-0 text-[11px] font-bold rounded bg-gray-100 px-2 py-1 text-gray-600">{{ $resource['type'] }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>
