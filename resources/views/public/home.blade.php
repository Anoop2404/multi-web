@extends('layouts.public')

@section('title', $tenant->name)

@section('content')
    @forelse($sections as $section)
        @include("sections.{$section->section_type}.{$section->variant}", [
            'config'  => $section->config ?? [],
            'section' => $section,
            'tenant'  => $tenant,
        ])
    @empty
        <div class="min-h-screen flex items-center justify-center text-gray-400">
            <p>This site is being set up. Please check back soon.</p>
        </div>
    @endforelse
@endsection
