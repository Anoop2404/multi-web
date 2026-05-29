@php
    $vacancies = \App\Models\JobVacancy::where('tenant_id', $tenant->id)
        ->where('is_active', true)
        ->where(fn($q) => $q->whereNull('last_date')->orWhere('last_date', '>=', now()->toDateString()))
        ->orderByDesc('created_at')
        ->get();
@endphp
@if($vacancies->isNotEmpty())
<section class="py-16 px-4 bg-white">
    <div class="max-w-4xl mx-auto">
        <h2 class="text-3xl font-bold font-heading text-gray-900 mb-8">{{ $config['heading'] ?? 'Job Vacancies' }}</h2>

        <div class="space-y-4">
            @foreach($vacancies as $vacancy)
            <div class="bg-gray-50 rounded-2xl p-6 border border-gray-100 hover:border-primary/30 hover:shadow-sm transition"
                 x-data="{ open: false }">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="font-bold text-gray-900 text-lg">{{ $vacancy->title }}</h3>
                        @if($vacancy->last_date)
                        <p class="text-sm text-red-500 font-medium mt-1">
                            Last Date: {{ \Carbon\Carbon::parse($vacancy->last_date)->format('d M Y') }}
                        </p>
                        @endif
                    </div>
                    <button @click="open = !open"
                            class="shrink-0 flex items-center gap-1 text-sm font-semibold hover:underline"
                            style="color: var(--color-primary)">
                        <span x-text="open ? 'Less' : 'Details'">Details</span>
                        <svg class="w-4 h-4 transition" :class="open ? 'rotate-180' : ''"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                </div>

                <div x-show="open" x-collapse class="mt-4 text-sm text-gray-600 space-y-2">
                    @if($vacancy->qualification)
                    <p><span class="font-semibold text-gray-800">Qualification:</span> {{ $vacancy->qualification }}</p>
                    @endif
                    @if($vacancy->experience)
                    <p><span class="font-semibold text-gray-800">Experience:</span> {{ $vacancy->experience }}</p>
                    @endif
                    @if($vacancy->description)
                    <div class="pt-2">{{ $vacancy->description }}</div>
                    @endif
                    @if($vacancy->apply_email)
                    <a href="mailto:{{ $vacancy->apply_email }}"
                       class="inline-block mt-3 font-semibold px-4 py-2 rounded-lg text-white text-sm hover:opacity-90 transition"
                       style="background-color: var(--color-primary)">
                        Apply via Email
                    </a>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>
@else
<section class="py-16 px-4 bg-white">
    <div class="max-w-4xl mx-auto text-center text-gray-400">
        <p class="text-lg">No current vacancies.</p>
    </div>
</section>
@endif
