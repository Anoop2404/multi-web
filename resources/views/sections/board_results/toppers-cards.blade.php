@php
    $yearsToShow = (int) ($config['years'] ?? 1);
    $showClasses = $config['show_class'] ?? ['10', '12'];
    if (! is_array($showClasses)) {
        $showClasses = ['10', '12'];
    }
    $showClasses = array_map('intval', $showClasses);

    $query = \App\Models\BoardResult::where('tenant_id', $tenant->id)
        ->published()
        ->with(['toppers' => fn ($q) => $q->orderBy('rank')->orderByDesc('percentage')->limit(8)])
        ->orderByDesc('academic_year')
        ->orderByDesc('class');

    if ($showClasses !== []) {
        $query->whereIn('class', $showClasses);
    }

    $results = $query->get();
    if ($yearsToShow > 0) {
        $allowedYears = $results->pluck('academic_year')->unique()->take($yearsToShow)->all();
        $results = $results->whereIn('academic_year', $allowedYears)->values();
    }

    $toppers = $results->flatMap(fn ($r) => $r->toppers->map(fn ($t) => [
        'name' => $t->name,
        'photo' => $t->photo,
        'percentage' => $t->percentage,
        'class' => $r->class,
        'is_perfect_scorer' => $t->is_perfect_scorer,
    ]))->take(12);
@endphp
@if($toppers->isNotEmpty())
<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-10" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($toppers as $topper)
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 text-center hover:shadow-md transition">
                @if(!empty($topper['photo']))
                <img loading="lazy" src="{{ $topper['photo'] }}" alt="{{ $topper['name'] }}" class="w-20 h-20 rounded-full mx-auto object-cover mb-3">
                @endif
                <h3 class="font-bold font-heading text-gray-800">{{ $topper['name'] }}</h3>
                <p class="text-xs text-gray-500">Class {{ $topper['class'] ?? 'X' }}</p>
                <div class="mt-3 text-2xl font-bold" style="color: var(--color-primary)">{{ $topper['percentage'] ?? '' }}%</div>
                @if(!empty($topper['is_perfect_scorer']))
                <span class="inline-block mt-2 px-2 py-0.5 rounded-full text-xs font-semibold text-white" style="background-color: var(--color-secondary);">Perfect Scorer</span>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
