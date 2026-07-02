@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold font-heading">{{ $event->title }} — Athletic Records</h1>
        <p class="text-gray-500 mt-1 text-sm">Standing records and recent record breaks</p>

        @if(empty($records) && empty($breaks))
        <p class="mt-8 text-gray-400 text-center py-8">Record tracking is not enabled or no records set yet.</p>
        @else
        @if(count($records))
        <h2 class="text-lg font-semibold mt-8 mb-3">Standing records</h2>
        <div class="bg-white border rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="p-3">Item</th>
                        <th class="p-3">Class</th>
                        <th class="p-3">Record</th>
                        <th class="p-3">Holder</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($records as $r)
                    <tr class="border-t">
                        <td class="p-3">{{ $r['item'] }}</td>
                        <td class="p-3 text-gray-500">{{ $r['class_group'] }} · {{ $r['gender'] }}</td>
                        <td class="p-3 font-mono font-semibold">{{ $r['value'] }} {{ $r['unit'] }}</td>
                        <td class="p-3">{{ $r['holder'] ?? '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        @if(count($breaks))
        <h2 class="text-lg font-semibold mt-10 mb-3">Record breaks &amp; prizes</h2>
        <ul class="space-y-2">
            @foreach($breaks as $b)
            <li class="bg-amber-50 border border-amber-100 rounded-xl p-4 text-sm">
                <p class="font-semibold">{{ $b['item'] }} — {{ $b['name'] ?? 'Participant' }}</p>
                <p class="text-gray-600 mt-1">New record: <strong>{{ $b['new_value'] }} {{ $b['unit'] }}</strong></p>
                <p class="text-amber-800 text-xs mt-1">🏅 {{ $b['prize_label'] }} · {{ $b['broken_at'] }}</p>
            </li>
            @endforeach
        </ul>
        @endif
        @endif

        <p class="mt-8 text-center">
            <a href="{{ route('tenant.fest.show', $event->id) }}" class="text-amber-600 text-sm">← Festival hub</a>
        </p>
    </div>
</section>
@endsection
