@extends('layouts.portal')

@section('title', 'State Kalolsavam — ' . $school->name)

@section('content')
<div class="portal-wrap">
    <div class="portal-page" style="max-width:44rem;">

        <div class="portal-card">
            <div class="portal-card-header" style="flex-direction:column;align-items:flex-start;gap:.25rem;">
                <p class="portal-card-sub" style="text-transform:uppercase;letter-spacing:.06em;font-size:.7rem;">
                    {{ $school->sahodaya->program->title ?? 'State Kalolsavam' }} · {{ $school->sahodaya->name }}
                </p>
                <h1 class="portal-card-title">{{ $school->name }}</h1>
                <p class="portal-card-sub">Add each of your qualified students below, one item at a time.</p>
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

                <form method="POST" action="{{ route('state.external.school.entries.store', $school->access_code) }}" class="portal-form">
                    @csrf
                    <p class="portal-form-section-title" style="margin-top:0;">Add a student</p>
                    <div class="field-grid field-grid-2">
                        <div class="field-span-2">
                            <label class="portal-label" for="item_code">Item <span class="portal-required">*</span></label>
                            <select id="item_code" name="item_code" class="portal-input portal-select @error('item_code') is-error @enderror" required>
                                <option value="">Select item</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->item_code }}" {{ old('item_code') === $item->item_code ? 'selected' : '' }}>
                                        {{ $item->item_code }} — {{ $item->title }}@if($item->class_group) ({{ strtoupper($item->class_group) }})@endif
                                    </option>
                                @endforeach
                            </select>
                            @error('item_code')<p class="portal-error">{{ $message }}</p>@enderror
                        </div>
                        <div class="field-span-2">
                            <label class="portal-label" for="student_name">Student name <span class="portal-required">*</span></label>
                            <input id="student_name" name="student_name" type="text" value="{{ old('student_name') }}" class="portal-input @error('student_name') is-error @enderror" required>
                            @error('student_name')<p class="portal-error">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="portal-label" for="class_name">Class <span class="portal-optional">(optional)</span></label>
                            <input id="class_name" name="class_name" type="text" value="{{ old('class_name') }}" class="portal-input" placeholder="e.g. VIII">
                        </div>
                        <div>
                            <label class="portal-label" for="position">Sahodaya position <span class="portal-optional">(optional)</span></label>
                            <select id="position" name="position" class="portal-input portal-select">
                                <option value="">—</option>
                                <option value="1" {{ old('position') === '1' ? 'selected' : '' }}>1st</option>
                                <option value="2" {{ old('position') === '2' ? 'selected' : '' }}>2nd</option>
                                <option value="3" {{ old('position') === '3' ? 'selected' : '' }}>3rd</option>
                            </select>
                        </div>
                    </div>
                    <div class="portal-form-actions">
                        <span></span>
                        <div class="portal-form-actions-end">
                            <button type="submit" class="portal-btn portal-btn-primary">Add student</button>
                        </div>
                    </div>
                </form>

                <p class="portal-form-section-title">Your entries</p>

                @if($entries->isEmpty())
                    <p class="portal-hint" style="margin-top:.5rem;">No students added yet.</p>
                @else
                    <div style="overflow-x:auto;margin-top:.75rem;">
                        <table style="width:100%;border-collapse:collapse;font-size:.8125rem;">
                            <thead>
                                <tr style="text-align:left;color:#64748b;border-bottom:1px solid #e2e8f0;">
                                    <th style="padding:.5rem .25rem;">Item</th>
                                    <th style="padding:.5rem .25rem;">Student</th>
                                    <th style="padding:.5rem .25rem;">Class</th>
                                    <th style="padding:.5rem .25rem;">Pos.</th>
                                    <th style="padding:.5rem .25rem;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($entries as $entry)
                                <tr style="border-bottom:1px solid #f1f5f9;">
                                    <td style="padding:.5rem .25rem;">{{ $entry->item_name ?? $entry->item_code }}</td>
                                    <td style="padding:.5rem .25rem;font-weight:600;color:var(--navy-900);">{{ $entry->student_name }}</td>
                                    <td style="padding:.5rem .25rem;color:#64748b;">{{ $entry->class_name }}</td>
                                    <td style="padding:.5rem .25rem;color:#64748b;">{{ $entry->position ?? '—' }}</td>
                                    <td style="padding:.5rem .25rem;text-align:right;">
                                        @if($entry->intake?->status === 'draft')
                                        <form method="POST" action="{{ route('state.external.school.entries.destroy', [$school->access_code, $entry->id]) }}"
                                              onsubmit="return confirm('Remove {{ $entry->student_name }} from {{ $entry->item_name ?? $entry->item_code }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" style="background:none;border:none;color:#dc2626;font-size:.75rem;font-weight:600;cursor:pointer;">Remove</button>
                                        </form>
                                        @else
                                        <span class="portal-hint" style="margin:0;">Submitted</span>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <p class="portal-hint" style="margin-top:1rem;">
                    Your Sahodaya coordinator reviews and submits all schools' entries together. Once submitted, entries can no longer be edited here — contact your coordinator if something needs to change.
                </p>

            </div>
        </div>

        <p class="portal-footer-note">
            Keep this link and your access code ({{ $school->access_code }}) safe — anyone with it can manage your entries.
        </p>
    </div>
</div>
@endsection
