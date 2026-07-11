@php
    $year  = $config['academic_year'] ?? null;
    $class = $config['class'] ?? null; // 10 or 12

    $query = \App\Models\BoardResult::where('tenant_id', $tenant->id)
        ->published();
    if ($year)  $query->where('academic_year', $year);
    if ($class) $query->where('class', $class);

    $results = $query->with(['toppers' => fn($q) => $q->orderByDesc('percentage')->limit(6)])
                     ->orderByDesc('academic_year')
                     ->get();
@endphp
@if($results->isNotEmpty())
<section class="py-16 px-4 bg-white">
    <div class="max-w-7xl mx-auto">
        <div class="text-center mb-10">
            @if(!empty($config['eyebrow']))
            <p class="text-sm font-semibold uppercase tracking-widest mb-1" style="color: var(--color-primary)">{{ $config['eyebrow'] }}</p>
            @endif
            <h2 class="text-3xl font-bold font-heading text-gray-900">{{ $config['heading'] ?? 'Board Results' }}</h2>
        </div>

        @foreach($results as $result)
        <div class="mb-14">
            <h3 class="text-xl font-bold text-gray-800 mb-6 flex items-center gap-3">
                <span class="px-3 py-1 rounded-full text-sm text-white" style="background-color: var(--color-primary)">
                    Class {{ $result->class }}
                </span>
                {{ $result->academic_year }}
                @if($result->examination_type)
                <span class="text-sm font-medium text-gray-500">{{ $result->examination_type }}</span>
                @endif
            </h3>

            {{-- Stats --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
                @foreach([
                    ['label' => 'Appeared',    'value' => $result->total_appeared],
                    ['label' => 'Pass %',       'value' => $result->pass_percent . '%'],
                    ['label' => 'Distinctions','value' => $result->distinctions],
                    ['label' => 'First Class', 'value' => $result->first_class],
                ] as $stat)
                <div class="text-center bg-gray-50 rounded-2xl p-5">
                    <div class="text-3xl font-bold font-heading" style="color: var(--color-primary)">{{ $stat['value'] }}</div>
                    <div class="text-sm text-gray-500 mt-1">{{ $stat['label'] }}</div>
                </div>
                @endforeach
            </div>

            {{-- Toppers --}}
            @if($result->toppers->isNotEmpty())
            <div>
                <h4 class="font-semibold text-gray-700 mb-4">Top Scorers</h4>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                    @foreach($result->toppers as $i => $topper)
                    <div class="text-center bg-gray-50 rounded-xl p-4">
                        @if($topper->photo)
                        <div class="w-16 h-16 mx-auto rounded-full overflow-hidden border-2 mb-2"
                             style="border-color: var(--color-primary)">
                            <img loading="lazy" src="{{ $topper->photo }}" alt="{{ $topper->name }}"
                                 class="w-full h-full object-cover">
                        </div>
                        @else
                        <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center text-white font-bold text-xl mb-2"
                             style="background-color: var(--color-primary)">
                            {{ strtoupper(substr($topper->name, 0, 1)) }}
                        </div>
                        @endif
                        @if($i < 3)
                        <div class="text-lg mb-1">{{ ['🥇','🥈','🥉'][$i] }}</div>
                        @endif
                        <p class="font-bold text-gray-900 text-sm">{{ $topper->name }}</p>
                        <p class="text-xs font-semibold mt-1" style="color: var(--color-primary)">{{ $topper->percentage }}%</p>
                        @if($topper->is_perfect_scorer)
                        <span class="text-xs text-amber-600 font-medium">Perfect Score!</span>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        @endforeach
    </div>
</section>
@endif
