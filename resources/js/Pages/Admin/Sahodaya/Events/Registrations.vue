<template>
    <SahodayaEventsLayout :title="`${event.title} — Registrations`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Registrations`" eyebrow="Registrations"
                    :description="filterDescription">
            <template #actions>
                <Link v-if="competitionUrl" :href="competitionUrl" class="btn-secondary text-xs">← {{ event.event_type === 'sports' ? 'By Event Head' : 'By item head' }}</Link>
                <button type="button" class="btn-primary text-xs" @click="openOnBehalf">Register on behalf</button>
                <Link :href="`${base}/registrations/import`" class="btn-secondary text-xs">Import CSV</Link>
                <Link v-if="feeRequired" :href="`${base}/fees`" class="btn-secondary text-xs">Event fees</Link>
            </template>
        </PageHeader>

        <SportsSetupSubNav v-if="event.event_type === 'sports'" :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="registrations" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="registrations" />

        <p v-if="selectedItemId" class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
            Showing registrations for one item.
            <Link :href="competitionUrl" class="font-semibold underline ml-1">Back to item listing</Link>
        </p>

        <div class="card mb-4 space-y-3">
            <div class="flex flex-wrap gap-2 items-end">
                <div>
                    <label class="text-xs font-semibold text-gray-600">Filter by school</label>
                    <select v-model="filterSchoolId" class="field text-sm mt-1">
                        <option value="">All schools</option>
                        <option v-for="(name, id) in schools" :key="id" :value="id">{{ name }}</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Filter by item</label>
                    <select v-model="filterItemId" class="field text-sm mt-1 w-56">
                        <option value="">All items</option>
                        <option v-for="item in eventItems" :key="item.id" :value="item.id">
                            {{ item.title }}
                        </option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600">Filter by status</label>
                    <select v-model="filterStatus" class="field text-sm mt-1 w-32">
                        <option value="">All statuses</option>
                        <option value="submitted">Submitted</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="withdrawn">Withdrawn</option>
                    </select>
                </div>
                <div class="flex-1 min-w-[180px]">
                    <label class="text-xs font-semibold text-gray-600">Search participant</label>
                    <input v-model="searchQuery" type="search" placeholder="Name or reg no…"
                           class="field text-sm mt-1" @keyup.enter="applySearch">
                </div>
                <button type="button" class="btn-secondary text-xs" @click="applySearch">Search</button>
                <button type="button" class="btn-secondary text-xs" @click="toggleSelectAll">Toggle submitted</button>
                <button type="button" class="btn-primary text-xs" :disabled="!selectedIds.length" @click="bulkApprove">Approve selected ({{ selectedIds.length }})</button>
                <button type="button" class="btn-secondary text-xs text-red-600" :disabled="!selectedIds.length" @click="bulkReject">Reject selected</button>
                <button v-if="filterSchoolId" type="button" class="btn-primary text-xs" @click="approveSchool">Approve all for school</button>
                <button v-if="filterItemId" type="button" class="btn-primary text-xs" @click="approveItem">Approve all for item</button>
                <label class="flex items-center gap-1 text-xs text-gray-600 ml-auto font-medium">
                    <input type="checkbox" v-model="overrideLifecycle"> Override locked registration
                </label>
            </div>
        </div>

        <!-- ── Sports: group registrations by age group ── -->
        <SportsRegistrationsTable
            v-if="event.event_type === 'sports'"
            :grouped-registrations="sportsGroupedRegistrations"
            :has-registrations="filteredRegistrations.length > 0"
            :selected-ids="selectedIds"
            :schools="schools"
            :gender-label="genderLabel"
            :status-class="statusClass"
            :standby-count="standbyCount"
            :can-cancel="canCancel"
            @toggle-select="toggleId"
            @substitute="openSubstitute"
            @approve="approve"
            @reject="reject"
            @cancel="cancel"
        />

        <!-- ── Kalotsav / other events: flat table ── -->
        <div v-else class="card card--flush overflow-x-auto">
            <table class="w-full min-w-[720px] text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="p-3 w-8"></th>
                        <th class="p-3 w-12">Sl No</th>
                        <th class="p-3">School</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Participants</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(reg, idx) in filteredRegistrations" :key="reg.id" class="border-t align-top">
                        <td class="p-3">
                            <input v-if="reg.status === 'submitted'" type="checkbox" :value="reg.id" v-model="selectedIds">
                        </td>
                        <td class="p-3 text-gray-500">{{ idx + 1 }}</td>
                        <td class="p-3">{{ (schools[reg.school_id] ?? reg.school_id ?? '').toString().toUpperCase() }}</td>
                        <td class="p-3">{{ reg.item?.title ?? '—' }}</td>
                        <td class="p-3">
                            <span :class="statusClass(reg.status)" class="text-xs font-semibold px-2 py-0.5 rounded">
                                {{ reg.status }}
                            </span>
                        </td>
                        <td class="p-3 text-xs space-y-1">
                                    <div v-for="p in reg.participants" :key="p.id" class="flex flex-wrap items-center gap-1.5">
                                        <span class="font-medium text-slate-800">{{ p.student?.name ?? p.teacher?.name ?? '—' }}</span>
                                        <span v-if="p.student?.reg_no" class="text-gray-400">· {{ p.student.reg_no }}</span>
                                        <span class="px-1.5 py-0.5 rounded text-[8px] font-bold uppercase tracking-wider"
                                              :class="p.participant_role === 'standby' ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-indigo-50 text-indigo-800 border border-indigo-200'">
                                            {{ p.participant_role || 'performer' }}
                                        </span>
                                    </div>
                                    <div v-if="reg.status === 'approved' && standbyCount(reg)" class="mt-1">
                                        <button type="button" class="text-indigo-600 font-semibold" @click="openSubstitute(reg)">Substitute</button>
                                    </div>
                                </td>
                        <td class="p-3 text-right space-x-2">
                            <template v-if="reg.status === 'submitted'">
                                <button @click="approve(reg.id)" class="text-green-600 text-xs font-semibold">Approve</button>
                                <button @click="reject(reg.id)" class="text-red-600 text-xs font-semibold">Reject</button>
                            </template>
                            <button v-if="canCancel(reg)"
                                    @click="cancel(reg.id)"
                                    class="text-gray-600 text-xs font-semibold">
                                Cancel
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!filteredRegistrations.length">
                        <td colspan="6" class="p-8 text-center text-gray-400">No registrations yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="substituteReg" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="substituteReg = null">
            <div class="card max-w-md w-full">
                <h3 class="font-semibold mb-3">Substitute performer</h3>
                <p class="text-xs text-gray-500 mb-3">{{ substituteReg.item?.title }}</p>
                <FormField label="Performer (out)">
                    <select v-model="substituteForm.performer_id" class="field text-sm">
                        <option v-for="p in performers(substituteReg)" :key="p.id" :value="p.id">{{ participantLabel(p) }}</option>
                    </select>
                </FormField>
                <FormField label="Standby (in)" class-extra="mt-2">
                    <select v-model="substituteForm.standby_id" class="field text-sm">
                        <option v-for="p in standbys(substituteReg)" :key="p.id" :value="p.id">{{ participantLabel(p) }}</option>
                    </select>
                </FormField>
                <div class="flex gap-2 mt-4">
                    <button type="button" class="btn-primary text-sm" @click="submitSubstitute">Confirm swap</button>
                    <button type="button" class="btn-secondary text-sm" @click="substituteReg = null">Cancel</button>
                </div>
            </div>
        </div>

        <div v-if="onBehalfOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="onBehalfOpen = false"></div>
            <div class="relative modal-shell max-w-2xl w-full max-h-[90vh] flex flex-col">
                <div class="modal-head shrink-0">
                    <div>
                        <h3 class="font-bold">Register on behalf of school</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Enter a registration when a school cannot submit themselves.</p>
                    </div>
                    <button type="button" class="text-gray-400 hover:text-gray-600 text-2xl leading-none" @click="onBehalfOpen = false">&times;</button>
                </div>
                <form @submit.prevent="submitOnBehalf" class="p-5 space-y-4 overflow-y-auto">
                    <FormField label="School" required>
                        <select v-model="onBehalfForm.school_id" class="field text-sm" required @change="loadSchoolStudents">
                            <option value="">Select school</option>
                            <option v-for="(name, id) in schools" :key="id" :value="id">{{ name }}</option>
                        </select>
                    </FormField>
                    <div v-if="event.event_type === 'kalolsavam' && onBehalfForm.school_id"
                         class="rounded-lg border px-3 py-2 text-xs"
                         :class="selectedSchoolRegion ? 'border-emerald-100 bg-emerald-50 text-emerald-900' : 'border-amber-100 bg-amber-50 text-amber-900'">
                        <p v-if="selectedSchoolRegion">
                            Kalotsav region: <strong>{{ selectedSchoolRegion }}</strong>
                        </p>
                        <p v-else>
                            This school has no Kalotsav region for the active year.
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/regions`" class="font-semibold underline">Assign region first</Link>.
                        </p>
                    </div>
                    <FormField label="Event item" required>
                        <select v-model="onBehalfForm.item_id" class="field text-sm" required>
                            <option value="">Select item</option>
                            <option v-for="item in eventItems" :key="item.id" :value="item.id">
                                {{ item.title }}
                            </option>
                        </select>
                    </FormField>
                    <FormField v-if="selectedItemIsGroup" label="Team name" required>
                        <input v-model="onBehalfForm.team_name" type="text" class="field text-sm" required>
                    </FormField>
                    <div v-if="selectedItemIsGroup" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <FormField label="Coach name">
                            <input v-model="onBehalfForm.coach_name" type="text" class="field text-sm" placeholder="Optional">
                        </FormField>
                        <FormField label="Coach phone">
                            <input v-model="onBehalfForm.coach_phone" type="text" class="field text-sm" placeholder="Optional">
                        </FormField>
                        <FormField label="Manager name">
                            <input v-model="onBehalfForm.manager_name" type="text" class="field text-sm" placeholder="Optional">
                        </FormField>
                        <FormField label="Manager phone">
                            <input v-model="onBehalfForm.manager_phone" type="text" class="field text-sm" placeholder="Optional">
                        </FormField>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <label class="text-xs font-semibold text-gray-600">Performers</label>
                            <button type="button" class="btn-secondary text-xs !min-h-0 !py-1"
                                    :disabled="!onBehalfForm.school_id || !onBehalfForm.item_id"
                                    @click="performerPickerOpen = true">
                                Pick students ({{ onBehalfForm.student_ids.length }})
                            </button>
                        </div>
                        <div v-if="onBehalfForm.student_ids.length" class="flex flex-wrap gap-1.5">
                            <span v-for="id in onBehalfForm.student_ids" :key="id"
                                  class="inline-flex items-center px-2 py-0.5 rounded-full bg-[#0f3d7a]/10 text-[#0f3d7a] text-[11px] font-medium">
                                {{ studentLabel(id) }}
                            </span>
                        </div>
                        <p v-else class="text-xs text-slate-400">No performers selected.</p>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                            <label class="text-xs font-semibold text-gray-600">Standbys (optional, max 2)</label>
                            <button type="button" class="btn-secondary text-xs !min-h-0 !py-1"
                                    :disabled="!onBehalfForm.school_id || !onBehalfForm.item_id"
                                    @click="standbyPickerOpen = true">
                                Pick standbys ({{ onBehalfForm.standby_ids.length }})
                            </button>
                        </div>
                        <div v-if="onBehalfForm.standby_ids.length" class="flex flex-wrap gap-1.5">
                            <span v-for="id in onBehalfForm.standby_ids" :key="id"
                                  class="inline-flex items-center px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[11px] font-medium">
                                {{ studentLabel(id) }}
                            </span>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input v-model="onBehalfForm.auto_approve" type="checkbox" class="rounded">
                        Auto-approve after submit
                    </label>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="btn-ghost text-sm" @click="onBehalfOpen = false">Cancel</button>
                        <button type="submit" class="btn-primary text-sm"
                                :disabled="onBehalfSubmitting || !onBehalfForm.school_id || !onBehalfForm.item_id || !onBehalfForm.student_ids.length">
                            Submit registration
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <FestStudentPickerModal
            v-model="performerPickerOpen"
            title="Pick performers"
            :subtitle="pickerSubtitle"
            :entries="rosterEntries"
            v-model:selected-ids="onBehalfForm.student_ids"
            :team-name="selectedItemIsGroup ? onBehalfForm.team_name : undefined"
            :require-team-name="selectedItemIsGroup"
            confirm-label="Use selection"
            @update:team-name="onBehalfForm.team_name = $event"
        />

        <FestStudentPickerModal
            v-model="standbyPickerOpen"
            title="Pick standbys"
            subtitle="Optional substitutes — max 2"
            :entries="standbyRosterEntries"
            v-model:selected-ids="onBehalfForm.standby_ids"
            confirm-label="Use selection"
        />

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import FestStudentPickerModal from '@/Components/school/FestStudentPickerModal.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, registrations: Array, schools: Object,
    feeRequired: Boolean, activityLogs: { type: Array, default: () => [] },
    registerStudents: { type: Array, default: () => [] },
    registerSchoolId: { type: [String, Number], default: '' },
    eventItems: { type: Array, default: () => [] },
    schoolRegions: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({ search: '' }) },
    selectedHeadId: { type: [String, Number], default: null },
    selectedItemId: { type: [Number, String], default: null },
    competitionUrl: { type: String, default: null },
});

const filterDescription = computed(() => {
    if (props.selectedItemId) {
        return 'Review and approve registrations for a single competition item.';
    }
    if (props.selectedHeadId) {
        return props.event.event_type === 'sports'
            ? 'Filtered by Event Head — approve, reject, or register on behalf of schools.'
            : 'Filtered by item head — approve, reject, or register on behalf of schools.';
    }
    return 'Approve or reject school registrations. Register on behalf of a school when needed.';
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const filterSchoolId = ref('');
const filterItemId = ref('');
const filterStatus = ref('');
const searchQuery = ref(props.filters?.search ?? '');
const selectedIds = ref([]);
const overrideLifecycle = ref(false);
const substituteReg = ref(null);
const substituteForm = ref({ performer_id: '', standby_id: '' });
const onBehalfOpen = ref(false);
const performerPickerOpen = ref(false);
const standbyPickerOpen = ref(false);
const onBehalfSubmitting = ref(false);
const onBehalfForm = reactive({
    school_id: props.registerSchoolId ? String(props.registerSchoolId) : '',
    item_id: '',
    team_name: '',
    coach_name: '',
    coach_phone: '',
    manager_name: '',
    manager_phone: '',
    student_ids: [],
    standby_ids: [],
    auto_approve: true,
});
const selectedSchoolRegion = computed(() => {
    if (!onBehalfForm.school_id) return null;
    return props.schoolRegions?.[onBehalfForm.school_id]
        ?? props.schoolRegions?.[String(onBehalfForm.school_id)]
        ?? null;
});

watch(() => props.registerStudents, () => {
    if (onBehalfForm.school_id && !props.registerStudents.length && props.registerSchoolId) {
        // students loaded via page reload
    }
}, { deep: true });

watch(() => onBehalfForm.standby_ids, (ids) => {
    if (ids.length > 2) onBehalfForm.standby_ids = ids.slice(0, 2);
}, { deep: true });

const selectedItem = computed(() =>
    props.eventItems.find(i => String(i.id) === String(onBehalfForm.item_id)) ?? null,
);

const selectedItemIsGroup = computed(() =>
    selectedItem.value && ['group', 'team'].includes(selectedItem.value.participant_type),
);

const pickerSubtitle = computed(() => {
    if (!selectedItem.value) return 'Select an item first';
    const parts = [selectedItem.value.title];
    if (selectedItem.value.age_group && selectedItem.value.age_group !== 'open') {
        parts.push(String(selectedItem.value.age_group).toUpperCase());
    }
    return parts.join(' · ');
});

function studentMatchesItem(student, item) {
    if (props.event.academic_year_id && student.academic_year_id && props.event.academic_year_id !== student.academic_year_id) {
        return false;
    }
    if (props.event.event_type === 'kalolsavam') {
        if (!student.eligible_kalolsav) return false;
        if (item.class_group && item.class_group !== 'open' && student.kalolsav_class_group !== item.class_group) return false;
    }
    if (props.event.event_type === 'kids_fest') {
        if (!student.eligible_kids_fest) return false;
        if (item.kids_band && item.kids_band !== 'open' && student.kids_fest_band !== item.kids_band) return false;
    }
    if (props.event.event_type === 'sports') {
        if (!student.dob) return false;
        if (item.age_group && item.age_group !== 'open') {
            const groups = student.eligible_sports_groups ?? [];
            if (!groups.includes(item.age_group)) return false;
        }
    }
    if (item.gender && !['open', 'mixed'].includes(item.gender) && student.gender && student.gender !== item.gender) {
        return false;
    }
    return true;
}

function ineligibilityReason(student, item) {
    if (props.event.academic_year_id && student.academic_year_id && props.event.academic_year_id !== student.academic_year_id) {
        return 'Wrong academic year';
    }
    if (props.event.event_type === 'sports' && item?.age_group && item.age_group !== 'open') {
        const age = student.sports_age_on_cutoff;
        if (age != null) return `Age ${age} — needs ${String(item.age_group).toUpperCase()}`;
    }
    if (item?.gender && !['open', 'mixed'].includes(item.gender) && student.gender && student.gender !== item.gender) {
        return 'Gender mismatch';
    }
    return 'Not eligible for this item';
}

function buildRosterEntries(excludeIds = []) {
    const item = selectedItem.value;
    if (!item) return [];
    return (props.registerStudents ?? []).map((student) => {
        const eligible = !excludeIds.includes(student.id) && studentMatchesItem(student, item);
        return {
            id: student.id,
            name: student.name,
            regNo: student.reg_no || '',
            meta: [student.class_name, student.reg_no].filter(Boolean).join(' · '),
            eligible,
            reason: eligible ? null : ineligibilityReason(student, item),
        };
    });
}

const rosterEntries = computed(() => buildRosterEntries(onBehalfForm.standby_ids));
const standbyRosterEntries = computed(() => buildRosterEntries(onBehalfForm.student_ids));

function studentLabel(id) {
    const s = props.registerStudents.find(st => st.id === id);
    if (!s) return `#${id}`;
    return s.reg_no ? `${s.reg_no} · ${s.name}` : s.name;
}

function openOnBehalf() {
    onBehalfForm.school_id = filterSchoolId.value || onBehalfForm.school_id || '';
    onBehalfForm.item_id = '';
    onBehalfForm.team_name = '';
    onBehalfForm.coach_name = '';
    onBehalfForm.coach_phone = '';
    onBehalfForm.manager_name = '';
    onBehalfForm.manager_phone = '';
    onBehalfForm.student_ids = [];
    onBehalfForm.standby_ids = [];
    onBehalfOpen.value = true;
    if (onBehalfForm.school_id) loadSchoolStudents();
}

function loadSchoolStudents() {
    onBehalfForm.student_ids = [];
    onBehalfForm.standby_ids = [];
    if (!onBehalfForm.school_id) return;
    router.get(`${base}/registrations`, { school_id: onBehalfForm.school_id }, {
        preserveScroll: true,
        preserveState: true,
        only: ['registerStudents', 'registerSchoolId'],
    });
}

function submitOnBehalf() {
    if (!onBehalfForm.school_id || !onBehalfForm.item_id || !onBehalfForm.student_ids.length) return;
    onBehalfSubmitting.value = true;
    router.post(`${base}/registrations/on-behalf`, {
        school_id: onBehalfForm.school_id,
        item_id: onBehalfForm.item_id,
        team_name: onBehalfForm.team_name || null,
        coach_name: onBehalfForm.coach_name || null,
        coach_phone: onBehalfForm.coach_phone || null,
        manager_name: onBehalfForm.manager_name || null,
        manager_phone: onBehalfForm.manager_phone || null,
        student_ids: onBehalfForm.student_ids,
        standby_ids: onBehalfForm.standby_ids,
        auto_approve: onBehalfForm.auto_approve,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            onBehalfOpen.value = false;
            onBehalfSubmitting.value = false;
        },
        onError: () => { onBehalfSubmitting.value = false; },
        onFinish: () => { onBehalfSubmitting.value = false; },
    });
}

function applySearch() {
    router.get(`${base}/registrations`, {
        search: searchQuery.value || undefined,
        school_id: filterSchoolId.value || undefined,
    }, { preserveScroll: true, preserveState: true });
}

const filteredRegistrations = computed(() => {
    let list = props.registrations;
    if (filterSchoolId.value) {
        list = list.filter(r => String(r.school_id) === String(filterSchoolId.value));
    }
    if (filterItemId.value) {
        list = list.filter(r => String(r.item_id) === String(filterItemId.value));
    }
    if (filterStatus.value) {
        list = list.filter(r => r.status === filterStatus.value);
    }
    return list;
});

function statusClass(status) {
    return {
        submitted: 'bg-yellow-50 text-yellow-700',
        approved:  'bg-green-50 text-green-700',
        rejected:  'bg-red-50 text-red-600',
        withdrawn: 'bg-gray-100 text-gray-500',
    }[status] ?? 'bg-gray-50 text-gray-600';
}

function canCancel(reg) {
    return !['withdrawn', 'rejected'].includes(reg.status);
}

function performerCount(reg) {
    return reg.participants?.filter(p => p.participant_role !== 'standby').length ?? reg.participants?.length ?? 0;
}

function standbyCount(reg) {
    return reg.participants?.filter(p => p.participant_role === 'standby').length ?? 0;
}

function performers(reg) {
    return reg.participants?.filter(p => p.participant_role !== 'standby') ?? [];
}

function standbys(reg) {
    return reg.participants?.filter(p => p.participant_role === 'standby') ?? [];
}

function participantLabel(p) {
    return p.student?.name ?? p.teacher?.name ?? `#${p.id}`;
}

function openSubstitute(reg) {
    substituteReg.value = reg;
    const perf = performers(reg)[0];
    const stby = standbys(reg)[0];
    substituteForm.value = { performer_id: perf?.id ?? '', standby_id: stby?.id ?? '' };
}

function submitSubstitute() {
    const reg = substituteReg.value;
    if (!reg || !substituteForm.value.performer_id || !substituteForm.value.standby_id) return;
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/${reg.id}/substitute/${substituteForm.value.performer_id}/${substituteForm.value.standby_id}`,
        {},
        { preserveScroll: true, onSuccess: () => { substituteReg.value = null; } },
    );
}

function approve(id) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/${id}/approve`, {
        override_lifecycle: overrideLifecycle.value,
    }, { preserveScroll: true });
}

function reject(id) {
    if (confirm('Reject this registration?')) {
        router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/${id}/reject`, {
            override_lifecycle: overrideLifecycle.value,
        }, { preserveScroll: true });
    }
}

function cancel(id) {
    if (!confirm('Cancel this registration? The school will be notified.')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/${id}/cancel`, {}, { preserveScroll: true });
}

function bulkApprove() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/bulk-approve`, {
        registration_ids: selectedIds.value,
        override_lifecycle: overrideLifecycle.value,
    }, { preserveScroll: true, onSuccess: () => { selectedIds.value = []; } });
}

function bulkReject() {
    if (!confirm(`Reject ${selectedIds.value.length} registration(s)?`)) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/bulk-reject`, {
        registration_ids: selectedIds.value,
        override_lifecycle: overrideLifecycle.value,
    }, { preserveScroll: true, onSuccess: () => { selectedIds.value = []; } });
}

function approveSchool() {
    if (!filterSchoolId.value || !confirm('Approve all submitted registrations for this school?')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/bulk-approve`, {
        school_id: filterSchoolId.value,
        override_lifecycle: overrideLifecycle.value,
    }, { preserveScroll: true });
}

function approveItem() {
    if (!filterItemId.value || !confirm('Approve all submitted registrations for this item?')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/bulk-approve`, {
        item_id: filterItemId.value,
        override_lifecycle: overrideLifecycle.value,
    }, { preserveScroll: true });
}

function toggleSelectAll() {
    const ids = filteredRegistrations.value.filter(r => r.status === 'submitted').map(r => r.id);
    selectedIds.value = selectedIds.value.length === ids.length ? [] : ids;
}

// ── Sports helpers ────────────────────────────────────────────────────────────
const SPORTS_AGE_ORDER = ['u8', 'u10', 'u11', 'u12', 'u14', 'u17', 'u19', 'open'];

function ageGroupKey(reg) {
    return reg.item?.age_group
        ? String(reg.item.age_group).toLowerCase()
        : 'open';
}

function ageGroupLabel(key) {
    return key === 'open' ? 'Open' : String(key).toUpperCase();
}

import SportsRegistrationsTable from '@/Components/sahodaya/SportsRegistrationsTable.vue';

function genderLabel(gender) {
    const g = String(gender ?? '').toLowerCase();
    if (['male', 'm', 'boys', 'boy'].includes(g)) return 'Boys';
    if (['female', 'f', 'girls', 'girl'].includes(g)) return 'Girls';
    if (g === 'mixed') return 'Mixed';
    return gender ?? 'Open';
}

const sportsGroupedRegistrations = computed(() => {
    const grouped = {};
    for (const reg of filteredRegistrations.value) {
        const key = ageGroupKey(reg);
        const label = ageGroupLabel(key);
        if (!grouped[label]) grouped[label] = [];
        grouped[label].push(reg);
    }
    // Sort by SPORTS_AGE_ORDER
    const sorted = {};
    const orderMap = Object.fromEntries(SPORTS_AGE_ORDER.map((k, i) => [k, i]));
    Object.keys(grouped)
        .sort((a, b) => {
            const ka = a === 'Open' ? 'open' : a.toLowerCase();
            const kb = b === 'Open' ? 'open' : b.toLowerCase();
            return (orderMap[ka] ?? 99) - (orderMap[kb] ?? 99);
        })
        .forEach(label => { sorted[label] = grouped[label]; });
    return sorted;
});
</script>
