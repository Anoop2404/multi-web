@extends('layouts.public')

@section('content')
@php
    $tabs = [
        'school' => 'School-wise',
        'category' => 'Category-wise',
        'item' => 'Item-wise',
        'individual' => 'Individual',
        'championship' => 'Championship',
    ];
@endphp

<section class="py-12 px-4">
    <div class="max-w-6xl mx-auto">
        <p class="text-xs text-amber-600 font-bold uppercase">Published Results</p>
        <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
            <div>
                <h1 class="text-3xl font-bold font-heading">{{ $event->title }}</h1>
                <p class="text-gray-500 text-sm mt-1">Browse results by school, category, item, or participant.</p>
                @if($publishedAt ?? null)
                    <p class="text-xs text-gray-400 mt-1">Results published on {{ \Carbon\Carbon::parse($publishedAt)->format('d M Y, g:i A') }}</p>
                @endif
            </div>
            <a href="{{ route('tenant.fest.scoreboard', $event->id) }}" class="text-sm font-semibold text-amber-700 hover:underline">Scoreboard →</a>
        </div>

        <nav class="flex flex-wrap gap-2 mb-8">
            @foreach($tabs as $key => $label)
                <a href="{{ route('tenant.fest.results', ['event' => $event->id, 'tab' => $key]) }}"
                   class="px-4 py-2 rounded-full text-sm border {{ $tab === $key ? 'bg-amber-500 text-white border-amber-500' : 'bg-white hover:border-amber-400' }}">
                    {{ $label }}
                </a>
            @endforeach
        </nav>

        @if($tab === 'school')
            <div class="bg-white border rounded-2xl overflow-hidden shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr><th class="p-3">Rank</th><th class="p-3">School</th><th class="p-3 text-right">Points</th></tr>
                    </thead>
                    <tbody>
                        @forelse($schoolBoard as $row)
                            <tr class="border-t">
                                <td class="p-3 font-bold text-amber-700">#{{ $row['rank'] }}</td>
                                <td class="p-3 font-semibold">{{ $row['school_name'] }}</td>
                                <td class="p-3 text-right font-mono">{{ $row['total_points'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="p-8 text-center text-gray-400">No school points published yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif($tab === 'category')
            <div class="grid lg:grid-cols-2 gap-5">
                @forelse($categoryBoards as $board)
                    <section class="bg-white border rounded-2xl overflow-hidden shadow-sm">
                        <div class="px-4 py-3 bg-gray-50 border-b">
                            <h2 class="font-bold">{{ $board['label'] }}</h2>
                        </div>
                        <table class="w-full text-sm">
                            <tbody>
                                @forelse($board['rows'] as $row)
                                    <tr class="border-t">
                                        <td class="p-3 font-bold text-amber-700">#{{ $row['rank'] }}</td>
                                        <td class="p-3">{{ $row['school_name'] }}</td>
                                        <td class="p-3 text-right font-mono">{{ $row['total_points'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="p-6 text-center text-gray-400">No scores yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </section>
                @empty
                    <p class="text-gray-400">No categories found.</p>
                @endforelse
            </div>
        @elseif($tab === 'item')
            <div class="space-y-5">
                @forelse($itemResults as $item)
                    <section class="bg-white border rounded-2xl overflow-hidden shadow-sm">
                        <div class="px-4 py-3 bg-gray-50 border-b">
                            <h2 class="font-bold">{{ $item['item'] }}</h2>
                            @if($item['head'])<p class="text-xs text-gray-500">{{ $item['head'] }}</p>@endif
                        </div>
                        <table class="w-full text-sm">
                            <thead class="text-left text-xs uppercase text-gray-500">
                                <tr><th class="p-3">Position</th><th class="p-3">Participant</th><th class="p-3">School</th><th class="p-3">Result</th></tr>
                            </thead>
                            <tbody>
                                @foreach($item['winners'] as $winner)
                                    <tr class="border-t">
                                        <td class="p-3 font-bold">{{ $winner['position'] }}</td>
                                        <td class="p-3">{{ $winner['participant'] }}</td>
                                        <td class="p-3">{{ $winner['school'] }}</td>
                                        <td class="p-3">{{ $winner['measurement'] ?: ($winner['score'] ?? $winner['grade'] ?? '—') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </section>
                @empty
                    <p class="text-gray-400">No item winners published yet.</p>
                @endforelse
            </div>
        @elseif($tab === 'individual')
            <div class="bg-white border rounded-2xl overflow-hidden shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr><th class="p-3">Participant</th><th class="p-3">School</th><th class="p-3">Item</th><th class="p-3">Position</th><th class="p-3">Result</th></tr>
                    </thead>
                    <tbody>
                        @forelse($individualResults as $row)
                            <tr class="border-t">
                                <td class="p-3 font-semibold">{{ $row['participant'] }}</td>
                                <td class="p-3">{{ $row['school'] }}</td>
                                <td class="p-3">{{ $row['item'] }}</td>
                                <td class="p-3 font-bold">{{ $row['position'] }}</td>
                                <td class="p-3">{{ $row['measurement'] ?: ($row['score'] ?? $row['grade'] ?? '—') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-8 text-center text-gray-400">No individual results published yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @else
            <div class="bg-white border rounded-2xl overflow-hidden shadow-sm">
                <div class="px-4 py-3 bg-gray-50 border-b">
                    <h2 class="font-bold">Individual Championship</h2>
                    <p class="text-xs text-gray-500">Total points across the whole meet, per student.</p>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr><th class="p-3">Rank</th><th class="p-3">Student</th><th class="p-3">School</th><th class="p-3">Category</th><th class="p-3">Gender</th><th class="p-3 text-right">Points</th></tr>
                    </thead>
                    <tbody>
                        @forelse($championship as $row)
                            <tr class="border-t">
                                <td class="p-3 font-bold text-amber-700">#{{ $row['rank'] }}</td>
                                <td class="p-3 font-semibold">{{ $row['student'] }}</td>
                                <td class="p-3">{{ $row['school'] }}</td>
                                <td class="p-3">{{ $row['category'] }}</td>
                                <td class="p-3">{{ $row['gender'] }}</td>
                                <td class="p-3 text-right font-mono">{{ $row['points'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-8 text-center text-gray-400">No championship points published yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</section>
@endsection
