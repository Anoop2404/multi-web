@extends('layouts.portal')

@section('title', 'School sign in — State Kalolsavam')

@section('content')
<div class="portal-wrap">
    <div class="portal-page" style="max-width:26rem;">

        <div class="portal-card">
            <div class="portal-card-header" style="flex-direction:column;align-items:flex-start;gap:.25rem;">
                <h1 class="portal-card-title">School sign in</h1>
                <p class="portal-card-sub">State Kalolsavam — outside-Sahodaya qualifier intake</p>
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

                <form method="POST" action="{{ route('state.external.school.login.submit') }}" class="portal-form">
                    @csrf
                    <div class="field-grid">
                        <div>
                            <label class="portal-label" for="username">Username</label>
                            <input id="username" name="username" type="text" value="{{ old('username') }}"
                                   class="portal-input @error('username') is-error @enderror" required autofocus>
                        </div>
                        <div>
                            <label class="portal-label" for="password">Password</label>
                            <input id="password" name="password" type="password" class="portal-input" required>
                        </div>
                    </div>
                    <div class="portal-form-actions">
                        <span></span>
                        <div class="portal-form-actions-end">
                            <button type="submit" class="portal-btn portal-btn-primary">Sign in</button>
                        </div>
                    </div>
                </form>

                <p class="portal-hint" style="margin-top:1rem;">
                    Don't have a username and password yet, or lost them? Contact your Sahodaya coordinator, who can request a reset from the State office.
                </p>

            </div>
        </div>
    </div>
</div>
@endsection
