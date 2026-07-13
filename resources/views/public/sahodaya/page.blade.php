@extends('layouts.public')

@section('content')
@php
    $orgTitle = $page['org_title'] ?? 'CONFEDERATION OF KERALA SAHODAYA COMPLEXES';
    $subtitle = $page['subtitle'] ?? ($page['title'] ?? '');
    $pageLogo = $page['logo'] ?? ($logo ?? null);
@endphp
<section class="scroll-mt-24">
    {{-- Gold page header band (CKSC inner page style) --}}
    <div class="px-4 sm:px-8 py-16 sm:py-24 flex flex-col items-center text-center gap-6 sm:gap-10"
         style="background: var(--color-secondary);">
        @if($pageLogo)
        <div class="w-28 h-28 sm:w-36 sm:h-36 bg-white rounded-full flex items-center justify-center overflow-hidden shadow-xl">
            <img src="{{ $pageLogo }}" alt="" class="w-full h-full object-cover">
        </div>
        @endif
        <div class="max-w-4xl space-y-3">
            <p class="text-lg sm:text-2xl font-bold leading-snug" style="color: var(--color-primary)">{!! nl2br(e($orgTitle)) !!}</p>
            <h1 class="text-3xl sm:text-5xl font-bold" style="color: var(--color-primary)">{{ $subtitle }}</h1>
        </div>
    </div>

    <div class="max-w-5xl mx-auto px-4 sm:px-8 py-12 sm:py-20">
        @if(!empty($page['content_html']))
        <div class="prose prose-lg max-w-none text-gray-800 leading-relaxed cksc-page-content">
            {!! \App\Support\HtmlSanitizer::rich($page['content_html'] ?? '') !!}
        </div>
        @elseif(!empty($page['content']))
        <div class="prose prose-lg max-w-none text-gray-800 leading-relaxed">
            {!! nl2br(e($page['content'])) !!}
        </div>
        @endif

        @if(!empty($page['table_rows']) && is_array($page['table_rows']))
        <div class="mt-8 overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="text-left" style="background:#15224D;color:white">
                        @foreach($page['table_headers'] ?? array_keys($page['table_rows'][0] ?? []) as $header)
                        <th class="px-4 py-3 font-semibold">{{ is_string($header) ? $header : '' }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($page['table_rows'] as $row)
                    <tr class="border-b border-gray-100 even:bg-gray-50">
                        @foreach($row as $cell)
                        <td class="px-4 py-3 text-gray-700">{{ $cell }}</td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>
</section>
@endsection
