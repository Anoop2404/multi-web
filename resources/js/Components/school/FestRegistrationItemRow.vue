<template>
    <!-- Sports: compact single-row layout -->
    <tr v-if="layout === 'sports'" :id="rowId" class="group hover:bg-slate-50/80 transition-colors">
        <td class="px-3 py-2.5 align-middle">
            <div class="flex flex-wrap items-center gap-1.5">
                <p class="font-medium text-slate-900 text-sm leading-snug">{{ displayTitle }}</p>
                <span v-if="item.item_code"
                      class="inline-flex shrink-0 text-[10px] font-mono font-bold text-slate-600 bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded">
                    CODE: {{ item.item_code }}
                </span>
                <span v-if="statusLabel"
                      class="inline-flex shrink-0 text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded border"
                      :class="statusClass">
                    {{ statusLabel }}
                </span>
            </div>
            <p v-if="item.squad_summary" class="text-[11px] text-indigo-600 mt-0.5">{{ item.squad_summary }}</p>
            <p v-if="item.competition_line" class="text-[11px] text-slate-500 mt-0.5">Event: {{ item.competition_line }}</p>
            <p v-if="item.competition_start" class="text-[11px] text-slate-500 mt-0.5">
                Competition: {{ formatDate(item.competition_start) }}<span v-if="item.competition_time"> @ {{ item.competition_time.slice(0, 5) }}</span>
            </p>
            <p v-if="statusHint && !blockReason" class="text-[11px] text-indigo-600 mt-0.5">{{ statusHint }}</p>
            <p v-if="blockReason" class="text-[11px] text-amber-700 mt-0.5">{{ blockReason }}</p>
            <p v-if="errorMessage" class="text-[11px] text-red-600 mt-0.5 font-medium">{{ errorMessage }}</p>
        </td>
        <td v-if="showFee" class="px-3 py-2.5 align-middle text-sm text-slate-600 whitespace-nowrap tabular-nums">
            {{ item.item_fee != null ? `₹${formatMoney(item.item_fee)}` : '—' }}
        </td>
        <td class="px-3 py-2.5 align-middle">
            <div v-if="registrations.length" class="flex flex-wrap gap-1">
                <span v-for="reg in registrations" :key="reg.id"
                      class="inline-flex items-center gap-1 max-w-full rounded-md bg-emerald-50 border border-emerald-100 px-2 py-0.5 text-[11px] text-emerald-900">
                    <span class="truncate font-medium">{{ registeredNames(reg) }}</span>
                    <span v-if="isGroup"
                          class="px-1 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider whitespace-nowrap"
                          :class="squadCompletionClass(reg)">
                        {{ squadCompletionLabel(reg) }}
                    </span>
                    <span class="text-emerald-600/70 shrink-0">{{ reg.status }}</span>
                    <button v-if="canEdit(reg)" type="button"
                            class="shrink-0 text-indigo-600 font-semibold hover:underline"
                            :title="reg.status === 'rejected' ? reg.rejection_reason : null"
                            @click="$emit('edit', reg)">
                        {{ reg.status === 'rejected' ? 'Fix & resubmit' : 'Edit' }}
                    </button>
                    <button v-if="canWithdraw(reg)" type="button"
                            class="shrink-0 text-red-600 font-semibold hover:underline"
                            @click="$emit('withdraw', reg.id)">
                        Cancel
                    </button>
                    <span class="text-emerald-600/50 shrink-0 text-[9px]" :title="registrationTimestampLabel(reg)">{{ registrationTimestampLabel(reg) }}</span>
                </span>
            </div>
            <span v-else class="text-xs text-slate-300">—</span>
        </td>
        <td class="px-3 py-2.5 align-middle">
            <p v-if="isEditing" class="text-[10px] text-indigo-700 font-semibold mb-1 text-right">
                Editing registration —
                <button type="button" class="underline hover:no-underline" @click="$emit('cancel-edit')">discard</button>
            </p>
            <div class="flex flex-wrap items-center justify-end gap-1.5">
                <button type="button"
                        class="btn-secondary text-xs !min-h-0 !px-2.5 !py-1"
                        :disabled="blocked && !hasRegistrationToEdit"
                        @click="openPicker">
                    {{ pickerSummary }}
                </button>
                <button v-if="showStandbyPicker"
                        type="button"
                        class="btn-secondary text-xs !min-h-0 !px-2 !py-1"
                        :disabled="blocked && !hasRegistrationToEdit"
                        @click="openStandbyPicker">
                    Standbys{{ standbySelectedCount ? ` (${standbySelectedCount})` : '' }}
                </button>
                <button type="button"
                        class="btn-primary text-xs !min-h-0 !px-3 !py-1"
                        :disabled="(blocked && !isEditing) || !canSubmit"
                        @click="submit">
                    {{ isEditing ? 'Save changes' : 'Register' }}
                </button>
            </div>
            <p v-if="selectedCount > 0" class="text-[10px] font-medium mt-1 text-right"
               :class="selectedCount >= (item.min_group_size || 1) ? 'text-emerald-700' : 'text-amber-700'">
                {{ selectedCount }} {{ performerLabel }}{{ selectedCount !== 1 ? 's' : '' }} ready
                <span v-if="isGroup && selectedCount < (item.min_group_size || 1)">
                    (requires min {{ item.min_group_size || 1 }})
                </span>
            </p>
            <p v-if="selectedAgeNotes.length" class="text-[10px] text-amber-700 mt-1 text-right max-w-xs ml-auto leading-snug">
                {{ selectedAgeNotes.join(' · ') }}
            </p>
            <p v-else-if="submitHint" class="text-[10px] text-amber-700 font-medium mt-1 text-right">
                {{ submitHint }}
            </p>
        </td>
    </tr>

    <!-- Default: Kalotsav / Kids Fest / Teacher Fest -->
    <tr v-else :id="rowId" class="hover:bg-gray-50/40">
        <td class="px-3 py-2">
            <div class="flex flex-wrap items-center gap-1.5">
                <p class="font-medium text-gray-900 text-sm">{{ displayTitle }}</p>
                <span v-if="item.item_code"
                      class="inline-flex shrink-0 text-[10px] font-mono font-bold text-slate-600 bg-slate-100 border border-slate-200 px-1.5 py-0.5 rounded">
                    CODE: {{ item.item_code }}
                </span>
                <span class="inline-flex shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded border"
                      :class="competitionTypeBadge.class">
                    {{ competitionTypeBadge.label }}
                </span>
                <span v-if="statusLabel"
                      class="inline-flex shrink-0 text-[10px] font-bold uppercase tracking-wide px-1.5 py-0.5 rounded border"
                      :class="statusClass">
                    {{ statusLabel }}
                </span>
            </div>
            <p v-if="item.squad_summary" class="text-[11px] text-indigo-700 mt-0.5">{{ item.squad_summary }}</p>
            <p v-if="statusHint && !blockReason" class="text-[11px] text-indigo-600 mt-0.5">{{ statusHint }}</p>
            <p v-if="blockReason" class="text-[11px] text-amber-700 mt-0.5">{{ blockReason }}</p>
            <p v-if="errorMessage" class="text-[11px] text-red-600 mt-0.5 font-medium">{{ errorMessage }}</p>
        </td>
        <td class="px-3 py-2 text-xs text-gray-600 whitespace-nowrap">{{ eligibilityLabel }}</td>
        <td v-if="showFee" class="px-3 py-2 text-xs font-semibold text-gray-800 whitespace-nowrap">
            {{ item.item_fee != null ? `₹${formatMoney(item.item_fee)}` : '—' }}
        </td>
        <td class="px-3 py-2 text-xs text-gray-600">
            <div v-if="registrations.length" class="space-y-1">
                <div v-for="reg in registrations" :key="reg.id">
                    <span class="font-medium">{{ registeredNames(reg) }}</span>
                    <span class="text-gray-400"> · {{ reg.status }}</span>
                    <button v-if="canEdit(reg)" type="button"
                            class="ml-1 text-indigo-600 font-semibold hover:underline"
                            :title="reg.status === 'rejected' ? reg.rejection_reason : null"
                            @click="$emit('edit', reg)">
                        {{ reg.status === 'rejected' ? 'Fix & resubmit' : 'Edit' }}
                    </button>
                    <button v-if="canWithdraw(reg)" type="button"
                            class="ml-1 text-red-600 font-semibold hover:underline"
                            @click="$emit('withdraw', reg.id)">
                        Cancel
                    </button>
                    <div class="text-[10px] text-gray-400">{{ registrationTimestampLabel(reg) }}</div>
                </div>
            </div>
            <span v-else class="text-gray-400">—</span>
        </td>
        <td class="px-3 py-2">
            <p v-if="isEditing" class="text-[10px] text-indigo-700 font-semibold mb-1">
                Editing registration —
                <button type="button" class="underline hover:no-underline" @click="$emit('cancel-edit')">discard</button>
            </p>
            <div class="flex flex-wrap items-center gap-2">
                <button type="button"
                        class="btn-secondary text-xs !min-h-0 !px-2 !py-1"
                        :disabled="blocked && !hasRegistrationToEdit"
                        @click="openPicker">
                    {{ pickerSummary }}
                </button>
                <span v-if="selectedCount > 0" class="text-[10px] text-[#0f3d7a] font-semibold whitespace-nowrap">
                    {{ selectedCount }} ready
                </span>
                <button v-if="showStandbyPicker"
                        type="button"
                        class="btn-secondary text-xs !min-h-0 !px-2 !py-1"
                        :disabled="blocked && !hasRegistrationToEdit"
                        @click="openStandbyPicker">
                    Standbys ({{ standbySelectedCount }})
                </button>
                <span v-else-if="selectedAgeNotes.length" class="text-[10px] text-amber-700 whitespace-nowrap">
                    {{ selectedAgeNotes[0] }}
                </span>
                <span v-else-if="!isTeacherFest && eligibleCount === 0 && rosterCount > 0"
                      class="text-[10px] text-amber-700 whitespace-nowrap">
                    0 eligible
                </span>
                <span v-else-if="submitHint" class="text-[10px] text-amber-700 whitespace-nowrap">
                    {{ submitHint }}
                </span>
            </div>
        </td>
        <td class="px-3 py-2 text-right">
            <button type="button"
                    class="btn-primary text-xs !min-h-0 !px-2 !py-1.5"
                    :disabled="(blocked && !isEditing) || !canSubmit"
                    @click="submit">
                {{ isEditing ? 'Save changes' : 'Register' }}
            </button>
        </td>
    </tr>

    <FestStudentPickerModal
        v-if="!isTeacherFest"
        v-model="pickerOpen"
        :title="`${displayTitle} — pick ${performerLabel}`"
        :subtitle="pickerSubtitle"
        :entries="rosterEntries"
        v-model:selected-ids="pickerModel"
        :team-name="isGroup ? form.team_name : undefined"
        :require-team-name="isGroup"
        :coach-name="isGroup ? form.coach_name : undefined"
        :coach-phone="isGroup ? form.coach_phone : undefined"
        :manager-name="isGroup ? form.manager_name : undefined"
        :manager-phone="isGroup ? form.manager_phone : undefined"
        :min-selected="isGroup ? groupSizeBounds?.min : null"
        :max-selected="isGroup ? groupSizeBounds?.max : maxSelectedLimit"
        :confirm-label="layout === 'sports' ? 'Register selection' : 'Use selection'"
        @update:team-name="form.team_name = $event"
        @update:coach-name="form.coach_name = $event"
        @update:coach-phone="form.coach_phone = $event"
        @update:manager-name="form.manager_name = $event"
        @update:manager-phone="form.manager_phone = $event"
        @confirm="handleMainPickerConfirm"
        @add-student="$emit('add-student')"
        @search="$emit('search-students', $event)"
    />

    <FestStudentPickerModal
        v-if="!isTeacherFest && showStandbyPicker"
        v-model="standbyPickerOpen"
        :title="`${displayTitle} — pick standbys`"
        subtitle="Optional substitutes — max 2"
        :entries="standbyEntries"
        v-model:selected-ids="standbyModel"
        confirm-label="Use standbys"
        @add-student="$emit('add-student')"
        @search="$emit('search-students', $event)"
    />

    <FestStudentPickerModal
        v-else-if="isTeacherFest"
        v-model="pickerOpen"
        :title="`${displayTitle} — pick teachers`"
        :subtitle="pickerSubtitle"
        :entries="teacherEntries"
        v-model:selected-ids="pickerModel"
        confirm-label="Use selection"
        :show-add-student="false"
    />
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import FestStudentPickerModal from '@/Components/school/FestStudentPickerModal.vue';
import { studentDisplayName } from '@/support/studentDisplay.js';
import { useSweetAlert } from '@/composables/useSweetAlert.js';
import { formatDateTime } from '@/support/calendarDates.js';

const { showError, showWarning } = useSweetAlert();

const props = defineProps({
    item: { type: Object, required: true },
    form: { type: Object, required: true },
    registrations: { type: Array, default: () => [] },
    eligibleStudents: { type: Array, default: () => [] },
    allStudents: { type: Array, default: () => [] },
    studentIneligibilityReason: { type: Function, default: null },
    teachers: { type: Array, default: () => [] },
    isTeacherFest: { type: Boolean, default: false },
    showFee: { type: Boolean, default: false },
    blocked: { type: Boolean, default: false },
    blockReason: { type: String, default: '' },
    errorMessage: { type: String, default: '' },
    statusLabel: { type: String, default: '' },
    statusClass: { type: String, default: '' },
    statusHint: { type: String, default: '' },
    rowId: { type: String, default: '' },
    eventType: { type: String, default: '' },
    performerLabel: { type: String, default: 'students' },
    studentLabel: { type: Function, required: true },
    registeredNames: { type: Function, required: true },
    canWithdraw: { type: Function, required: true },
    canEdit: { type: Function, default: () => false },
    editingRegistrationId: { type: [Number, String], default: null },
    columnCount: { type: Number, default: 6 },
    layout: { type: String, default: 'default' },
    classGroupLabels: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['register', 'update', 'withdraw', 'edit', 'cancel-edit', 'add-student', 'search-students']);

const isEditing = computed(() => props.editingRegistrationId != null);

// A "blocked" item (e.g. school's per-item quota already reached) must still be
// reachable via its own existing registration -- that's the whole point of
// isItemBlocked()'s isCurrentlyEditing exception in the parent. This can't be
// keyed off isEditing: before the first click, isEditing is false precisely
// because nothing has been clicked yet, so a `blocked && !isEditing` disabled
// guard would leave the button permanently disabled (blocked because not
// editing, never editing because the disabled button can't be clicked).
// Keying off "is there a registration to fall back into" avoids that deadlock.
const hasRegistrationToEdit = computed(() => !isEditing.value && (props.registrations?.length ?? 0) > 0 && !!props.registrations[0]);

const pickerOpen = ref(false);
const standbyPickerOpen = ref(false);

// openStandbyPicker() also emits 'edit' (to sync the form with the existing
// registration before showing standby-eligible students) — without the
// standbyPickerOpen guard, this watcher reacting to that same edit would force
// the main squad picker open on top of the standby one that was just requested.
watch(() => props.editingRegistrationId, (id) => {
    if (id != null && !standbyPickerOpen.value) pickerOpen.value = true;
});

const showStandbyPicker = computed(() => {
    if (props.isTeacherFest) return false;
    const criteria = props.item.criteria_json ?? {};
    const maxSubs = props.item.max_subs ?? criteria.max_subs;
    const standbys = props.item.standbys ?? criteria.standbys;
    return (maxSubs != null && maxSubs > 0) || (standbys != null && standbys > 0);
});

const displayTitle = computed(() => {
    const raw = props.item?.clean_title || props.item?.title || '';
    return String(raw).replace(/_/g, ' ');
});

const isGroup = computed(() => ['team', 'group', 'pair', 'trio'].includes(props.item.participant_type));

const competitionTypeBadge = computed(() => {
    const isGrp = ['team', 'group', 'pair', 'trio'].includes(props.item.participant_type);
    const isOff = props.item.stage_type === 'off_stage';

    if (isOff) {
        return { label: '🎨 Off-stage', class: 'bg-amber-50 text-amber-800 border-amber-200' };
    }
    if (isGrp) {
        const min = props.item.min_group_size || props.item.min_participants;
        const max = props.item.max_group_size || props.item.max_participants;
        const squad = min && max && min !== max ? ` (${min}-${max})` : max ? ` (Max ${max})` : '';
        return { label: `👥 On-stage Group${squad}`, class: 'bg-emerald-50 text-emerald-800 border-emerald-200' };
    }
    return { label: '🎭 On-stage Individual', class: 'bg-indigo-50 text-indigo-700 border-indigo-200' };
});

// Default "Team N" for this school's Nth entry under this item — pre-filled so registering
// a team is one direct action (pick athletes, confirm) instead of also requiring a typed name.
// Still editable in the picker if the school wants a custom name.
const nextTeamName = computed(() => `Team ${(props.registrations?.length ?? 0) + 1}`);

const eligibilityLabel = computed(() => {
    const parts = [];
    const age = props.item.age_group;
    if (age && age !== 'open') {
        parts.push(String(age).toUpperCase());
    }
    // Show class category for events that use class_group (English Fest, Science Fest, etc.)
    const classGroup = props.item.class_group;
    if (classGroup && classGroup !== 'open') {
        // Use the event's class_group_labels if available, otherwise fallback to uppercase
        const label = props.classGroupLabels[classGroup] ?? String(classGroup).toUpperCase();
        parts.push(label);
    }
    // Also show gender if present and meaningful
    const gender = String(props.item.gender ?? '').toLowerCase();
    if (gender && !['open', 'mixed'].includes(gender)) {
        parts.push(gender === 'male' ? 'Boys' : gender === 'female' ? 'Girls' : props.item.gender);
    } else if (!classGroup || classGroup === 'open') {
        // Fall back to eligibility_label only if no class_group was shown
        if (props.item.eligibility_label && props.item.eligibility_label !== 'Open') {
            parts.push(props.item.eligibility_label);
        } else {
            const title = String(props.item.title ?? '').toLowerCase();
            if (title.includes('boys')) parts.push('Boys');
            else if (title.includes('girls')) parts.push('Girls');
            else if (props.item.eligibility_label) parts.push(props.item.eligibility_label);
        }
    }
    return parts.length ? parts.join(' · ') : 'Open';
});

const maxSelectedLimit = computed(() => {
    if (isGroup.value) return null;
    const maxPerSchool = Number(props.item?.max_per_school ?? 1);
    return maxPerSchool > 0 ? maxPerSchool : 1;
});

const pickerSubtitle = computed(() => {
    const parts = [`Eligible: ${eligibilityLabel.value}`];
    if (isGroup.value) {
        parts.push('Team name required');
    } else {
        const max = maxSelectedLimit.value;
        if (max > 1) {
            parts.push(`Select up to ${max} students for this item`);
        }
    }
    return parts.join(' · ');
});

const eligibleCount = computed(() => props.eligibleStudents?.length ?? 0);
const rosterCount = computed(() => props.allStudents?.length ?? 0);
const selectedCount = computed(() => pickerModel.value?.length ?? 0);
const standbySelectedCount = computed(() => standbyModel.value?.length ?? 0);

const standbyEntries = computed(() => {
    const performerIds = props.form.student_ids ?? [];
    const pool = (props.eligibleStudents?.length ?? 0) > 0 ? props.eligibleStudents : (props.allStudents ?? []);
    const eligibleSet = new Set((props.eligibleStudents ?? []).map(s => s.id));

    return pool.map((student) => {
        const eligible = !performerIds.includes(student.id) && eligibleSet.has(student.id);
        return {
            id: student.id,
            name: student.name,
            displayName: studentDisplayName(student),
            regNo: student.reg_no || '',
            // School-entered admission number — shown alongside reg_no (in parens), not
            // instead of it, so both identifiers stay visible when a school has set one.
            admissionNo: student.admission_number || null,
            meta: props.studentLabel(student),
            eligible,
            reason: eligible ? null : (props.studentIneligibilityReason?.(student) ?? 'Not eligible'),
            eventRegistered: !!(student.event_registered || student.event_registration_number),
            eventRegNumber: student.event_registration_number || null,
        };
    });
});

const rosterEntries = computed(() => {
    if (props.isTeacherFest) return [];
    const pool = (props.eligibleStudents?.length ?? 0) > 0 ? props.eligibleStudents : (props.allStudents ?? []);
    const eligibleSet = new Set((props.eligibleStudents ?? []).map(s => s.id));

    return pool.map((student) => {
        const eligible = eligibleSet.has(student.id);
        const reason = eligible ? null : (props.studentIneligibilityReason?.(student) ?? 'Not eligible for this item');
        return {
            id: student.id,
            name: student.name,
            displayName: studentDisplayName(student),
            regNo: student.reg_no || '',
            admissionNo: student.admission_number || null,
            meta: props.studentLabel(student),
            eligible,
            reason,
            eventRegistered: !!(student.event_registered || student.event_registration_number),
            eventRegNumber: student.event_registration_number || null,
        };
    });
});

const teacherEntries = computed(() => (props.teachers ?? []).map((t) => ({
    id: t.id,
    name: t.name,
    regNo: t.reg_no || '',
    meta: t.designation || 'Teacher',
    eligible: true,
    reason: null,
})));

const pickerSummary = computed(() => {
    const n = selectedCount.value;
    if (n > 0) return `${n} selected · Change`;
    if (!props.isTeacherFest && rosterCount.value > 0 && eligibleCount.value === 0) {
        return 'Why none match?';
    }
    return `Pick ${props.performerLabel}`;
});

// Some items carry min_group_size/max_group_size without participant_type being
// literally 'team'/'group'/'pair'/'trio' (matches the backend's hasSquadRules()
// widening in FestTeamSquadRules::fromItem) -- key off the actual configured
// bounds, not just the participant_type badge, so the count check can't be
// silently skipped by a type mismatch.
const groupSizeBounds = computed(() => {
    if (!isGroup.value && !props.item.min_group_size && !props.item.max_group_size) return null;
    const min = Number(props.item.min_group_size || 1);
    const max = props.item.max_group_size ? Number(props.item.max_group_size) : null;
    return { min, max };
});

const canSubmit = computed(() => {
    const ids = pickerModel.value ?? [];
    if (!ids.length) return false;
    if (isGroup.value && !String(props.form.team_name ?? '').trim()) return false;
    if (!isGroup.value && maxSelectedLimit.value != null && ids.length > maxSelectedLimit.value) return false;
    if (groupSizeBounds.value) {
        const { min, max } = groupSizeBounds.value;
        if (ids.length < min) return false;
        if (max != null && ids.length > max) return false;
    }
    return true;
});

const submitHint = computed(() => {
    const ids = pickerModel.value ?? [];
    if (!isGroup.value && maxSelectedLimit.value != null && ids.length > maxSelectedLimit.value) {
        return `Maximum ${maxSelectedLimit.value} entry registrations allowed.`;
    }
    if (ids.length > 0 && groupSizeBounds.value) {
        const { min, max } = groupSizeBounds.value;
        if (ids.length < min) {
            return `Select at least ${min} ${props.performerLabel}${min !== 1 ? 's' : ''} for this item.`;
        }
        if (max != null && ids.length > max) {
            return `Maximum ${max} ${props.performerLabel}${max !== 1 ? 's' : ''} allowed for this item.`;
        }
    }
    if (isGroup.value && ids.length > 0 && !String(props.form.team_name ?? '').trim()) {
        return 'Team name required.';
    }
    return '';
});

const selectedAgeNotes = computed(() => {
    if (props.eventType !== 'sports') return [];
    return (pickerModel.value ?? []).map((id) => {
        const student = (props.allStudents ?? []).find(s => s.id === id);
        if (!student) return null;
        if (student.sports_age_on_cutoff == null) {
            return `${student.name}: DOB required for age check`;
        }
        const group = props.item.age_group && props.item.age_group !== 'open'
            ? String(props.item.age_group).toUpperCase()
            : 'eligible';
        return `${student.name}: age ${student.sports_age_on_cutoff} on cutoff (${group})`;
    }).filter(Boolean);
});

const pickerModel = computed({
    get() {
        return props.isTeacherFest ? (props.form.teacher_ids ?? []) : (props.form.student_ids ?? []);
    },
    set(value) {
        if (props.isTeacherFest) {
            props.form.teacher_ids = value;
        } else {
            props.form.student_ids = value;
        }
    },
});

const standbyModel = computed({
    get() {
        return props.form.standby_ids ?? [];
    },
    set(value) {
        props.form.standby_ids = (value ?? []).slice(0, 2);
    },
});

watch(() => props.form.standby_ids, (ids) => {
    if ((ids ?? []).length > 2) props.form.standby_ids = ids.slice(0, 2);
}, { deep: true });

function formatMoney(value) {
    const n = Number(value);
    if (Number.isNaN(n)) return '0.00';
    return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function openPicker() {
    // Auto-entering edit mode has to happen BEFORE the blocked check below, not
    // after, or the block never lifts (see hasRegistrationToEdit's comment).
    // Only a genuinely new registration attempt (nothing to fall back into)
    // should honor the block.
    if (hasRegistrationToEdit.value) {
        emit('edit', props.registrations[0]);
    } else if (props.blocked) {
        showWarning(props.blockReason || 'This item is currently unavailable.', 'Item Unavailable');
        return;
    }
    if (!isEditing.value && isGroup.value && !String(props.form.team_name ?? '').trim()) {
        props.form.team_name = nextTeamName.value;
    }
    pickerOpen.value = true;
}

function openStandbyPicker() {
    // See openPicker() above for why the existing-registration edit trigger must
    // run before the blocked check.
    if (hasRegistrationToEdit.value) {
        emit('edit', props.registrations[0]);
    } else if (props.blocked) {
        showWarning(props.blockReason || 'This item is currently unavailable.', 'Item Unavailable');
        return;
    }
    standbyPickerOpen.value = true;
}

function handleMainPickerConfirm() {
    if (props.layout === 'sports' && canSubmit.value) {
        submit();
    }
}

function submit() {
    // Once editing an existing registration, `blocked` (a per-school/new-entry
    // cap) no longer applies -- saving changes to what's already on file isn't
    // creating a new entry. Only a fresh, non-editing submit should honor it.
    if (props.blocked && !isEditing.value) {
        showWarning(props.blockReason || 'This item is currently unavailable for registration.', 'Item Unavailable');
        return;
    }
    if (!canSubmit.value) {
        if (submitHint.value) {
            showWarning(submitHint.value, 'Registration Incomplete');
        } else if (selectedCount.value === 0) {
            showWarning(`Please pick ${props.performerLabel} before registering.`, 'Selection Required');
        } else {
            showWarning('Please review selection requirements for this item.', 'Registration Notice');
        }
        return;
    }
    if (isEditing.value) {
        emit('update', props.editingRegistrationId);
    } else {
        emit('register');
    }
}

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
}

function registrationTimestampLabel(reg) {
    const submitted = `Submitted ${formatDateTime(reg.created_at)}`;
    if (reg.updated_at && reg.updated_at !== reg.created_at) {
        return `${submitted} · Modified ${formatDateTime(reg.updated_at)}`;
    }

    return submitted;
}

function squadPerformersCount(reg) {
    return (reg.participants ?? [])
        .filter((p) => p.participant_role !== 'standby' && p.student_id)
        .length;
}

function squadCompletionLabel(reg) {
    const count = squadPerformersCount(reg);
    const min = props.item.min_group_size || 1;
    if (count >= min) return 'Complete';
    return `${count}/${min} min`;
}

function squadCompletionClass(reg) {
    const count = squadPerformersCount(reg);
    const min = props.item.min_group_size || 1;
    if (count >= min) return 'bg-emerald-100 text-emerald-800 border border-emerald-200';
    return 'bg-amber-100 text-amber-800 border border-amber-200';
}
</script>
