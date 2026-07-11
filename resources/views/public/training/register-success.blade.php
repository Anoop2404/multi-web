@extends('layouts.portal')

@section('title', 'Registered — '.$program->title)

@section('content')
<div class="portal-wrap">
    <div class="portal-form-wrap">
        <div class="portal-card">
            <div class="portal-card-header" style="flex-direction:column;align-items:center;text-align:center;gap:.5rem;">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="" style="height:48px;margin-bottom:.25rem;">
                @endif
                <h1 class="portal-card-title">Registration successful</h1>
                <p class="portal-card-sub">{{ $program->title }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
