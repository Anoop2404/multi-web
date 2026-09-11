@extends('emails.layouts.sahodaya')

@section('content')
    <h2 style="margin:0 0 16px;font-size:18px;color:#041525;font-weight:700;">{{ $form->name }}</h2>

    @include('emails.partials.detail-table', [
        'rows' => collect($form->fields_json ?? [])->mapWithKeys(function ($field) use ($submission) {
            $key = $field['key'] ?? null;

            return $key ? [($field['label'] ?? $key) => data_get($submission->payload_json, $key)] : [];
        })->filter()->all(),
    ])

    <p class="muted" style="color:#64748b;font-size:13px;margin:0;">
        Submitted {{ $submission->created_at->format('d M Y, h:i A') }} · {{ $school->name }} Website
    </p>
@endsection
