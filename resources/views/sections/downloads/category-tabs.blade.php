@php
    $downloads = \App\Models\Download::where('tenant_id', $tenant->id)
        ->where('is_active', true)
        ->orderBy('display_order')
        ->orderByDesc('created_at')
        ->get();

    $categories = [
        'booklist'      => 'Book List',
        'calendar'      => 'Calendar',
        'circular'      => 'Circulars',
        'question_paper'=> 'Question Papers',
        'annual_report' => 'Annual Reports',
        'form'          => 'Forms',
        'minutes'       => 'Minutes',
        'other'         => 'Others',
    ];
    $grouped = $downloads->groupBy('category');
    $available = $grouped->keys()->toArray();
@endphp
@if($downloads->isNotEmpty())
<section class="py-16 px-4 bg-white">
    <div class="max-w-5xl mx-auto">
        <h2 class="text-3xl font-bold font-heading text-gray-900 mb-8">{{ $config['heading'] ?? 'Downloads' }}</h2>

        <div x-data="{ tab: '{{ $available[0] ?? 'other' }}' }">
            {{-- Tabs --}}
            <div class="flex flex-wrap gap-2 mb-8 border-b border-gray-100 pb-4">
                @foreach($categories as $key => $label)
                @if(isset($grouped[$key]))
                <button @click="tab = '{{ $key }}'"
                        class="px-4 py-2 rounded-full text-sm font-medium transition"
                        :class="tab === '{{ $key }}'
                            ? 'text-white shadow'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                        :style="tab === '{{ $key }}' ? 'background-color: var(--color-primary)' : ''">
                    {{ $label }}
                    <span class="ml-1 text-xs opacity-70">({{ $grouped[$key]->count() }})</span>
                </button>
                @endif
                @endforeach
            </div>

            {{-- Tab content --}}
            @foreach($categories as $key => $label)
            @if(isset($grouped[$key]))
            <div x-show="tab === '{{ $key }}'" x-transition>
                <div class="divide-y divide-gray-50">
                    @foreach($grouped[$key] as $dl)
                    <div class="flex items-center justify-between py-3 hover:bg-gray-50 px-3 rounded-lg transition">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0"
                                 style="background-color: color-mix(in srgb, var(--color-primary) 15%, transparent)">
                                <svg class="w-5 h-5" style="color: var(--color-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="font-medium text-gray-800 text-sm">{{ $dl->title }}</p>
                                @if($dl->academic_year)
                                <p class="text-xs text-gray-400">{{ $dl->academic_year }}</p>
                                @endif
                            </div>
                        </div>
                        <a href="{{ \Storage::url($dl->file_path) }}" target="_blank"
                           class="shrink-0 ml-4 text-sm font-semibold flex items-center gap-1 hover:underline"
                           style="color: var(--color-primary)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Download
                        </a>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
            @endforeach
        </div>
    </div>
</section>
@endif
