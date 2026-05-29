@php
    $limit = $config['limit'] ?? 9;
    $downloads = \App\Models\Download::where('tenant_id', $tenant->id)
        ->where('is_active', true)
        ->orderBy('display_order')
        ->limit($limit)->get();
@endphp
@if($downloads->isNotEmpty())
<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-7xl mx-auto">
        <h2 class="text-3xl font-bold font-heading text-gray-900 mb-8">{{ $config['heading'] ?? 'Downloads' }}</h2>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($downloads as $dl)
            <div class="bg-white rounded-xl p-5 shadow-sm hover:shadow-md transition flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                     style="background-color: color-mix(in srgb, var(--color-primary) 15%, transparent)">
                    <svg class="w-6 h-6" style="color: var(--color-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="font-semibold text-gray-800 text-sm truncate">{{ $dl->title }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $dl->getCategoryLabelAttribute() }}
                        @if($dl->academic_year) &bull; {{ $dl->academic_year }} @endif
                    </p>
                </div>
                <a href="{{ \Storage::url($dl->file_path) }}" target="_blank"
                   class="shrink-0 p-2 rounded-lg hover:bg-gray-100 transition"
                   style="color: var(--color-primary)" title="Download">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                </a>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
