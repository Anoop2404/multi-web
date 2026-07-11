@extends('layouts.portal')

@section('title', 'Register — '.$program->title)

@section('content')
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
                    'eyebrow' => 'Teacher training',
                    'tagline' => $program->title,
                    'motto'   => $program->venue,
                    'showContacts' => false,
                ])
                <ul class="portal-steps">
                    <li>Fill your details and select your school</li>
                    <li>Unverified teachers can register for this programme</li>
                    <li>Attend the training — fees are collected at the venue</li>
                </ul>
            </div>

            <div class="portal-panel portal-panel--form">
                <div class="portal-panel-intro">
                    <h2 class="portal-panel-title">Training registration</h2>
                    <p class="portal-panel-sub">
                        {{ $program->title }}
                        @if($program->start_date)
                            · {{ $program->start_date->format('d M Y') }}
                            @if($program->end_date && ! $program->end_date->equalTo($program->start_date))
                                – {{ $program->end_date->format('d M Y') }}
                            @endif
                        @endif
                    </p>
                </div>

                @if(! $open)
                    <div class="portal-alert portal-alert-error">
                        <p class="font-semibold">Registration is closed</p>
                        <p class="text-sm mt-1">The QR registration window for this programme has ended or is not yet open.</p>
                    </div>
                @else
                    @if($errors->any())
                        <div class="portal-alert portal-alert-error">
                            <p class="font-semibold">Please correct the following:</p>
                            <ul class="mt-2 space-y-1 text-sm list-disc pl-4">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('tenant.training.register.store', $token) }}" enctype="multipart/form-data" class="portal-form">
                        @csrf

                        <div>
                            <label class="portal-label" for="name">Full name <span class="portal-required">*</span></label>
                            <input id="name" class="portal-input" name="name" value="{{ old('name') }}" required maxlength="150">
                        </div>

                        <div>
                            <label class="portal-label" for="email">Email <span class="portal-required">*</span></label>
                            <input id="email" type="email" class="portal-input" name="email" value="{{ old('email') }}" required maxlength="150">
                        </div>

                        <div>
                            <label class="portal-label" for="phone">Phone <span class="portal-optional">(optional)</span></label>
                            <input id="phone" class="portal-input" name="phone" value="{{ old('phone') }}" maxlength="20">
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                            <div>
                                <label class="portal-label" for="dob">Date of birth <span class="portal-optional">(optional)</span></label>
                                <input id="dob" type="date" class="portal-input" name="dob" value="{{ old('dob') }}">
                            </div>
                            <div>
                                <label class="portal-label" for="gender">Gender <span class="portal-required">*</span></label>
                                <select id="gender" class="portal-input portal-select" name="gender" required>
                                    <option value="">Select gender</option>
                                    <option value="male" @selected(old('gender')==='male')>Male</option>
                                    <option value="female" @selected(old('gender')==='female')>Female</option>
                                    <option value="other" @selected(old('gender')==='other')>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="trn-school-picker" data-school-picker>
                            <label class="portal-label" for="school_search">School <span class="portal-required">*</span></label>
                            <input type="hidden" name="school_id" id="school_id" value="{{ old('school_id') }}">
                            <input type="search" id="school_search" class="portal-input" placeholder="Search school name, affiliation no., or school code…" autocomplete="off" aria-autocomplete="list" aria-controls="school_results">
                            <div id="school_results" class="trn-school-results" role="listbox" hidden></div>
                            <p id="school_selected" class="portal-field-hint"></p>
                            @if(empty($schools))
                                <p class="portal-field-hint">No member schools found. Enter your school manually below.</p>
                            @endif
                            <details class="trn-manual-school">
                                <summary>School not listed? Enter manually</summary>
                                <div class="portal-form" style="margin-top:.75rem;">
                                    <input class="portal-input" name="manual_school_name" value="{{ old('manual_school_name') }}" placeholder="School name">
                                    <input class="portal-input" name="manual_school_code" value="{{ old('manual_school_code') }}" placeholder="Affiliation no. (optional)">
                                </div>
                            </details>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                            <div>
                                <label class="portal-label" for="teaching_type_id">Teacher category <span class="portal-required">*</span></label>
                                <select id="teaching_type_id" class="portal-input portal-select" name="teaching_type_id" required>
                                    <option value="">Select category</option>
                                    @foreach($teachingTypes as $type)
                                        <option value="{{ $type['id'] }}" @selected((string) old('teaching_type_id') === (string) $type['id'])>
                                            {{ $type['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                                @if(empty($teachingTypes))
                                    <p class="portal-field-hint">No teacher categories configured. Ask Sahodaya to add them under Membership → Settings → Teaching Types.</p>
                                @endif
                            </div>
                            <div>
                                <label class="portal-label" for="designation_id">Designation <span class="portal-optional">(optional)</span></label>
                                <select id="designation_id" class="portal-input portal-select" name="designation_id">
                                    <option value="">—</option>
                                    @foreach($designations as $d)
                                        <option value="{{ $d['id'] }}" @selected((string) old('designation_id') === (string) $d['id'])>
                                            {{ $d['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="portal-label" for="department">Department <span class="portal-optional">(optional)</span></label>
                            <input id="department" class="portal-input" name="department" value="{{ old('department') }}" maxlength="120">
                        </div>

                        <div>
                            <label class="portal-label" for="experience">Experience (years) <span class="portal-optional">(optional)</span></label>
                            <input id="experience" type="number" class="portal-input" name="experience" value="{{ old('experience') }}" min="0" max="50">
                        </div>

                        <div>
                            <label class="portal-label" for="photo">Photo <span class="portal-optional">(optional)</span></label>
                            <input id="photo" type="file" class="portal-input" name="photo" accept="image/*">
                        </div>

                        <label class="trn-consent">
                            <input type="checkbox" name="consent" value="1" required @checked(old('consent'))>
                            <span>I consent to my details being used for this training programme registration and related certificates.</span>
                        </label>

                        <div class="portal-form-actions">
                            <button type="submit" class="portal-btn portal-btn-primary">Submit registration</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
    .trn-school-picker { position: relative; }
    .trn-school-results {
        position: absolute; z-index: 20; left: 0; right: 0; top: calc(100% - .15rem);
        margin-top: .15rem; background: #fff; border: 1.5px solid #e2e8f0;
        border-radius: .75rem; max-height: 220px; overflow: auto;
        box-shadow: 0 12px 28px rgba(15,23,42,.12);
    }
    .trn-school-results button {
        display: block; width: 100%; text-align: left;
        padding: .65rem .9rem; background: transparent; border: 0;
        border-bottom: 1px solid #f1f5f9; color: var(--navy-900);
        cursor: pointer; font-size: .8125rem; font-family: inherit; line-height: 1.35;
    }
    .trn-school-results button:hover,
    .trn-school-results button.is-active { background: #f8fafc; }
    .trn-school-results .trn-empty {
        padding: .75rem .9rem; font-size: .8125rem; color: #64748b;
    }
    .trn-manual-school { margin-top: .65rem; font-size: .8125rem; color: #475569; }
    .trn-manual-school summary { cursor: pointer; font-weight: 600; color: var(--navy-700); }
    .trn-consent {
        display: flex; gap: .65rem; align-items: flex-start;
        font-size: .8125rem; color: #475569; line-height: 1.45;
    }
    .trn-consent input { margin-top: .2rem; }
</style>
<script>
(() => {
    const schools = @json($schools ?? []);
    const search = document.getElementById('school_search');
    const results = document.getElementById('school_results');
    const schoolId = document.getElementById('school_id');
    const selected = document.getElementById('school_selected');
    if (!search || !results || !schoolId) return;

    let activeIndex = -1;

    function labelFor(school) {
        if (school.label) return school.label;
        const parts = [school.affiliation, school.school_code].filter(Boolean);
        return parts.length ? `${school.name} (${parts.join(' · ')})` : school.name;
    }

    function selectedHint(school) {
        const bits = [];
        if (school.affiliation) bits.push(`Affiliation ${school.affiliation}`);
        if (school.school_code) bits.push(`Code ${school.school_code}`);
        return bits.length ? `Selected · ${bits.join(' · ')}` : 'Selected';
    }

    function pick(school) {
        schoolId.value = school.id;
        search.value = labelFor(school);
        selected.textContent = selectedHint(school);
        results.hidden = true;
        activeIndex = -1;
    }

    function render(list) {
        results.innerHTML = '';
        if (!list.length) {
            results.innerHTML = '<div class="trn-empty">No matching schools</div>';
            results.hidden = false;
            return;
        }
        list.slice(0, 40).forEach((school, index) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.role = 'option';
            btn.dataset.index = String(index);
            btn.textContent = labelFor(school);
            btn.addEventListener('mousedown', (e) => {
                e.preventDefault();
                pick(school);
            });
            results.appendChild(btn);
        });
        results.hidden = false;
        activeIndex = -1;
    }

    function filter(q) {
        const needle = q.trim().toLowerCase();
        if (!needle) return schools;
        return schools.filter((school) => {
            const hay = [
                school.name || '',
                school.affiliation || '',
                school.school_code || '',
                school.label || '',
            ].join(' ').toLowerCase();
            return hay.includes(needle);
        });
    }

    const initial = schools.find((s) => s.id === schoolId.value);
    if (initial) {
        search.value = labelFor(initial);
        selected.textContent = selectedHint(initial);
    }

    search.addEventListener('focus', () => render(filter(search.value)));
    search.addEventListener('input', () => {
        schoolId.value = '';
        selected.textContent = '';
        render(filter(search.value));
    });
    search.addEventListener('keydown', (e) => {
        const buttons = [...results.querySelectorAll('button')];
        if (!buttons.length) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            activeIndex = Math.min(activeIndex + 1, buttons.length - 1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            activeIndex = Math.max(activeIndex - 1, 0);
        } else if (e.key === 'Enter' && activeIndex >= 0) {
            e.preventDefault();
            buttons[activeIndex].dispatchEvent(new Event('mousedown'));
            return;
        } else if (e.key === 'Escape') {
            results.hidden = true;
            return;
        } else {
            return;
        }
        buttons.forEach((btn, i) => btn.classList.toggle('is-active', i === activeIndex));
        buttons[activeIndex]?.scrollIntoView({ block: 'nearest' });
    });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('[data-school-picker]')) {
            results.hidden = true;
        }
    });
})();
</script>
@endsection
