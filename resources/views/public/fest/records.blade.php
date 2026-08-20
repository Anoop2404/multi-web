@extends('layouts.public-event')

@section('content')
<section class="py-8 sm:py-12 px-4 bg-slate-950 text-white min-h-screen">
    <div class="max-w-4xl mx-auto">
        @include('public.fest.partials.page-hero', [
            'eyebrow' => 'Records',
            'title' => $event->title.' — Athletic Records',
            'subtitle' => 'Standing records and recent record breaks',
        ])

        @if(empty($records) && empty($breaks))
        <p class="mt-8 text-white/30 text-center py-8">Record tracking is not enabled or no records set yet.</p>
        @else
        @if(count($records))
        <h2 class="text-xs font-bold uppercase tracking-widest text-amber-400 mt-10 mb-3">Standing records</h2>
        <div class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-white/5 text-left text-xs uppercase text-white/40">
                    <tr>
                        <th class="p-3">Item</th>
                        <th class="p-3">Class</th>
                        <th class="p-3">Record</th>
                        <th class="p-3">Holder</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @foreach($records as $r)
                    <tr>
                        <td class="p-3 text-white">{{ $r['item'] }}</td>
                        <td class="p-3 text-white/50">{{ $r['class_group'] }} · {{ $r['gender'] }}</td>
                        <td class="p-3 font-mono font-semibold text-amber-300">{{ $r['value'] }} {{ $r['unit'] }}</td>
                        <td class="p-3 text-white/70">{{ $r['holder'] ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(count($breaks))
        <h2 class="text-xs font-bold uppercase tracking-widest text-amber-400 mt-10 mb-3">Record breaks &amp; prizes</h2>
        <ul class="space-y-3">
            @foreach($breaks as $b)
            <li class="relative overflow-hidden rounded-2xl border border-amber-500/40 bg-gradient-to-br from-amber-500/15 to-slate-900/60 p-4 shadow-[0_0_25px_-8px_rgba(245,158,11,0.5)]">
                <div aria-hidden="true" class="absolute -right-6 -top-6 text-6xl opacity-10">🏆</div>
                <span class="inline-flex items-center gap-1 text-[10px] font-extrabold uppercase tracking-widest text-amber-300 bg-amber-500/20 border border-amber-500/40 rounded-full px-2 py-0.5">🔥 New Record</span>
                <p class="font-bold text-lg text-white mt-2">{{ $b['item'] }} — {{ $b['name'] ?? 'Participant' }}</p>
                <p class="text-white/70 mt-1">New record: <strong class="text-amber-300 font-mono">{{ $b['new_value'] }} {{ $b['unit'] }}</strong></p>
                <p class="text-amber-300/80 text-xs mt-1">🏅 {{ $b['prize_label'] }} · {{ $b['broken_at'] }}</p>
            </li>
            @endforeach
        </ul>
        @endif
        @endif

        <p class="mt-10 pt-6 border-t border-slate-800 text-center">
            <a href="{{ route('tenant.fest.show', $event->id) }}" class="text-amber-400 text-sm font-semibold hover:underline">← Event page</a>
        </p>
    </div>
</section>
@endsection
