{{-- sports_meet/results-highlight.blade.php — Sports meet results with school leaderboard --}}
<section class="py-16 px-4 bg-gray-50">
    <div class="max-w-5xl mx-auto">
        {{-- Header --}}
        <div class="text-center mb-10">
            @if(!empty($config['eyebrow']))
            <div class="inline-flex items-center gap-2 text-xs font-bold uppercase tracking-widest mb-3 px-4 py-2 rounded-full"
                 style="background: color-mix(in srgb, var(--color-primary) 12%, white); color: var(--color-primary)">
                🏅 {{ $config['eyebrow'] }}
            </div>
            @endif
            <h2 class="font-heading text-3xl sm:text-4xl font-bold text-gray-900">
                {{ $config['heading'] ?? 'Sports Meet Results' }}
            </h2>
            @if(!empty($config['year']))
            <p class="mt-2 text-sm text-gray-500 font-semibold">{{ $config['year'] }}</p>
            @endif
        </div>

        {{-- Trophy podium --}}
        @php $results = $config['results'] ?? []; @endphp
        @if(!empty($results))
        <div class="flex items-end justify-center gap-4 mb-12">
            @php
                $podium = [
                    1 => ['h' => 'h-28', 'color' => '#f59e0b', 'label' => '2nd', 'shadow' => 'shadow-amber-200'],
                    0 => ['h' => 'h-36', 'color' => '#6d28d9', 'label' => '1st', 'shadow' => 'shadow-purple-200'],
                    2 => ['h' => 'h-20', 'color' => '#b45309', 'label' => '3rd', 'shadow' => 'shadow-amber-100'],
                ];
                $order = [1, 0, 2];
            @endphp
            @foreach($order as $pos)
            @if(isset($results[$pos]))
            @php $r = $results[$pos]; $p = $podium[$pos]; @endphp
            <div class="flex-1 max-w-[180px] text-center">
                <div class="mb-2 text-4xl">
                    @if($pos === 0) 🥇 @elseif($pos === 1) 🥈 @else 🥉 @endif
                </div>
                <div class="bg-white rounded-xl border-2 p-4 shadow-lg mb-2"
                     style="border-color: {{ $p['color'] }}20">
                    @if(!empty($r['school_logo']))
                    <img src="{{ $r['school_logo'] }}" alt="{{ $r['school'] }}" class="w-12 h-12 rounded-full mx-auto object-contain mb-2">
                    @endif
                    <p class="font-bold text-sm text-gray-900">{{ $r['school'] ?? '' }}</p>
                    @if(!empty($r['points']))
                    <p class="text-xs text-gray-500 mt-1">{{ $r['points'] }} pts</p>
                    @endif
                </div>
                <div class="{{ $p['h'] }} rounded-t-xl flex items-center justify-center text-white text-lg font-bold shadow-lg {{ $p['shadow'] }}"
                     style="background-color: {{ $p['color'] }}">
                    {{ $p['label'] }}
                </div>
            </div>
            @endif
            @endforeach
        </div>

        {{-- Full standings table --}}
        @if(count($results) > 3)
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="text-left px-5 py-3 text-xs font-bold text-gray-500 uppercase">Rank</th>
                        <th class="text-left px-5 py-3 text-xs font-bold text-gray-500 uppercase">School</th>
                        <th class="text-right px-5 py-3 text-xs font-bold text-gray-500 uppercase">Points</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($results as $i => $r)
                    <tr class="border-b border-gray-50 hover:bg-gray-50 transition">
                        <td class="px-5 py-3">
                            <span class="w-7 h-7 rounded-full inline-flex items-center justify-center text-xs font-bold text-white"
                                  style="background: {{ $i < 3 ? '#6d28d9' : '#9ca3af' }}">
                                {{ $i + 1 }}
                            </span>
                        </td>
                        <td class="px-5 py-3 font-semibold text-gray-800">{{ $r['school'] ?? '' }}</td>
                        <td class="px-5 py-3 text-right font-bold text-gray-700">{{ $r['points'] ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @else
        <p class="text-center text-gray-400 py-8">Add results in the section editor to display the leaderboard.</p>
        @endif
    </div>
</section>
