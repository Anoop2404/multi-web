<section class="py-16 px-4 bg-white">
    <div class="max-w-5xl mx-auto">
        <div class="grid md:grid-cols-2 gap-10 items-center">
            <div>
                <p class="text-sm font-semibold uppercase tracking-widest mb-2" style="color: var(--color-primary)">
                    {{ $config['eyebrow'] ?? 'Admissions' }}
                </p>
                <h2 class="text-3xl font-bold font-heading text-gray-900 mb-4">
                    {{ $config['heading'] ?? 'Join Our School' }}
                </h2>
                @if(!empty($config['body']))
                <div class="text-gray-600 leading-relaxed mb-6">{!! nl2br(e($config['body'])) !!}</div>
                @endif

                @if(!empty($config['steps']) && is_array($config['steps']))
                <ol class="space-y-4 mb-6">
                    @foreach($config['steps'] as $i => $step)
                    <li class="flex gap-4">
                        <div class="w-8 h-8 rounded-full text-white text-sm font-bold flex items-center justify-center shrink-0"
                             style="background-color: var(--color-primary)">{{ $i + 1 }}</div>
                        <div class="pt-1">
                            @if(is_array($step))
                            <p class="font-semibold text-gray-800">{{ $step['title'] ?? '' }}</p>
                            <p class="text-sm text-gray-500">{{ $step['description'] ?? '' }}</p>
                            @else
                            <p class="text-gray-700">{{ $step }}</p>
                            @endif
                        </div>
                    </li>
                    @endforeach
                </ol>
                @endif

                @if(!empty($config['cta_label']) && !empty($config['cta_url']))
                <a href="{{ $config['cta_url'] }}"
                   class="inline-block font-semibold px-6 py-3 rounded-full text-white hover:opacity-90 transition"
                   style="background-color: var(--color-primary)">
                    {{ $config['cta_label'] }}
                </a>
                @endif
            </div>

            {{-- Key info sidebar --}}
            <div class="bg-gray-50 rounded-2xl p-6 space-y-4">
                @foreach([
                    ['label' => 'Academic Year',   'key' => 'academic_year'],
                    ['label' => 'Age Criteria',    'key' => 'age_criteria'],
                    ['label' => 'Registration Fee','key' => 'registration_fee'],
                    ['label' => 'Last Date',       'key' => 'last_date'],
                    ['label' => 'Contact',         'key' => 'contact'],
                ] as $row)
                @if(!empty($config[$row['key']]))
                <div class="flex justify-between text-sm border-b border-gray-200 pb-3 last:border-0 last:pb-0">
                    <span class="text-gray-500 font-medium">{{ $row['label'] }}</span>
                    <span class="text-gray-800 font-semibold">{{ $config[$row['key']] }}</span>
                </div>
                @endif
                @endforeach

                @if(!empty($config['download_prospectus_url']))
                <a href="{{ $config['download_prospectus_url'] }}" target="_blank"
                   class="flex items-center justify-center gap-2 w-full border-2 font-semibold py-2.5 rounded-lg transition hover:bg-opacity-10 mt-2"
                   style="border-color: var(--color-primary); color: var(--color-primary)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Download Prospectus
                </a>
                @endif
            </div>
        </div>
    </div>
</section>
