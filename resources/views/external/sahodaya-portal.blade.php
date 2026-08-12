@extends('layouts.portal')

@section('title', 'State Kalolsavam — ' . $sahodaya->name)

@section('content')
<div class="portal-wrap">
    <div class="portal-page" style="max-width:52rem;">

        <div class="portal-card">
            <div class="portal-card-header" style="flex-direction:column;align-items:flex-start;gap:.25rem;">
                <p class="portal-card-sub" style="text-transform:uppercase;letter-spacing:.06em;font-size:.7rem;">
                    {{ $sahodaya->program->title ?? 'State Kalolsavam' }}
                </p>
                <h1 class="portal-card-title">{{ $sahodaya->name }}</h1>
                <p class="portal-card-sub">Coordinator portal — add your schools, then each school enters its own qualified students.</p>
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

                <p class="portal-form-section-title">Schools</p>

                @if($schools->isEmpty())
                    <p class="portal-hint" style="margin-top:.5rem;">No schools added yet. Add your first school below — you'll get an access code to hand them so they can enter their own students.</p>
                @else
                    <div style="overflow-x:auto;margin-top:.75rem;">
                        <table style="width:100%;border-collapse:collapse;font-size:.8125rem;">
                            <thead>
                                <tr style="text-align:left;color:#64748b;border-bottom:1px solid #e2e8f0;">
                                    <th style="padding:.5rem .25rem;">School</th>
                                    <th style="padding:.5rem .25rem;">Contact</th>
                                    <th style="padding:.5rem .25rem;">Access code</th>
                                    <th style="padding:.5rem .25rem;">Portal link</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($schools as $school)
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:.5rem .25rem;font-weight:600;color:var(--navy-900);">{{ $school->name }}</td>
                                    <td style="padding:.5rem .25rem;color:#64748b;">{{ $school->contact_name }}@if($school->contact_phone) · {{ $school->contact_phone }}@endif</td>
                                    <td style="padding:.5rem .25rem;font-family:monospace;font-weight:700;color:var(--navy-700);">{{ $school->access_code }}</td>
                                    <td style="padding:.5rem .25rem;">
                                        <a href="{{ route('state.external.school.show', $school->access_code) }}" class="portal-form-link">Open</a>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <form method="POST" action="{{ route('state.external.sahodaya.schools.store', $sahodaya->access_code) }}"
                      class="portal-form" style="margin-top:1.25rem;">
                    @csrf
                    <p class="portal-form-section-title" style="margin-top:0;">Add a school</p>
                    <div class="field-grid field-grid-2">
                        <div class="field-span-2">
                            <label class="portal-label" for="school_name">School name <span class="portal-required">*</span></label>
                            <input id="school_name" name="name" type="text" value="{{ old('name') }}" class="portal-input @error('name') is-error @enderror" required>
                            @error('name')<p class="portal-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="portal-label" for="contact_name">Contact name <span class="portal-optional">(optional)</span></label>
                            <input id="contact_name" name="contact_name" type="text" value="{{ old('contact_name') }}" class="portal-input">
                        </div>
                        <div>
                            <label class="portal-label" for="contact_phone">Contact phone <span class="portal-optional">(optional)</span></label>
                            <input id="contact_phone" name="contact_phone" type="tel" value="{{ old('contact_phone') }}" class="portal-input">
                        </div>
                    </div>
                    <div class="portal-form-actions">
                        <span></span>
                        <div class="portal-form-actions-end">
                            <button type="submit" class="portal-btn portal-btn-primary">Add school</button>
                        </div>
                    </div>
                </form>

                <p class="portal-form-section-title">Entries submitted by your schools</p>

                @if($entries->isEmpty())
                    <p class="portal-hint" style="margin-top:.5rem;">
                        Nothing to review yet. Once schools add their qualified students, they'll show up here before you submit them to the State Kalolsavam office.
                    </p>
                @else
                    <div style="overflow-x:auto;margin-top:.75rem;">
                        <table style="width:100%;border-collapse:collapse;font-size:.8125rem;">
                            <thead>
                                <tr style="text-align:left;color:#64748b;border-bottom:1px solid #e2e8f0;">
                                    <th style="padding:.5rem .25rem;">School</th>
                                    <th style="padding:.5rem .25rem;">Item</th>
                                    <th style="padding:.5rem .25rem;">Student</th>
                                    <th style="padding:.5rem .25rem;">Class</th>
                                    <th style="padding:.5rem .25rem;">Pos.</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($entries as $entry)
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:.5rem .25rem;">{{ $entry->school_name }}</td>
                                    <td style="padding:.5rem .25rem;">{{ $entry->item_name ?? $entry->item_code }}</td>
                                    <td style="padding:.5rem .25rem;font-weight:600;color:var(--navy-900);">{{ $entry->student_name }}</td>
                                    <td style="padding:.5rem .25rem;color:#64748b;">{{ $entry->class_name }}</td>
                                    <td style="padding:.5rem .25rem;color:#64748b;">{{ $entry->position ?? '—' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <form method="POST" action="{{ route('state.external.sahodaya.submit', $sahodaya->access_code) }}" style="margin-top:1rem;"
                          onsubmit="return confirm('Submit {{ $entries->count() }} entr{{ $entries->count() === 1 ? 'y' : 'ies' }} to the State Kalolsavam office? Schools won\'t be able to edit them after this.');">
                        @csrf
                        <div class="portal-form-actions">
                            <p class="portal-hint" style="margin:0;">Check with your schools before submitting — this locks their entries.</p>
                            <div class="portal-form-actions-end">
                                <button type="submit" class="portal-btn portal-btn-primary">Submit to State</button>
                            </div>
                        </div>
                    </form>
                @endif

            </div>
        </div>

        <p class="portal-footer-note">
            Keep this link and your access code ({{ $sahodaya->access_code }}) safe — anyone with it can manage your submission.
        </p>
    </div>
</div>
@endsection
