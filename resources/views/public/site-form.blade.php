@extends('layouts.public')

@section('content')
<div class="max-w-xl mx-auto px-4 py-16">
    <h1 class="text-3xl font-bold font-heading mb-2" style="color: var(--color-primary)">{{ $form->name }}</h1>
    @if(session('success'))
        <div class="mb-6 rounded-xl bg-emerald-50 text-emerald-800 px-4 py-3 text-sm">{{ session('success') }}</div>
    @endif
    <form method="post" action="{{ url('/forms/'.$form->slug) }}" class="space-y-4 bg-white rounded-2xl border border-gray-100 p-6 shadow-sm">
        @csrf
        {{-- Honeypot --}}
        <div class="hidden" aria-hidden="true">
            <label>Website <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label>
        </div>
        @foreach(($form->fields_json ?? []) as $field)
            @php $key = $field['key'] ?? null; @endphp
            @continue(!$key)
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-1">{{ $field['label'] ?? $key }}</label>
                @if(($field['type'] ?? 'text') === 'textarea')
                    <textarea name="{{ $key }}" rows="4" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm" @if(!empty($field['required'])) required @endif>{{ old($key) }}</textarea>
                @else
                    <input name="{{ $key }}" type="{{ $field['type'] ?? 'text' }}" value="{{ old($key) }}"
                           class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm"
                           @if(!empty($field['required'])) required @endif>
                @endif
                @error($key)<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>
        @endforeach
        <button type="submit" class="w-full py-3 rounded-xl text-white font-bold" style="background: var(--color-primary)">Submit</button>
    </form>
</div>
@endsection
