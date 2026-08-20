@extends('layouts.public-event')

@section('content')
@php
    $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
    $rankTint = [1 => 'border-amber-500/40 bg-gradient-to-br from-amber-500/10 to-slate-900/60', 2 => 'border-slate-500/30', 3 => 'border-orange-700/30'];
    $typeLabels = ['individual' => 'Individual', 'pair' => 'Pair', 'trio' => 'Trio', 'group' => 'Group', 'team' => 'Team'];
@endphp
<section class="py-8 sm:py-12 px-4 bg-slate-950 text-white min-h-screen">
    <div class="max-w-5xl mx-auto">
        @include('public.fest.partials.page-hero', [
            'eyebrow' => 'Results',
            'title' => $item->title,
            'subtitle' => $event->title,
            'badges' => [$typeLabels[$item->participant_type] ?? ucfirst($item->participant_type ?: 'individual')],
        ])

        @if($marks->isEmpty())
        <div class="rounded-2xl border border-dashed border-slate-700 p-10 text-center text-white/30 mt-6">No published results for this item.</div>
        @else
        <div class="grid sm:grid-cols-2 gap-4 mt-6">
            @foreach($marks as $row)
            @php
                $roster = ($row['team'] ?? []) ?: [['name' => $row['participant'], 'photo' => $row['photo'] ?? null]];
            @endphp
            <div class="rounded-2xl border bg-slate-900/60 p-5 {{ $rankTint[$row['position']] ?? 'border-slate-800' }}">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-3xl leading-none">{{ $medals[$row['position']] ?? ($row['position'] ? '#'.$row['position'] : '—') }}</span>
                    <div class="flex items-center gap-2 flex-wrap justify-end">
                        @if(!empty($row['grade']))
                        <span class="text-xs font-semibold text-amber-300 bg-amber-500/10 px-2 py-0.5 rounded border border-amber-500/30">Grade {{ $row['grade'] }}</span>
                        @endif
                        @if(!empty($row['measurement']))
                        <span class="text-xs font-semibold text-white/60 bg-white/5 px-2 py-0.5 rounded border border-slate-700">{{ $row['measurement'] }}</span>
                        @endif
                    </div>
                </div>

                <p class="text-xs text-white/40 mt-3">{{ $row['school'] }}</p>

                <div class="mt-2 space-y-2.5">
                    @foreach($roster as $member)
                    <div class="flex items-center gap-3">
                        @if($member['photo'] ?? null)
                        <img src="{{ $member['photo'] }}" alt="" class="w-16 h-16 rounded-full object-cover border-2 border-slate-800 shadow-sm shrink-0">
                        @else
                        <span class="w-16 h-16 rounded-full bg-amber-500/15 text-amber-300 flex items-center justify-center text-xl font-bold border-2 border-slate-800 shadow-sm shrink-0">{{ strtoupper(substr($member['name'] ?? '?', 0, 1)) }}</span>
                        @endif
                        <span class="font-bold text-lg leading-snug text-white">{{ $member['name'] ?? '—' }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
        @endif

        <p class="mt-6"><a href="{{ route('tenant.fest.show', $event->id) }}" class="text-sm text-white/40 hover:text-white">← Event page</a></p>
    </div>
</section>
@endsection
