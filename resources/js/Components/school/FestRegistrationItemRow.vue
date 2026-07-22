<template>
    <!-- Sports: compact single-row layout -->
    <tr v-if="layout === 'sports'" :id="rowId" class="group hover:bg-slate-50/80 transition-colors">
        <td class="px-3 py-2.5 align-middle">
            <div class="flex flex-wrap items-center gap-1.5">
                <p class="font-medium text-slate-900 text-sm leading-snug">{{ item.title }}</p>
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
                            @click="$emit('edit', reg)">
                        Edit
                    </button>
                    <button v-if="canWithdraw(reg)" type="button"
                            class="shrink-0 text-red-600 font-semibold hover:underline"
                            @click="$emit('withdraw', reg.id)">
                        Cancel
                    </button>
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
                        :disabled="blocked"
                        @click="openPicker">
                    {{ pickerSummary }}
                </button>
                <button v-if="showStandbyPicker"
                        type="button"
                        class="btn-secondary text-xs !min-h-0 !px-2 !py-1"
                        :disabled="blocked"
                        @click="openStandbyPicker">
                    Standbys{{ standbySelectedCount ? ` (${standbySelectedCount})` : '' }}
                </button>
                <button type="button"
                        class="btn-primary text-xs !min-h-0 !px-3 !py-1"
                        :disabled="blocked || !canSubmit"
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
                <p class="font-medium text-gray-900 text-sm">{{ item.title }}</p>
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
                            @click="$emit('edit', reg)">
                        Edit
                    </button>
                    <button v-if="canWithdraw(reg)" type="button"
                            class="ml-1 text-red-600 font-semibold hover:underline"
                            @click="$emit('withdraw', reg.id)">
                        Cancel
                    </button>
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
                        :disabled="blocked"
                        @click="openPicker">
                    {{ pickerSummary }}
                </button>
                <span v-if="selectedCount > 0" class="text-[10px] text-[#0f3d7a] font-semibold whitespace-nowrap">
                    {{ selectedCount }} ready
                </span>
                <button v-if="showStandbyPicker"
                        type="button"
                        class="btn-secondary text-xs !min-h-0 !px-2 !py-1"
                        :disabled="blocked"
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
                    :disabled="blocked || !canSubmit"
                    @click="submit">
                {{ isEditing ? 'Save changes' : 'Register' }}
            </button>
        </td>
    </tr>

    <FestStudentPickerModal
        v-if="!isTeacherFest"
        v-model="pickerOpen"
        :title="`${item.title} — pick ${performerLabel}`"
        :subtitle="pickerSubtitle"
        :entries="rosterEntries"
        v-model:selected-ids="pickerModel"
        :team-name="isGroup ? form.team_name : undefined"
        :require-team-name="isGroup"
        :coach-name="isGroup ? form.coach_name : undefined"
        :coach-phone="isGroup ? form.coach_phone : undefined"
        :manager-name="isGroup ? form.manager_name : undefined"
        :manager-phone="isGroup ? form.manager_phone : undefined"
        :max-selected="isGroup ? null : 1"
        :confirm-label="layout === 'sports' ? 'Register selection' : 'Use selection'"
        @update:team-name="form.team_name = $event"
        @update:coach-name="form.coach_name = $event"
        @update:coach-phone="form.coach_phone = $event"
        @update:manager-name="form.manager_name = $event"
        @update:manager-phone="form.manager_phone = $event"
        @confirm="handleMainPickerConfirm"
        @add-student="$emit('add-student')"
    />

    <FestStudentPickerModal
        v-if="!isTeacherFest && showStandbyPicker"
        v-model="standbyPickerOpen"
        :title="`${item.title} — pick standbys`"
        subtitle="Optional substitutes — max 2"
        :entries="standbyEntries"
        v-model:selected-ids="standbyModel"
        confirm-label="Use standbys"
        @add-student="$emit('add-student')"
    />

    <FestStudentPickerModal
        v-else-if="isTeacherFest"
        v-model="pickerOpen"
        :title="`${item.title} — pick teachers`"
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
});

const emit = defineEmits(['register', 'update', 'withdraw', 'edit', 'cancel-edit', 'add-student']);

const isEditing = computed(() => props.editingRegistrationId != null);

watch(() => props.editingRegistrationId, (id) => {
    if (id != null) pickerOpen.value = true;
});

const pickerOpen = ref(false);
const standbyPickerOpen = ref(false);

const showStandbyPicker = computed(() => {
    if (props.isTeacherFest) return false;
    if (props.item.max_subs === 0 || props.item.standbys === 0) return false;
    if (props.item.max_subs != null && props.item.max_subs > 0) return true;
    if (props.item.standbys != null && props.item.standbys > 0) return true;
    return isGroup.value;
});

const isGroup = computed(() => ['group', 'team'].includes(props.item.participant_type));

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
    if (props.item.eligibility_label && props.item.eligibility_label !== 'Open') {
        parts.push(props.item.eligibility_label);
    } else {
        const title = String(props.item.title ?? '').toLowerCase();
        if (title.includes('boys')) parts.push('Boys');
        else if (title.includes('girls')) parts.push('Girls');
        else if (props.item.eligibility_label) parts.push(props.item.eligibility_label);
    }
    return parts.length ? parts.join(' · ') : 'Open';
});

const pickerSubtitle = computed(() => {
    const parts = [`Eligible: ${eligibilityLabel.value}`];
    if (isGroup.value) parts.push('Team name required');
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
            regNo: student.reg_no || '',
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
            regNo: student.reg_no || '',
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

const canSubmit = computed(() => {
    const ids = pickerModel.value ?? [];
    if (!ids.length) return false;
    if (isGroup.value && !String(props.form.team_name ?? '').trim()) return false;
    if (!isGroup.value && ids.length > 1) return false;
    return true;
});

const submitHint = computed(() => {
    const ids = pickerModel.value ?? [];
    if (!isGroup.value && ids.length > 1) {
        return 'Only one participant allowed for this item.';
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
    if (!isEditing.value && props.registrations?.length > 0 && props.registrations[0]) {
        emit('edit', props.registrations[0]);
        return;
    }
    if (!isEditing.value && isGroup.value && !String(props.form.team_name ?? '').trim()) {
        props.form.team_name = nextTeamName.value;
    }
    pickerOpen.value = true;
}

function openStandbyPicker() {
    if (!isEditing.value && props.registrations?.length > 0 && props.registrations[0]) {
        emit('edit', props.registrations[0]);
    }
    standbyPickerOpen.value = true;
}

function handleMainPickerConfirm() {
    if (props.layout === 'sports' && canSubmit.value) {
        submit();
    }
}

function submit() {
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
