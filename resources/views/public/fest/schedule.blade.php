@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-3xl mx-auto">
        <h1 class="text-2xl font-bold font-heading mb-6">{{ $event->title }} — Schedule</h1>
        <table class="w-full text-sm bg-white border rounded-xl overflow-hidden">
            <thead class="bg-gray-50"><tr><th class="p-3 text-left">Time</th><th class="p-3 text-left">Item</th><th class="p-3 text-left">Participant</th><th class="p-3 text-left">Stage</th></tr></thead>
            <tbody>
            @forelse($schedules as $row)
            <tr class="border-t">
                <td class="p-3">{{ $row->scheduled_at?->format('H:i') ?? '—' }}</td>
                <td class="p-3">{{ $row->item?->title }}</td>
                <td class="p-3">
                    @if($row->participant)
                    <a href="{{ route('tenant.fest.participant', [$event->id, $row->participant->chest_no]) }}" class="text-amber-700 hover:underline">
                        #{{ $row->participant->chest_no }} {{ $row->participant->student?->name ?? $row->participant->teacher?->name }}
                    </a>
                    @else — @endif
                </td>
                <td class="p-3">{{ $row->stage ?? '—' }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="p-6 text-center text-gray-400">Schedule not published yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        <p class="mt-4"><a href="{{ route('tenant.fest.show', $event->id) }}" class="text-sm text-amber-700">← Back</a></p>
    </div>
</section>
@endsection
