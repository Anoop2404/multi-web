@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-5xl mx-auto">
        <a href="/" class="inline-flex items-center gap-1 text-sm font-semibold mb-8 hover:underline" style="color: var(--color-primary)">
            &larr; Back to home
        </a>

        <div class="mb-10">
            <h1 class="text-3xl md:text-4xl font-bold font-heading text-gray-900">Board Examination Results</h1>
            <p class="text-gray-500 mt-2">Academic year {{ $year }} — Class X (AISSE) &amp; Class XII (AISSCE)</p>
        </div>

        @forelse($results as $result)
        <div class="mb-14">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-6">
                <h2 class="text-xl font-bold text-gray-800 flex items-center gap-3">
                    <span class="px-3 py-1 rounded-full text-sm text-white" style="background-color: var(--color-primary)">
                        Class {{ $result->class }}
                    </span>
                    {{ $result->academic_year }}
                    @if($result->examination_type)
                    <span class="text-sm font-medium text-gray-500">{{ $result->examination_type }}</span>
                    @endif
                </h2>
                @if($result->result_pdf_path)
                <a href="{{ url('/results/'.$result->id.'/download') }}"
                   class="inline-flex items-center gap-1.5 text-sm font-semibold px-4 py-2 rounded-full border transition hover:opacity-90"
                   style="border-color: var(--color-primary); color: var(--color-primary)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                    Download Full Result (PDF)
                </a>
                @endif
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
                @foreach([
                    ['label' => 'Appeared', 'value' => $result->total_appeared],
                    ['label' => 'Pass %', 'value' => $result->pass_percent !== null ? $result->pass_percent.'%' : '—'],
                    ['label' => 'Distinctions', 'value' => $result->distinctions],
                    ['label' => 'First Class', 'value' => $result->first_class],
                ] as $stat)
                <div class="text-center bg-gray-50 rounded-2xl p-5">
                    <div class="text-3xl font-bold font-heading" style="color: var(--color-primary)">{{ $stat['value'] ?? '—' }}</div>
                    <div class="text-sm text-gray-500 mt-1">{{ $stat['label'] }}</div>
                </div>
                @endforeach
            </div>

            @if($result->toppers->isNotEmpty())
            @php $streamGroups = $result->toppers->groupBy(fn ($t) => $t->examStream->label ?? null); @endphp
            <div class="space-y-8">
                @foreach($streamGroups as $streamLabel => $streamToppers)
                <div>
                    <h3 class="font-semibold text-gray-700 mb-4">{{ $streamLabel ?: 'Top Scorers' }}</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                        @foreach($streamToppers as $topper)
                        <div class="text-center bg-gray-50 rounded-xl p-4">
                            @if($topper->photo)
                            <div class="w-16 h-16 mx-auto rounded-full overflow-hidden border-2 mb-2" style="border-color: var(--color-primary)">
                                <img loading="lazy" src="{{ $topper->photo }}" alt="{{ $topper->name }}" class="w-full h-full object-cover">
                            </div>
                            @else
                            <div class="w-16 h-16 mx-auto rounded-full flex items-center justify-center text-white font-bold text-xl mb-2" style="background-color: var(--color-primary)">
                                {{ strtoupper(substr($topper->name, 0, 1)) }}
                            </div>
                            @endif
                            <p class="font-semibold text-sm text-gray-800">{{ $topper->name }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $topper->percentage }}%</p>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        </div>
        @empty
        <p class="text-gray-500 text-center py-12">Results have not been published yet.</p>
        @endforelse
    </div>
</section>
@endsection
