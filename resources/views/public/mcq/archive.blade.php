@extends('layouts.public')

@section('content')
<section class="py-12 px-4">
    <div class="max-w-3xl mx-auto">
        <p class="text-xs text-amber-600 font-bold uppercase">Downloads</p>
        <h1 class="text-2xl font-bold font-heading mb-2">Question Paper Archive</h1>
        <p class="text-gray-500 text-sm mb-8">Past model exam and talent search question papers for practice.</p>

        <ul class="space-y-3">
            @forelse($papers as $paper)
            <li class="bg-white border rounded-xl px-4 py-3 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-medium">{{ $paper->question_paper_label ?: $paper->title }}</p>
                    <p class="text-xs text-gray-500 mt-1">
                        {{ ucfirst($paper->exam_type ?? 'exam') }}
                        @if($paper->scheduled_at)
                        · {{ $paper->scheduled_at->format('M Y') }}
                        @endif
                    </p>
                </div>
                <a href="{{ route('tenant.mcq.archive.download', $paper->id) }}"
                   class="text-sm font-semibold text-amber-700">Download PDF ↓</a>
            </li>
            @empty
            <li class="text-center text-gray-400 py-10 border rounded-xl">No question papers published yet.</li>
            @endforelse
        </ul>
    </div>
</section>
@endsection
