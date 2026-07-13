@extends('layouts.portal')

@section('title', 'School Registration — ' . $sahodaya->name)

@section('content')
@php
    $hasSchoolStep = collect($fields)->where('group', 'school')->where('enabled', true)->isNotEmpty()
        || ($fields['school_name']['enabled'] ?? true);
    $hasLeadershipStep = collect($fields)->where('group', 'leadership')->where('enabled', true)->isNotEmpty();
    $hasPrincipalStep = collect($fields)->whereIn('group', ['principal', 'account'])->where('enabled', true)->isNotEmpty();
    $hasStep2 = $hasPrincipalStep || $hasLeadershipStep;
    $threeStep = $hasSchoolStep && $hasPrincipalStep && $hasLeadershipStep;
    $twoStep = $hasSchoolStep && $hasStep2 && ! $threeStep;
    $totalSteps = $threeStep ? 3 : ($twoStep ? 2 : 1);

    $step1Fields = ['school_name', 'school_email', 'school_prefix', 'school_category', 'cbse_affiliation', 'phone', 'highest_class', 'website', 'address', 'district'];
    $step2Fields = [
        'principal_name', 'principal_email', 'principal_phone',
        'password', 'password_confirmation',
    ];
    $step3Fields = [
        'vice_principal_name', 'vice_principal_email', 'vice_principal_phone',
        'event_coordinator_name', 'event_coordinator_email', 'event_coordinator_phone',
    ];
    $errorStep = 1;
    if ($errors->any()) {
        if ($errors->hasAny($step1Fields)) {
            $errorStep = 1;
        } elseif ($threeStep && $errors->hasAny($step3Fields)) {
            $errorStep = 3;
        } else {
            $errorStep = $twoStep || $threeStep ? 2 : 1;
        }
    }
    $initialStep = ($twoStep || $threeStep) ? $errorStep : 1;
@endphp
<div class="portal-wrap">
    <div class="portal-page">

        <a href="/" class="portal-back">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Back
        </a>

        <div class="portal-shell portal-shell--form">

            <div class="portal-brand">
                @include('partials.portal-brand', [
                    'tenant'  => $sahodaya,
                    'logoUrl' => $logoUrl ?? null,
                    'eyebrow' => $eyebrow ?? null,
                    'tagline' => $tagline ?? null,
                    'motto'   => $motto ?? null,
                    'showContacts' => false,
                ])
            </div>

            <div class="portal-panel portal-panel--form">
                <div class="portal-panel-intro">
                    <h2 class="portal-panel-title">School Registration</h2>
                </div>

                @if(session('success'))
                <div class="portal-alert portal-alert-success">{{ session('success') }}</div>
                @endif

                @if($errors->any())
                <div class="portal-alert portal-alert-error">
                    <p class="font-semibold">Please correct the following:</p>
                    <ul class="mt-2 space-y-1 text-sm list-disc pl-4">
                        @foreach($errors->all() as $message)
                        <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <form method="POST" action="{{ route('school-register.store') }}" class="portal-form" id="school-register-form"
                      data-multi-step="{{ ($twoStep || $threeStep) ? '1' : '0' }}"
                      data-total-steps="{{ $totalSteps }}"
                      data-initial-step="{{ $initialStep }}">
                    @csrf

                    @if($twoStep || $threeStep)
                    <div class="portal-form-steps" aria-label="Registration progress">
                        <div class="portal-form-step {{ $initialStep === 1 ? 'is-active' : 'is-done' }}" data-step-indicator="1">
                            <span class="portal-form-step-num">1</span>
                            <span>School</span>
                        </div>
                        <div class="portal-form-step-line {{ $initialStep > 1 ? 'is-done' : '' }}"></div>
                        <div class="portal-form-step {{ $initialStep === 2 ? 'is-active' : ($initialStep > 2 ? 'is-done' : '') }}" data-step-indicator="2">
                            <span class="portal-form-step-num">2</span>
                            <span>Principal</span>
                        </div>
                        @if($threeStep)
                        <div class="portal-form-step-line {{ $initialStep > 2 ? 'is-done' : '' }}"></div>
                        <div class="portal-form-step {{ $initialStep === 3 ? 'is-active' : '' }}" data-step-indicator="3">
                            <span class="portal-form-step-num">3</span>
                            <span>Leadership</span>
                        </div>
                        @endif
                    </div>
                    @endif

                    @if($hasSchoolStep)
                    <div class="portal-form-step-panel {{ $initialStep === 1 ? 'is-active' : '' }}" data-step-panel="1">
                        <div class="field-grid field-grid-2">
                            <div class="field-span-2">
                                <label class="portal-label" for="school_name">School Name <span class="portal-required">*</span></label>
                                <input id="school_name" name="school_name" type="text"
                                       value="{{ old('school_name') }}"
                                       class="portal-input @error('school_name') is-error @enderror"
                                       placeholder="{{ $fields['school_name']['placeholder'] ?? '' }}"
                                       required>
                                @error('school_name')<p class="portal-error">{{ $message }}</p>@enderror
                            </div>

                            @if($fields['school_email']['enabled'] ?? false)
                            <div class="field-span-2">
                                <label class="portal-label" for="school_email">{{ $fields['school_email']['label'] }}
                                    <span class="portal-required">*</span>
                                </label>
                                <input id="school_email" name="school_email" type="email"
                                       value="{{ old('school_email') }}"
                                       class="portal-input @error('school_email') is-error @enderror"
                                       placeholder="{{ $fields['school_email']['placeholder'] }}"
                                       required>
                                @if($fields['school_email']['hint'] ?? null)
                                <p class="portal-hint">{{ $fields['school_email']['hint'] }}</p>
                                @endif
                                @error('school_email')<p class="portal-error">{{ $message }}</p>@enderror
                            </div>
                            @endif

                            @if($fields['school_prefix']['enabled'] ?? false)
                            <div class="field-span-2">
                                <label class="portal-label" for="school_prefix">{{ $fields['school_prefix']['label'] }}
                                    <span class="portal-required">*</span>
                                </label>
                                <input id="school_prefix" name="school_prefix" type="text"
                                       value="{{ old('school_prefix') }}"
                                       class="portal-input @error('school_prefix') is-error @enderror uppercase"
                                       placeholder="{{ $fields['school_prefix']['placeholder'] }}"
                                       maxlength="10"
                                       pattern="[A-Za-z0-9]+"
                                       required>
                                @if($fields['school_prefix']['hint'] ?? null)
                                <p class="portal-hint">{{ $fields['school_prefix']['hint'] }}</p>
                                @endif
                                @error('school_prefix')<p class="portal-error">{{ $message }}</p>@enderror
                            </div>
                            @endif

                            @if($fields['school_category']['enabled'] ?? false)
                            <div class="field-span-2">
                                <label class="portal-label">{{ $fields['school_category']['label'] }}
                                    <span class="portal-required">*</span>
                                </label>
                                @if(!empty($fields['school_category']['hint']))
                                <p class="portal-hint">{{ $fields['school_category']['hint'] }}</p>
                                @endif
                                <div class="mt-2 space-y-2">
                                    @foreach(($fields['school_category']['options'] ?? []) as $value => $label)
                                    <label class="flex items-start gap-2 text-sm">
                                        <input type="radio" name="school_category" value="{{ $value }}"
                                               class="mt-1"
                                               {{ old('school_category', 'affiliated') === $value ? 'checked' : '' }}
                                               required
                                               onchange="document.getElementById('cbse_affiliation') && (document.getElementById('cbse_affiliation').required = this.value !== 'non_affiliated'); document.getElementById('cbse-aff-req') && (document.getElementById('cbse-aff-req').style.display = this.value === 'non_affiliated' ? 'none' : '');">
                                        <span>{{ $label }}</span>
                                    </label>
                                    @endforeach
                                </div>
                                @error('school_category')<p class="portal-error">{{ $message }}</p>@enderror
                            </div>
                            @endif

                            @if($fields['cbse_affiliation']['enabled'] ?? false)
                            <div>
                                <label class="portal-label" for="cbse_affiliation">{{ $fields['cbse_affiliation']['label'] }}
                                    <span id="cbse-aff-req" class="portal-required" @if(old('school_category') === 'non_affiliated') style="display:none" @endif>*</span>
                                </label>
                                <input id="cbse_affiliation" name="cbse_affiliation" type="text"
                                       value="{{ old('cbse_affiliation') }}"
                                       class="portal-input @error('cbse_affiliation') is-error @enderror"
                                       placeholder="{{ $fields['cbse_affiliation']['placeholder'] }}"
                                       @if(old('school_category') !== 'non_affiliated') required @endif>
                                @error('cbse_affiliation')<p class="portal-error">{{ $message }}</p>@enderror
                            </div>
                            @endif

                            @if($fields['phone']['enabled'] ?? false)
                            <div>
                                <label class="portal-label" for="phone">{{ $fields['phone']['label'] }}
                                    @if($fields['phone']['required'])<span class="portal-required">*</span>@endif
                                </label>
                                <input id="phone" name="phone" type="tel"
                                       value="{{ old('phone') }}"
                                       class="portal-input @error('phone') is-error @enderror"
                                       placeholder="{{ $fields['phone']['placeholder'] }}"
                                       @if($fields['phone']['required']) required @endif>
                                @error('phone')<p class="portal-error">{{ $message }}</p>@enderror
                            </div>
                            @endif

                            @if($fields['highest_class']['enabled'] ?? false)
                            <div>
                                <label class="portal-label" for="highest_class">{{ $fields['highest_class']['label'] }}
                                    @if($fields['highest_class']['required'])<span class="portal-required">*</span>@endif
                                </label>
                                <select id="highest_class" name="highest_class"
                                        class="portal-input portal-select @error('highest_class') is-error @enderror"
                                        @if($fields['highest_class']['required']) required @endif>
                                    <option value="">—</option>
                                    @foreach($highestClassOptions as $value => $label)
                                    <option value="{{ $value }}" @selected(old('highest_class') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('highest_class')<p class="portal-error">{{ $message }}</p>@enderror
                            </div>
                            @endif

                            @if($fields['website']['enabled'] ?? false)
                            <div>
                                <label class="portal-label" for="website">{{ $fields['website']['label'] }}
                                    @if($fields['website']['required'])<span class="portal-required">*</span>@endif
                                </label>
                                <input id="website" name="website" type="text"
                                       value="{{ old('website') }}"
                                       class="portal-input @error('website') is-error @enderror"
                                       placeholder="{{ $fields['website']['placeholder'] }}"
                                       @if($fields['website']['required']) required @endif>
                                @error('website')<p class="portal-error">{{ $message }}</p>@enderror
                            </div>
                            @endif

                            @if($fields['address']['enabled'] ?? false)
                            <div class="field-span-2">
                                <label class="portal-label" for="address">{{ $fields['address']['label'] }}
                                    @if($fields['address']['required'])<span class="portal-required">*</span>@endif
                                </label>
                                <input id="address" name="address" type="text"
                                       value="{{ old('address') }}"
                                       class="portal-input @error('address') is-error @enderror"
                                       placeholder="{{ $fields['address']['placeholder'] }}"
                                       @if($fields['address']['required']) required @endif>
                                @error('address')<p class="portal-error">{{ $message }}</p>@enderror
                            </div>
                            @endif

                            @if($fields['district']['enabled'] ?? false)
                            <div>
                                <label class="portal-label" for="district">{{ $fields['district']['label'] }}
                                    @if($fields['district']['required'])<span class="portal-required">*</span>@endif
                                </label>
                                <input id="district" name="district" type="text"
                                       value="{{ old('district') }}"
                                       class="portal-input @error('district') is-error @enderror"
                                       placeholder="{{ $fields['district']['placeholder'] }}"
                                       @if($fields['district']['required']) required @endif>
                                @error('district')<p class="portal-error">{{ $message }}</p>@enderror
                            </div>
                            @endif
                        </div>

                        @if($twoStep || $threeStep)
                        <div class="portal-form-actions">
                            <a href="/login" class="portal-form-link">Sign in</a>
                            <div class="portal-form-actions-end">
                                <button type="button" class="portal-btn portal-btn-primary" data-step-next="2">Continue</button>
                            </div>
                        </div>
                        @endif
                    </div>
                    @endif

                    @if($hasStep2)
                    <div class="portal-form-step-panel {{ $initialStep === 2 ? 'is-active' : '' }}" data-step-panel="2">
                        <div class="field-grid field-grid-2">
                            @if($hasPrincipalStep)
                            @if(($fields['principal_name']['enabled'] ?? false) || ($fields['principal_email']['enabled'] ?? false) || ($fields['principal_phone']['enabled'] ?? false))
                            <div class="field-span-2">
                                <p class="text-sm font-semibold text-slate-800">Principal</p>
                            </div>
                            @endif

                            @if($fields['principal_name']['enabled'] ?? false)
                            <div>
                                <label class="portal-label" for="principal_name">{{ $fields['principal_name']['label'] }}
                                    @if($fields['principal_name']['required'])<span class="portal-required">*</span>@endif
                                </label>
                                <input id="principal_name" name="principal_name" type="text"
                                       value="{{ old('principal_name') }}"
                                       class="portal-input @error('principal_name') is-error @enderror"
                                       placeholder="{{ $fields['principal_name']['placeholder'] }}"
                                       @if($fields['principal_name']['required']) required @endif>
                                @error('principal_name')<p class="portal-error">{{ $message }}</p>@enderror
                            </div>
                            @endif

                            @if($fields['principal_email']['enabled'] ?? false)
                            <div>
                                <label class="portal-label" for="principal_email">{{ $fields['principal_email']['label'] }}
                                    @if($fields['principal_email']['required'])<span class="portal-required">*</span>@endif
                                </label>
                                <input id="principal_email" name="principal_email" type="email"
                                       value="{{ old('principal_email') }}"
                                       class="portal-input @error('principal_email') is-error @enderror"
                                       placeholder="{{ $fields['principal_email']['placeholder'] }}"
                                       @if($fields['principal_email']['required']) required @endif>
                                @error('principal_email')<p class="portal-error">{{ $message }}</p>@enderror
                            </div>
                            @endif

                            @if($fields['principal_phone']['enabled'] ?? false)
                            <div>
                                <label class="portal-label" for="principal_phone">{{ $fields['principal_phone']['label'] }}
                                    @if($fields['principal_phone']['required'])<span class="portal-required">*</span>@endif
                                </label>
                                <input id="principal_phone" name="principal_phone" type="tel"
                                       value="{{ old('principal_phone') }}"
                                       class="portal-input @error('principal_phone') is-error @enderror"
                                       placeholder="{{ $fields['principal_phone']['placeholder'] }}"
                                       @if($fields['principal_phone']['required']) required @endif>
                                @error('principal_phone')<p class="portal-error">{{ $message }}</p>@enderror
                            </div>
                            @endif
                            @endif

                            @if(! $threeStep && $hasLeadershipStep)
                            @include('public.partials.school-application-leadership-fields', ['fields' => $fields])
                            @endif

                            @if($fields['password']['enabled'] ?? false)
                            <div>
                                <label class="portal-label" for="password">{{ $fields['password']['label'] }}
                                    @if($fields['password']['required'])<span class="portal-required">*</span>@endif
                                </label>
                                <input id="password" name="password" type="password"
                                       class="portal-input @error('password') is-error @enderror"
                                       placeholder="{{ $fields['password']['placeholder'] }}"
                                       @if($fields['password']['required']) required @endif>
                                @error('password')<p class="portal-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="portal-label" for="password_confirmation">{{ $fields['password_confirmation']['label'] }}
                                    @if($fields['password_confirmation']['required'])<span class="portal-required">*</span>@endif
                                </label>
                                <input id="password_confirmation" name="password_confirmation" type="password"
                                       class="portal-input"
                                       placeholder="{{ $fields['password_confirmation']['placeholder'] }}"
                                       @if($fields['password_confirmation']['required']) required @endif>
                            </div>
                            @endif
                        </div>

                        <div class="portal-form-actions">
                            @if($twoStep || $threeStep)
                            <button type="button" class="portal-btn portal-btn-secondary" data-step-back="1">Back</button>
                            @else
                            <a href="/login" class="portal-form-link">Sign in</a>
                            @endif
                            <div class="portal-form-actions-end">
                                @if($threeStep)
                                <button type="button" class="portal-btn portal-btn-primary" data-step-next="3">Continue</button>
                                @else
                                <button type="submit" class="portal-btn portal-btn-primary">Register</button>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if($threeStep)
                    <div class="portal-form-step-panel {{ $initialStep === 3 ? 'is-active' : '' }}" data-step-panel="3">
                        <div class="field-grid field-grid-2">
                            @include('public.partials.school-application-leadership-fields', ['fields' => $fields])
                        </div>
                        <div class="portal-form-actions">
                            <button type="button" class="portal-btn portal-btn-secondary" data-step-back="2">Back</button>
                            <div class="portal-form-actions-end">
                                <button type="submit" class="portal-btn portal-btn-primary">Register</button>
                            </div>
                        </div>
                    </div>
                    @endif
                    @elseif(! $twoStep && ! $threeStep && $hasSchoolStep)
                    <div class="portal-form-actions">
                        <a href="/login" class="portal-form-link">Sign in</a>
                        <div class="portal-form-actions-end">
                            <button type="submit" class="portal-btn portal-btn-primary">Register</button>
                        </div>
                    </div>
                    @endif
                </form>
            </div>

        </div>
    </div>
</div>

<script>
(function () {
    const form = document.getElementById('school-register-form');
    if (!form) return;

    const multiStep = form.dataset.multiStep === '1';
    const totalSteps = parseInt(form.dataset.totalSteps || '1', 10);
    let currentStep = parseInt(form.dataset.initialStep || '1', 10);

    function setStep(step) {
        currentStep = step;
        form.querySelectorAll('[data-step-panel]').forEach((panel) => {
            panel.classList.toggle('is-active', parseInt(panel.dataset.stepPanel, 10) === step);
        });
        form.querySelectorAll('[data-step-indicator]').forEach((indicator) => {
            const n = parseInt(indicator.dataset.stepIndicator, 10);
            indicator.classList.toggle('is-active', n === step);
            indicator.classList.toggle('is-done', n < step);
        });
        form.querySelectorAll('.portal-form-step-line').forEach((line, index) => {
            line.classList.toggle('is-done', step > index + 1);
        });
    }

    function validatePanel(step) {
        const panel = form.querySelector(`[data-step-panel="${step}"]`);
        if (!panel) return true;

        for (const field of panel.querySelectorAll('input, select, textarea')) {
            if (field.offsetParent === null) continue;
            if (!field.checkValidity()) {
                field.reportValidity();
                field.focus();
                return false;
            }
        }
        return true;
    }

    form.querySelectorAll('[data-step-next]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const next = parseInt(btn.dataset.stepNext, 10);
            if (validatePanel(currentStep)) setStep(next);
        });
    });

    form.querySelectorAll('[data-step-back]').forEach((btn) => {
        btn.addEventListener('click', () => setStep(parseInt(btn.dataset.stepBack, 10)));
    });

    form.addEventListener('submit', (e) => {
        if (!multiStep) return;
        if (currentStep < totalSteps) {
            e.preventDefault();
            if (validatePanel(currentStep)) setStep(currentStep + 1);
            return;
        }
        if (!validatePanel(currentStep)) {
            e.preventDefault();
        }
    });

    if (multiStep) setStep(currentStep);

    const firstError = form.querySelector('.is-error, .portal-error');
    if (firstError) {
        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
})();
</script>
@endsection
