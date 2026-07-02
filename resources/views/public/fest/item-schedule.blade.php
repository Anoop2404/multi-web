@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-3xl mx-auto">
        <p class="text-xs text-amber-600 font-bold uppercase">Item schedule</p>
        <h1 class="text-2xl font-bold font-heading mb-1">{{ $item->title }}</h1>
        <p class="text-gray-500 text-sm mb-6">{{ $event->title }}</p>
        <table class="w-full text-sm bg-white border rounded-xl overflow-hidden">
            <thead class="bg-gray-50">
                <tr>
                    <th class="p-3 text-left">Order</th>
                    <th class="p-3 text-left">Time</th>
                    <th class="p-3 text-left">Participant</th>
                    <th class="p-3 text-left">Stage</th>
                </tr>
            </thead>
            <tbody>
            @forelse($schedules as $row)
            <tr class="border-t">
                <td class="p-3 font-mono text-xs">{{ $row['sort_order'] ?? '—' }}</td>
                <td class="p-3">{{ $row['scheduled_at']?->format('H:i') ?? '—' }}</td>
                <td class="p-3">
                    @if($row['participant'] && $row['participant']['link_ref'])
                    <a href="{{ route('tenant.fest.participant', [$event->id, $row['participant']['link_ref']]) }}" class="text-amber-700 hover:underline">
                        @if(($row['participant']['reference'] ?? '—') !== '—')
                        <span class="font-mono">#{{ $row['participant']['reference'] }}</span>
                        @endif
                        @if($row['participant']['show_name'] && $row['participant']['name'])
                        {{ $row['participant']['name'] }}
                        @elseif(!$row['participant']['show_name'])
                        <span class="text-gray-400 text-xs">(anonymous until results)</span>
                        @endif
                    </a>
                    @else — @endif
                </td>
                <td class="p-3">{{ $row['stage'] ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="p-6 text-center text-gray-400">No performance order for this item yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        <p class="mt-4 flex gap-4">
            <a href="{{ route('tenant.fest.schedule', $event->id) }}" class="text-sm text-amber-700">← Full schedule</a>
            <a href="{{ route('tenant.fest.show', $event->id) }}" class="text-sm text-gray-500">Festival hub</a>
        </p>
    </div>
</section>
@endsection
