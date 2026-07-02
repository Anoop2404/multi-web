@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-2xl font-bold font-heading mb-6">{{ $event->title }} — Schedule</h1>
        <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-sm bg-white border rounded-xl overflow-hidden">
            <thead class="bg-gray-50">
                <tr>
                    <th class="p-3 text-left">Order</th>
                    <th class="p-3 text-left">Time</th>
                    <th class="p-3 text-left">Item</th>
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
                    @if($row['item_id'] && $row['item_title'])
                    <a href="{{ route('tenant.fest.item-schedule', [$event->id, $row['item_id']]) }}" class="text-amber-800 hover:underline">{{ $row['item_title'] }}</a>
                    @else — @endif
                </td>
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
            <tr><td colspan="5" class="p-6 text-center text-gray-400">Schedule not published yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
        <p class="mt-4"><a href="{{ route('tenant.fest.show', $event->id) }}" class="text-sm text-amber-700">← Back</a></p>
    </div>
</section>
@endsection
