@extends('layouts.portal')

@section('title', 'Attendance — '.$program->title)

@section('content')
<div class="portal-wrap">
    <div class="portal-page" style="max-width:32rem;">
        <a href="/" class="portal-back">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back
        </a>

        <div class="portal-card">
            <div class="portal-card-header" style="flex-direction:column;align-items:flex-start;gap:.25rem;">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="" style="height:40px;margin-bottom:.35rem;">
                @endif
                <p class="portal-card-sub" style="text-transform:uppercase;letter-spacing:.06em;font-size:.7rem;">{{ $sahodaya->name }}</p>
                <h1 class="portal-card-title">Attendance check-in</h1>
                <p class="portal-card-sub">{{ $program->title }}</p>
                @if($session)
                    <p class="portal-card-sub" style="margin-top:.35rem;">
                        <strong>{{ $session->title }}</strong>
                        @if($session->scheduled_at) · {{ $session->scheduled_at->format('d M Y H:i') }}@endif
                    </p>
                @endif
            </div>
            <div class="portal-card-body">
                @if(session('success'))
                    <div class="portal-alert portal-alert-success" style="margin-bottom:1rem;">{{ session('success') }}</div>
                @endif
                @if($errors->any())
                    <div class="portal-alert portal-alert-error" style="margin-bottom:1rem;">
                        <ul class="text-sm list-disc pl-4">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST"
                      action="{{ $mode === 'session' ? route('tenant.training.attendance.store', $token) : route('tenant.training.attendance.program.store', $token) }}"
                      class="portal-form">
                    @csrf
                    @if($mode === 'program')
                        <div>
                            <label class="portal-label" for="session_id">Session <span class="portal-required">*</span></label>
                            <select id="session_id" name="session_id" class="portal-input portal-select" required>
                                <option value="">Select session</option>
                                @foreach($program->sessions as $s)
                                    <option value="{{ $s->id }}">{{ $s->title }}@if($s->scheduled_at) ({{ $s->scheduled_at->format('d M Y') }})@endif</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="portal-label" for="lookup">Email, mobile, or registration ID <span class="portal-required">*</span></label>
                        <input id="lookup" class="portal-input" name="lookup" value="{{ old('lookup') }}" required maxlength="150">
                    </div>
                    <div class="portal-form-actions">
                        <button type="submit" class="portal-btn portal-btn-primary">Mark present</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
