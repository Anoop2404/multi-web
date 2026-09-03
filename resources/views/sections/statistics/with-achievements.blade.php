@php
    $statsItems = $config['stats'] ?? [];
    if (empty($statsItems)) {
        $realSchools = \App\Support\SahodayaPublicData::memberSchools($tenant->id)->count();
        $realBearers = \App\Support\SahodayaPublicData::officeBearers($tenant->id)->count();
        $statsItems = collect([
            $realSchools > 0 ? ['value' => $realSchools, 'label' => 'Member Schools'] : null,
            $realBearers > 0 ? ['value' => $realBearers, 'label' => 'Office Bearers'] : null,
        ])->filter()->values()->all();
    }
@endphp
<section class="py-16 px-4">
    <div class="max-w-7xl mx-auto">
        @if(!empty($config['heading']))
        <h2 class="text-3xl md:text-4xl font-bold font-heading text-center mb-4" style="color: var(--color-primary)">{{ $config['heading'] }}</h2>
        @endif
        <div class="grid {{ !empty($statsItems) ? 'md:grid-cols-2' : '' }} gap-10">
            {{-- Stats --}}
            @if(!empty($statsItems))
            <div class="grid grid-cols-2 gap-4">
                @foreach($statsItems as $stat)
                <div class="p-5 rounded-xl text-center shadow-sm border" style="border-color: var(--color-primary);">
                    <div class="text-3xl font-bold font-heading" style="color: var(--color-primary)">{{ $stat['value'] ?? 0 }}</div>
                    <div class="text-sm text-gray-500 mt-1">{{ $stat['label'] ?? '' }}</div>
                </div>
                @endforeach
            </div>
            @endif
            {{-- Key Achievements --}}
            <div>
                <h3 class="text-xl font-bold font-heading mb-4" style="color: var(--color-secondary)">{{ $config['achievements_title'] ?? 'Key Achievements' }}</h3>
                @if(!empty($config['achievements']) && is_array($config['achievements']))
                <ul class="space-y-3">
                    @foreach($config['achievements'] as $achievement)
                    <li class="flex gap-3 items-start">
                        <span class="mt-1 shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-white text-xs"
                              style="background-color: var(--color-primary)">✓</span>
                        <span class="text-gray-600">{{ $achievement['text'] ?? $achievement }}</span>
                    </li>
                    @endforeach
                </ul>
                @endif
            </div>
        </div>
    </div>
</section>