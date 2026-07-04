@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-3xl mx-auto">
        <p class="text-xs text-amber-600 font-bold uppercase">Results</p>
        <h1 class="text-2xl font-bold font-heading mb-1">{{ $item->title }}</h1>
        <p class="text-gray-500 text-sm mb-6">{{ $event->title }}</p>
        <table class="w-full text-sm bg-white border rounded-xl overflow-hidden">
            <thead class="bg-gray-50">
                <tr>
                    <th class="p-3 text-left">Position</th>
                    <th class="p-3 text-left">Ref</th>
                    <th class="p-3 text-left">Name</th>
                    <th class="p-3 text-left">School</th>
                    <th class="p-3 text-left">Grade</th>
                    <th class="p-3 text-left">Result</th>
                </tr>
            </thead>
            <tbody>
            @forelse($marks as $row)
                <tr class="border-t">
                <td class="p-3 font-mono">#{{ $row['position'] ?? '—' }}</td>
                <td class="p-3 font-mono text-xs">{{ $row['reference'] }}</td>
                <td class="p-3">{{ $row['name'] ?? '—' }}</td>
                <td class="p-3">{{ $row['school'] ?? '—' }}</td>
                <td class="p-3">{{ $row['grade'] ?? '—' }}</td>
                <td class="p-3">
                    {{ $row['result'] ?: ($row['score'] ?? '—') }}
                    @if(!empty($row['poster_url']))
                    <a href="{{ $row['poster_url'] }}" class="block text-xs text-amber-700 mt-1" download>Winner poster ↓</a>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="p-6 text-center text-gray-400">No published results for this item.</td></tr>
            @endforelse
            </tbody>
        </table>
        <p class="mt-4 flex flex-wrap gap-4">
            <a href="{{ route('tenant.fest.item-results.pdf', [$event->id, $item->id]) }}" class="text-sm text-amber-700 font-medium">Download PDF ↓</a>
            <a href="{{ route('tenant.fest.show', $event->id) }}" class="text-sm text-amber-700">← Festival hub</a>
        </p>
    </div>
</section>
@endsection
