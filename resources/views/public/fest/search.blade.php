@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-xl mx-auto">
        <h1 class="text-2xl font-bold font-heading mb-4">{{ $event->title }} — Search</h1>
        <form method="get" class="flex gap-2 mb-2">
            <input name="q" value="{{ $q }}" placeholder="{{ $searchHint }}" class="flex-1 border rounded-lg px-3 py-2 text-sm" autofocus>
            <button class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm">Search</button>
        </form>
        @unless($nameSearch)
        <p class="text-xs text-gray-500 mb-6">Names are hidden until results are published. Search by chest number or level registration number.</p>
        @else
        <p class="mb-6"></p>
        @endunless
        <ul class="divide-y bg-white border rounded-xl">
            @forelse($results as $p)
            <li class="p-4">
                @if($p['link_ref'])
                <a href="{{ route('tenant.fest.participant', [$event->id, $p['link_ref']]) }}" class="font-medium text-amber-800 hover:underline">
                    @if(($p['reference'] ?? '—') !== '—')
                    <span class="font-mono">#{{ $p['reference'] }}</span>
                    @endif
                    @if($p['show_name'] && $p['name'])
                    {{ $p['name'] }}
                    @endif
                </a>
                @endif
                <p class="text-xs text-gray-500">{{ $p['item_title'] ?? '' }}</p>
            </li>
            @empty
            @if($q)<li class="p-4 text-gray-400 text-sm">No matches for "{{ $q }}"</li>@endif
            @endforelse
        </ul>
    </div>
</section>
@endsection
