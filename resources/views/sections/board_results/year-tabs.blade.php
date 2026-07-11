@php
    $results = \App\Models\BoardResult::where('tenant_id', $tenant->id)
        ->published()
        ->orderByDesc('academic_year')
        ->orderBy('class')
        ->get();

    $years = $results->groupBy('academic_year')->map(function ($group, $year) {
        return [
            'key' => (string) $year,
            'label' => (string) $year,
            'results' => $group->map(fn ($r) => [
                'class' => $r->class,
                'examination_type' => $r->examination_type,
                'pass_percent' => $r->pass_percent,
                'total_count' => $r->total_appeared,
                'pass_count' => $r->pass_count,
                'distinctions' => $r->distinctions,
                'highest_mark' => $r->highest_mark,
                'average_mark' => $r->average_mark,
            ])->values()->all(),
        ];
    })->values();

    // Prefer DB-built years; fall back to CMS config only when it already embeds published-shaped data.
    if ($years->isEmpty() && ! empty($config['years']) && is_array($config['years'])) {
        $years = collect($config['years']);
    }

    $activeYear = $years->first()['key'] ?? '';
@endphp
@if($years->isNotEmpty())
<section class="py-16 px-4" x-data="{ activeYear: '{{ $activeYear }}' }">
    <div class="max-w-5xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-4" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        <div class="flex flex-wrap justify-center gap-2 mb-8">
            @foreach($years as $year)
            <button @click="activeYear = '{{ $year['key'] }}'"
                    class="px-5 py-2 rounded-lg text-sm font-medium transition"
                    :class="activeYear === '{{ $year['key'] }}' ? 'text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    x-bind:style="activeYear === '{{ $year['key'] }}' ? 'background-color: var(--color-primary);' : ''">
                {{ $year['label'] }}
            </button>
            @endforeach
        </div>
        <div>
            @foreach($years as $year)
            <div x-show="activeYear === '{{ $year['key'] }}'" x-cloak>
                @if(!empty($year['results']) && is_array($year['results']))
                <div class="grid md:grid-cols-2 gap-6">
                    @foreach($year['results'] as $result)
                    <div class="bg-white rounded-xl p-6 shadow-sm border border-gray-100">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="font-bold font-heading text-gray-800">
                                Class {{ $result['class'] ?? 'X' }}
                                @if(!empty($result['examination_type']))
                                <span class="text-xs font-medium text-gray-400">{{ $result['examination_type'] }}</span>
                                @endif
                            </h3>
                            <span class="text-2xl font-bold" style="color: var(--color-primary)">{{ $result['pass_percent'] ?? $result['percentage'] ?? '' }}%</span>
                        </div>
                        <div class="text-sm text-gray-500 space-y-1">
                            <p>Total Students: {{ $result['total_count'] ?? $result['total'] ?? '' }}</p>
                            <p>Passed: {{ $result['pass_count'] ?? $result['passed'] ?? '' }}</p>
                            @if(!empty($result['distinctions']))
                            <p>Distinctions: {{ $result['distinctions'] }}</p>
                            @endif
                            @if(!empty($result['highest_mark']))
                            <p>Highest mark: {{ $result['highest_mark'] }}</p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
