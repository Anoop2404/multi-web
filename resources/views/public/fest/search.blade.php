@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-xl mx-auto">
        <h1 class="text-2xl font-bold font-heading mb-4">{{ $event->title }} — Search</h1>
        <form method="get" class="flex gap-2 mb-6">
            <input name="q" value="{{ $q }}" placeholder="Chest number or name" class="flex-1 border rounded-lg px-3 py-2 text-sm" autofocus>
            <button class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm">Search</button>
        </form>
        <ul class="divide-y bg-white border rounded-xl">
            @forelse($results as $p)
            <li class="p-4">
                <a href="{{ route('tenant.fest.participant', [$event->id, $p->chest_no]) }}" class="font-medium text-amber-800 hover:underline">
                    #{{ $p->chest_no }} {{ $p->student?->name ?? $p->teacher?->name }}
                </a>
                <p class="text-xs text-gray-500">{{ $p->registration?->item?->title }}</p>
            </li>
            @empty
            @if($q)<li class="p-4 text-gray-400 text-sm">No matches for "{{ $q }}"</li>@endif
            @endforelse
        </ul>
    </div>
</section>
@endsection
