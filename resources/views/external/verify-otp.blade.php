@extends('layouts.portal')

@section('title', 'Verify your access — State Kalolsavam')

@section('content')
<div class="portal-wrap">
    <div class="portal-page" style="max-width:28rem;">

        <div class="portal-card">
            <div class="portal-card-header" style="flex-direction:column;align-items:flex-start;gap:.25rem;">
                <p class="portal-card-sub" style="text-transform:uppercase;letter-spacing:.06em;font-size:.7rem;">State Kalolsavam</p>
                <h1 class="portal-card-title">Verify it's you</h1>
                <p class="portal-card-sub">{{ $name }} — we'll email a 6-digit code to the registered address before opening your portal.</p>
            </div>
            <div class="portal-card-body">

                @if(session('success'))
                    <div class="portal-alert portal-alert-success">{{ session('success') }}</div>
                @endif
                @if($errors->any())
                    <div class="portal-alert portal-alert-error">
                        <ul class="text-sm list-disc pl-4" style="margin:0;">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ $sendRoute }}" class="portal-form">
                    @csrf
                    <p class="portal-hint" style="margin-top:0;">
                        @if($hasPending)
                            We already sent a code — check your inbox, or request a new one below.
                        @else
                            Code goes to the email on file for {{ $name }}.
                        @endif
                    </p>
                    <div class="portal-form-actions">
                        <span></span>
                        <div class="portal-form-actions-end">
                            <button type="submit" class="portal-btn portal-btn-secondary">{{ $hasPending ? 'Resend code' : 'Send code' }}</button>
                        </div>
                    </div>
                </form>

                <form method="POST" action="{{ $checkRoute }}" class="portal-form" style="margin-top:1rem;">
                    @csrf
                    <p class="portal-form-section-title" style="margin-top:0;">Enter the code</p>
                    <div class="field-grid field-grid-2">
                        <div class="field-span-2">
                            <label class="portal-label" for="otp">6-digit code <span class="portal-required">*</span></label>
                            <input id="otp" name="otp" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                                   autocomplete="one-time-code" class="portal-input @error('otp') is-error @enderror" required>
                            @error('otp')<p class="portal-error">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="portal-form-actions">
                        <span></span>
                        <div class="portal-form-actions-end">
                            <button type="submit" class="portal-btn portal-btn-primary">Verify &amp; continue</button>
                        </div>
                    </div>
                </form>

            </div>
        </div>

        <p class="portal-footer-note">Your access link alone is no longer enough — we now also check that you can read the registered coordinator email before opening the portal.</p>
    </div>
</div>
@endsection
