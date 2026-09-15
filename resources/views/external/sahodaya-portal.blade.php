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
                @if(session('warning'))
                    <div class="portal-alert portal-alert-error">{{ session('warning') }}</div>
                @endif
                @if(session('importErrors') && count(session('importErrors')))
                    <div class="portal-alert portal-alert-error">
                        <ul class="text-sm list-disc pl-4" style="margin:0;">
                            @foreach(session('importErrors') as $importError)
                                <li>{{ $importError }}</li>
                            @endforeach
                        </ul>
                    </div>
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

                @unless($sahodaya->is_appeal_pool)
                <p class="portal-form-section-title" style="margin-top:0;">Registration fee</p>
                @if($fee && $fee->status === 'verified')
                    <p class="portal-hint" style="margin-top:.5rem;color:#15803d;">Verified — ₹{{ number_format($fee->amount, 2) }} confirmed by the State Kalolsavam office.</p>
                @else
                    @if($fee && $fee->status === 'submitted')
                        <p class="portal-hint" style="margin-top:.5rem;">Submitted — ₹{{ number_format($fee->amount, 2) }}, awaiting State verification.</p>
                    @elseif($fee && $fee->status === 'rejected')
                        <p class="portal-hint" style="margin-top:.5rem;color:#dc2626;">Rejected: {{ $fee->rejection_reason ?? 'no reason given' }}. Please re-submit.</p>
                    @else
                        <p class="portal-hint" style="margin-top:.5rem;">No fee submitted yet. Enter your own amount and upload one proof (bank transfer/DD receipt) covering your whole roster.</p>
                    @endif
                    <form method="POST" action="{{ route('state.external.sahodaya.fee.store', $sahodaya->access_code) }}"
                          enctype="multipart/form-data" class="portal-form" style="margin-top:.75rem;">
                        @csrf
                        <div class="field-grid field-grid-2">
                            <div>
                                <label class="portal-label" for="amount">Amount (₹) <span class="portal-required">*</span></label>
                                <input id="amount" name="amount" type="number" step="0.01" min="0.01" class="portal-input" required>
                            </div>
                            <div>
                                <label class="portal-label" for="proof">Payment proof <span class="portal-required">*</span></label>
                                <input id="proof" name="proof" type="file" accept=".pdf,.jpg,.jpeg,.png" class="portal-input" required>
                            </div>
                        </div>
                        <div class="portal-form-actions">
                            <span></span>
                            <div class="portal-form-actions-end">
                                <button type="submit" class="portal-btn portal-btn-primary">{{ $fee ? 'Re-submit' : 'Submit' }} fee</button>
                            </div>
                        </div>
                    </form>
                @endif
                @endunless

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
                                    <td style="padding:.5rem .25rem;font-weight:600;color:var(--navy-900);">
                                        {{ $school->name }}
                                        @if($school->is_appeal_pool)<span style="font-weight:600;color:#b45309;font-size:.7rem;"> (Appeal)</span>@endif
                                    </td>
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

                <p class="portal-form-section-title">Bulk-upload winners</p>
                <p class="portal-hint" style="margin-top:.5rem;">
                    Upload one spreadsheet with every qualified student across all your schools (columns: school_name, category, student_name, roll_number).
                    Schools not already listed above are created automatically. This is just the roster — register each student to a state item below afterward.
                </p>
                <form method="POST" action="{{ route('state.external.sahodaya.import-winners', $sahodaya->access_code) }}"
                      enctype="multipart/form-data" class="portal-form" style="margin-top:.5rem;">
                    @csrf
                    <div class="field-grid field-grid-2">
                        <div class="field-span-2">
                            <input name="file" type="file" accept=".csv,.txt,.xlsx,.xls" class="portal-input" required>
                        </div>
                    </div>
                    <div class="portal-form-actions">
                        <span></span>
                        <div class="portal-form-actions-end">
                            <button type="submit" class="portal-btn portal-btn-primary">Upload winner list</button>
                        </div>
                    </div>
                </form>

                <p class="portal-form-section-title">Register students to state items</p>
                @if($unassigned->isEmpty())
                    <p class="portal-hint" style="margin-top:.5rem;">No unregistered roster students right now — upload a winner list above, or every uploaded student already has an item.</p>
                @else
                    <p class="portal-hint" style="margin-top:.5rem;">{{ $unassigned->count() }} student(s) uploaded but not yet registered to an item.</p>
                    <form method="POST" action="{{ route('state.external.sahodaya.register-item', $sahodaya->access_code) }}" class="portal-form" style="margin-top:.5rem;">
                        @csrf
                        <div class="field-grid field-grid-2">
                            <div class="field-span-2">
                                <label class="portal-label" for="entry_id">Student <span class="portal-required">*</span></label>
                                <select id="entry_id" name="entry_id" class="portal-input portal-select" required>
                                    <option value="">Select student</option>
                                    @foreach($unassigned as $rosterEntry)
                                        <option value="{{ $rosterEntry->id }}">{{ $rosterEntry->student_name }} — {{ $rosterEntry->school_name }}@if($rosterEntry->class_name) ({{ $rosterEntry->class_name }})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field-span-2">
                                <label class="portal-label" for="item_code">Item <span class="portal-required">*</span></label>
                                <select id="item_code" name="item_code" class="portal-input portal-select" required>
                                    <option value="">Select item</option>
                                    @foreach($items as $item)
                                        <option value="{{ $item->item_code }}">{{ $item->item_code }} — {{ $item->title }}@if($item->class_group) ({{ strtoupper($item->class_group) }})@endif</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="portal-label" for="position">Sahodaya position <span class="portal-optional">(optional)</span></label>
                                <select id="position" name="position" class="portal-input portal-select">
                                    <option value="">—</option>
                                    <option value="1">1st</option>
                                    <option value="2">2nd</option>
                                    <option value="3">3rd</option>
                                </select>
                            </div>
                            <div>
                                <label class="portal-label" for="grade">Grade <span class="portal-optional">(optional)</span></label>
                                <input id="grade" name="grade" type="text" class="portal-input" placeholder="e.g. A">
                            </div>
                        </div>
                        <div class="portal-form-actions">
                            <span></span>
                            <div class="portal-form-actions-end">
                                <button type="submit" class="portal-btn portal-btn-primary">Register to item</button>
                            </div>
                        </div>
                    </form>
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

                @php($registeredEntries = $entries->whereNotNull('item_code'))
                <p class="portal-form-section-title">Registered to items</p>

                @if($registeredEntries->isEmpty())
                    <p class="portal-hint" style="margin-top:.5rem;">
                        Nothing registered to an item yet. Once your roster has students, register them above — they'll show up here before you submit them to the State Kalolsavam office.
                    </p>
                @else
                    @php($entries = $registeredEntries)
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
