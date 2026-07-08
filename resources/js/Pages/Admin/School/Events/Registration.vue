<template>
    <SchoolAdminLayout :title="`${programLabel} Registration`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`${programLabel} Registration`"
            :eyebrow="programLabel"
                :description="isSports
                ? 'Register athletes for the event, then assign items by head (Athletics, etc.).'
                : isTeacherFest
                    ? 'Register teachers for open Teacher Fest events.'
                    : `Register students for open ${programLabel} events.`"
        >
            <template #actions>
                <button v-if="!isTeacherFest" type="button" class="btn-secondary text-sm" @click="showAddStudent = true">
                    {{ isLocked ? 'Request student' : '+ Add student' }}
                </button>
                <button v-if="events.length && isSports" type="button" class="btn-secondary text-sm" @click="showBulkAssign = !showBulkAssign">
                    Bulk assign items
                </button>
                <button v-if="events.length" type="button" class="btn-secondary text-sm" @click="showBulkImport = !showBulkImport">
                    Import CSV
                </button>
                <a :href="`${programBase}/reports`" class="btn-secondary text-sm">Reports →</a>
            </template>
        </PageHeader>

        <div v-if="schoolRegion?.applies" class="mb-5 max-w-2xl">
            <div v-if="schoolRegion.region" class="notice-banner notice-banner--info text-sm">
                <p>Your Kalotsav region: <strong>{{ schoolRegion.region }}</strong>.
                    <a :href="schoolRegion.set_url" class="link-brand font-semibold">Change →</a>
                </p>
            </div>
            <div v-else class="notice-banner notice-banner--warning text-sm">
                <p class="font-semibold">Select your Kalotsav region</p>
                <p class="mt-1">Your Sahodaya runs Kalotsav by region. Choose your region in
                    <a :href="schoolRegion.set_url" class="link-brand font-semibold">annual registration →</a>
                    (or ask your Sahodaya to assign it).
                </p>
            </div>
        </div>

        <SchoolEventWorkflowStepper v-if="singleEventMode && event?.id"
                                    :school-id="school.id"
                                    :program-prefix="programPrefix"
                                    :event-id="event.id"
                                    :is-sports="isSports"
                                    current-step="registration" />

        <div v-if="showBulkImport && events.length" class="card mb-5 max-w-2xl text-sm border-indigo-100">
            <div class="flex items-center justify-between gap-2 mb-3">
                <p class="font-semibold text-slate-800">Bulk import from CSV</p>
                <button type="button" class="text-slate-400 hover:text-slate-600 text-lg leading-none" @click="showBulkImport = false">×</button>
            </div>
            <p class="text-xs text-slate-500 mb-3">Columns: item_id, item_title, reg_no, team_name, role (performer|standby)</p>
            <div class="flex flex-wrap gap-2 items-end">
                <a :href="`${programBase}/import-template`"
                   class="btn-secondary text-xs">Download template</a>
                <select v-model="importEventId" class="field text-sm max-w-xs">
                    <option value="">Select event</option>
                    <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.title }}</option>
                </select>
                <input type="file" accept=".csv,text/csv" class="text-xs" @change="onImportFile">
                <button type="button" class="btn-primary text-xs" :disabled="!importEventId || !importFile || importForm.processing"
                        @click="submitImport">
                    Import CSV
                </button>
            </div>
            <ul v-if="$page.props.importErrors?.length" class="mt-3 text-xs text-red-600 list-disc pl-4">
                <li v-for="(err, i) in $page.props.importErrors" :key="i">{{ err }}</li>
            </ul>
        </div>

        <div v-if="showBulkAssign && events.length && isSports" class="card mb-5 max-w-3xl text-sm border-emerald-100">
            <div class="flex items-center justify-between gap-2 mb-3">
                <p class="font-semibold text-slate-800">Bulk assign athletes to items</p>
                <button type="button" class="text-slate-400 hover:text-slate-600 text-lg leading-none" @click="showBulkAssign = false">×</button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block text-sm">
                    <span class="text-gray-600">Event</span>
                    <select v-model="bulkAssignEventId" class="field mt-1">
                        <option value="">Select event</option>
                        <option v-for="ev in events" :key="ev.id" :value="ev.id">{{ ev.title }}</option>
                    </select>
                </label>
            </div>
            <p class="text-xs text-slate-500 mt-3">Select students and items on the registration grid below, then use bulk assign from the event row actions.</p>
            <button type="button" class="btn-primary text-xs mt-3" :disabled="!bulkAssignEventId || bulkAssignForm.processing"
                    @click="submitBulkAssign">
                Assign selected students to checked items
            </button>
        </div>

        <details v-if="!isTeacherFest && isSports" class="mb-5 max-w-3xl rounded-xl border border-slate-200/80 bg-slate-50/50 text-sm group">
            <summary class="px-4 py-3 cursor-pointer select-none font-medium text-slate-700 flex items-center justify-between gap-2">
                <span>How Sports Meet registration works</span>
                <span class="text-xs text-slate-400 group-open:hidden">Show tips</span>
            </summary>
            <div class="px-4 pb-4 pt-0 border-t border-slate-100">
                <ol class="list-decimal pl-4 space-y-1 text-slate-600 mt-3 mb-3">
                    <li><strong>Step 1 · Register for event</strong> — add athletes to the sports fest (event ID assigned).</li>
                    <li><strong>Step 2 · Register by item head</strong> — pick a head (Athletics, Field, Relay…), then add athletes to each item inside it.</li>
                    <li>Pay event + item fees in the billing section; Sahodaya approves → chest numbers on fest day.</li>
                </ol>
                <p class="text-xs text-slate-500">
                    <button type="button" class="link-brand font-semibold" @click="showAddStudent = true">Add student</button>
                    or
                    <a :href="`/school-admin/${school.id}/students`" class="link-brand font-semibold">manage students →</a>
                </p>
            </div>
        </details>

        <div v-else-if="!isTeacherFest" class="notice-banner notice-banner--info mb-6 max-w-3xl text-sm">
            Register students against each event item in the list below. Item fees are charged per registration;
            annual Sahodaya membership is paid separately under Annual Registration.
            <button type="button" class="link-brand font-semibold" @click="showAddStudent = true">Add student</button>
            or
            <a :href="`/school-admin/${school.id}/students`" class="link-brand font-semibold whitespace-nowrap">manage all students →</a>
        </div>

        <EmptyState
            v-if="!events.length"
            title="No events open for registration"
            description="Check back when Sahodaya or your school publishes an event with registration open."
            icon="📅"
        />

        <div v-else class="space-y-5">
            <div v-if="focusEventId && displayEvents.length === 1" class="notice-banner notice-banner--info text-sm mb-2">
                Showing Sahodaya event registration. <Link :href="`${programBase}/registration`" class="link-brand font-semibold">View all events</Link>
            </div>
            <div v-for="event in displayEvents" :key="event.id" class="card !p-0 overflow-hidden" :id="`event-${event.id}`">
                <!-- Event header -->
                <div class="px-5 py-4 border-b border-slate-100 bg-gradient-to-r from-slate-50/80 to-white">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-lg font-bold text-slate-900">{{ event.title }}</h3>
                            <p class="text-xs text-slate-500 mt-0.5">{{ event.payer_label }}</p>
                        </div>
                        <div class="flex flex-wrap gap-2 shrink-0">
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full capitalize"
                                  :class="statusClass(event.status)">
                                {{ statusLabel(event.status) }}
                            </span>
                            <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                                  :class="event.level_round === 'school' ? 'bg-violet-50 text-violet-700' : 'bg-blue-50 text-blue-700'">
                                {{ event.level_label || event.level_round }}
                            </span>
                            <a v-if="['ongoing','registration_open','published'].includes(event.status)"
                               :href="`${programBase}/fest-day/${event.id}`"
                               class="text-xs font-semibold text-indigo-600 px-2 py-1 rounded-full bg-indigo-50 hover:bg-indigo-100">
                                Fest day →
                            </a>
                            <a :href="`${programBase}/events/${event.id}/substitution-requests`"
                               class="text-xs font-semibold text-slate-600 px-2 py-1 rounded-full bg-slate-100 hover:bg-slate-200">
                                Substitutions
                            </a>
                            <a :href="`${programBase}/events/${event.id}/clash-requests`"
                               class="text-xs font-semibold text-slate-600 px-2 py-1 rounded-full bg-slate-100 hover:bg-slate-200">
                                Clash report
                            </a>
                        </div>
                    </div>
                    <div v-if="event.verification_status?.verification_day" class="mt-3 notice-banner text-xs"
                         :class="event.verification_status.documents_verified ? 'notice-banner--success' : 'notice-banner--warning'">
                        Verification day {{ event.verification_status.verification_day }} —
                        {{ event.verification_status.documents_verified ? 'Documents verified by Sahodaya' : 'Awaiting document verification' }}
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs">
                        <span v-if="event.academic_year_label"
                              class="inline-flex items-center gap-1 rounded-lg bg-violet-50 text-violet-800 px-2.5 py-1 border border-violet-100">
                            Academic year <strong class="font-mono">{{ event.academic_year_label }}</strong>
                        </span>
                        <span v-if="event.quotas && eventType === 'sports'"
                              class="inline-flex items-center gap-1 rounded-lg bg-emerald-50 text-emerald-800 px-2.5 py-1 border border-emerald-100">
                            <strong>{{ event.quotas.used.total }}</strong> school {{ event.quotas.used.total === 1 ? 'entry' : 'entries' }}
                        </span>
                        <span v-if="event.sports_age_cutoff_date"
                              class="inline-flex items-center gap-1 rounded-lg bg-slate-100 text-slate-600 px-2.5 py-1">
                            Age cutoff {{ event.sports_age_cutoff_date }}
                        </span>
                    </div>
                    <p v-if="event.age_rule_summary" class="text-xs text-indigo-800 mt-3 leading-relaxed">
                        {{ event.age_rule_summary }}
                    </p>
                </div>

                <div class="p-5">
                <!-- Kalotsav-style participation quotas -->
                <div v-if="event.quotas && eventType === 'kalolsavam'" class="grid sm:grid-cols-3 gap-2 mb-4">
                    <div class="bg-gray-50 border rounded-lg px-3 py-2 text-xs">
                        <span class="text-gray-500">On-stage</span>
                        <p class="font-semibold">{{ event.quotas.used.on_stage }}/{{ limitLabel(event.quotas.limits?.max_onstage_per_student) }}</p>
                    </div>
                    <div class="bg-gray-50 border rounded-lg px-3 py-2 text-xs">
                        <span class="text-gray-500">Off-stage</span>
                        <p class="font-semibold">{{ event.quotas.used.off_stage }}/{{ limitLabel(event.quotas.limits?.max_offstage_per_student) }}</p>
                    </div>
                    <div class="bg-gray-50 border rounded-lg px-3 py-2 text-xs">
                        <span class="text-gray-500">Group</span>
                        <p class="font-semibold">{{ event.quotas.used.group }}/{{ limitLabel(event.quotas.limits?.max_group_per_student) }}</p>
                    </div>
                </div>

                <div v-if="!canRegister(event)" class="bg-slate-50 border border-slate-200 rounded-xl p-4 text-sm text-slate-600">
                    {{ registrationClosedMessage(event) }}
                </div>

                <!-- ── SPORTS: event athletes + head/age filters ── -->
                <div v-else-if="isSports" class="space-y-4">
                    <div v-if="event.download_gate?.blocked"
                         class="notice-banner notice-banner--warning text-sm">
                        <p class="font-semibold">Payment pending — ID cards & hall tickets locked</p>
                        <p class="mt-0.5">{{ event.download_gate.reason }} Pay membership and event fees to download ID cards and admit cards.</p>
                        <p v-if="event.download_gate.links?.payments" class="mt-2">
                            <a :href="event.download_gate.links.payments" class="link-brand font-semibold">Go to payments →</a>
                        </p>
                    </div>

                    <SportsEventAthletesPanel
                        :event="event"
                        :students="studentsForEvent(event.id)"
                        :event-registrations="event.event_registrations ?? []"
                        :register-url="`${programBase}/events/${event.id}/register-students`"
                        :items-url="`${programBase}/events/${event.id}/items`"
                        :reports-href="`${programBase}/reports/${event.id}/registration-register`"
                        :student-event-reg-fee="Number(event.student_event_reg_fee ?? 0)"
                        :school-classes="schoolClasses"
                    />

                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-4 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h4 class="text-sm font-bold text-emerald-950">Step 2 · Register by item head</h4>
                            <p class="text-xs text-emerald-900/80 mt-0.5">
                                <strong>{{ eventRegisteredCount(event) }}</strong> event athlete{{ eventRegisteredCount(event) === 1 ? '' : 's' }}
                                · pick an item head (Athletics, Field events, Relay, etc.) and add them to its items.
                            </p>
                        </div>
                        <Link :href="`${programBase}/item-registration?event=${event.id}`"
                              class="btn-primary text-sm !min-h-0 shrink-0">
                            Register by item head →
                        </Link>
                    </div>
                </div>

                <!-- ── KALOTSAV / KIDS FEST / TEACHER FEST: generic flat table ── -->
                <form v-else class="mt-4 space-y-4" @submit.prevent>
                    <div class="rounded-xl border border-gray-100 overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 border-b border-gray-100">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide min-w-[160px]">Event item</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide min-w-[140px]">Eligibility</th>
                                        <th v-if="event.fee_required" class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-24">Item fee</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide min-w-[120px]">Registered</th>
                                        <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide w-48">
                                            {{ isTeacherFest ? 'Teachers' : 'Students' }}
                                        </th>
                                        <th class="px-3 py-2 w-24"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <FestRegistrationItemRow
                                        v-for="item in allItems(event)"
                                        :key="item.id"
                                        :row-id="itemRowId(event.id, item.id)"
                                        :item="item"
                                        :form="itemForms[itemFormKey(event.id, item.id)]"
                                        :registrations="registrationsForItem(event.id, item.id)"
                                        :eligible-students="eligibleStudentsForItem(event.id, item)"
                                        :all-students="studentsForEvent(event.id)"
                                        :student-ineligibility-reason="(student) => studentIneligibilityReason(student, event, item)"
                                        :show-fee="event.fee_required"
                                        :blocked="isItemBlocked(event, item)"
                                        :block-reason="itemBlockReason(event, item)"
                                        :error-message="itemErrors[itemFormKey(event.id, item.id)]"
                                        :status-label="itemStatusMeta(event, item).label"
                                        :status-class="itemStatusMeta(event, item).badgeClass"
                                        :status-hint="itemStatusMeta(event, item).hint"
                                        :performer-label="isTeacherFest ? 'teachers' : (isSports ? 'participants' : 'students')"
                                        :is-teacher-fest="isTeacherFest"
                                        :event-type="eventType"
                                        :teachers="teachers"
                                        :student-label="studentOptionLabel"
                                        :registered-names="registeredNames"
                                        :can-withdraw="canWithdraw"
                                        :column-count="event.fee_required ? 6 : 5"
                                        @register="submitItem(event, item)"
                                        @withdraw="withdraw"
                                        @add-student="showAddStudent = true"
                                    />
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>

                <!-- Item fees — separate from annual Sahodaya membership -->
                <div v-if="event.fee_required && event.school_fee" class="mt-4 border-t border-gray-100 pt-4 space-y-3">
                    <div>
                        <p class="text-xs font-semibold text-slate-800">Event fees & billing</p>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Includes per-student event registration (when athletes are registered above) plus item fees.
                            Annual Sahodaya membership is paid under
                            <a :href="`/school-admin/${school.id}/registration`" class="link-brand font-semibold">Annual Registration</a>.
                            <a :href="`${programBase}/reports/${event.id}/fee-summary`" class="link-brand font-semibold ml-1">Fee report →</a>
                        </p>
                    </div>
                    <div class="bg-indigo-50 border border-indigo-100 rounded-xl p-3 text-sm">
                        <ul v-if="itemFeeLines(event).length" class="text-xs text-indigo-900 space-y-1">
                            <li v-for="(line, i) in itemFeeLines(event)" :key="i" class="flex justify-between gap-4">
                                <span>{{ line.label }}</span>
                                <span class="font-semibold shrink-0">₹{{ formatMoney(line.amount) }}</span>
                            </li>
                        </ul>
                        <p v-else class="text-xs text-indigo-800">Register items above to see item fees here.</p>
                        <p class="font-semibold text-indigo-900 mt-2 pt-2 border-t border-indigo-100">
                            Item fees due: ₹{{ formatMoney(itemFeesDue(event)) }}
                            <span v-if="event.school_fee.participation_item_count" class="font-normal text-indigo-700">
                                ({{ event.school_fee.participation_item_count }} item{{ event.school_fee.participation_item_count === 1 ? '' : 's' }})
                            </span>
                        </p>
                        <div class="mt-2 flex flex-wrap gap-2 items-center">
                            <span v-if="event.school_fee.status === 'approved'" class="text-xs text-green-700 font-semibold">Payment approved</span>
                            <span v-else-if="event.school_fee.status === 'proof_uploaded'" class="text-xs text-amber-700 font-semibold">Payment pending approval</span>
                            <span v-else-if="event.school_fee.status === 'rejected'" class="text-xs text-red-600 font-semibold">Payment rejected — re-upload</span>
                            <form v-if="itemFeesDue(event) > 0 && ['pending', 'rejected'].includes(event.school_fee.status)"
                                  @submit.prevent="uploadEventPayment(event)" class="flex flex-wrap gap-2 items-center">
                                <input type="file" accept=".pdf,.jpg,.jpeg,.png"
                                       @change="e => eventPaymentFiles[event.id] = e.target.files[0]" class="text-xs">
                                <input v-model="eventPaymentRefs[event.id]" class="field text-xs w-36" placeholder="Txn ref (opt)">
                                <button type="submit" class="btn-secondary text-xs !min-h-0 !px-2 !py-1">Upload item fee proof</button>
                            </form>
                            <a v-if="event.school_fee.status === 'approved'"
                               :href="`${programBase}/events/${event.id}/receipt`"
                               target="_blank" rel="noopener"
                               class="px-2 py-1 bg-green-50 border border-green-300 text-green-700 text-xs font-semibold rounded">
                                View Receipt ↗
                            </a>
                            <a v-if="itemFeesDue(event) > 0"
                               :href="`${programBase}/events/${event.id}/invoice`"
                               target="_blank" rel="noopener"
                               class="px-2 py-1 bg-indigo-50 border border-indigo-300 text-indigo-700 text-xs font-semibold rounded">
                                Download Invoice ↗
                            </a>
                        </div>
                    </div>
                </div>
                <p v-else-if="canRegister(event) && !event.fee_required" class="text-xs text-gray-400 mt-4 border-t border-gray-100 pt-4">No fee for this round</p>
                </div>
            </div>
        </div>

        <QuickAddStudentModal
            v-model="showAddStudent"
            :school="school"
            :school-classes="schoolClasses"
            :student-edit-lock="studentEditLock"
        />
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import QuickAddStudentModal from '@/Components/school/QuickAddStudentModal.vue';
import FestRegistrationItemRow from '@/Components/school/FestRegistrationItemRow.vue';
import SportsEventAthletesPanel from '@/Components/school/SportsEventAthletesPanel.vue';
import SchoolEventWorkflowStepper from '@/Components/school/SchoolEventWorkflowStepper.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';
import { genderLabel } from '@/support/festItemEligibility.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    eventType: String,
    events: Array,
    focusEventId: { type: Number, default: null },
    singleEventMode: { type: Boolean, default: false },
    event: { type: Object, default: null },
    programPrefix: { type: String, default: '' },
    registrations: Array,
    students: Array,
    studentsByEvent: { type: Object, default: () => ({}) },
    lazyLoadStudents: { type: Boolean, default: false },
    studentCount: { type: Number, default: 0 },
    schoolClasses: { type: Array, default: () => [] },
    teachers: { type: Array, default: () => [] },
    isTeacherFest: { type: Boolean, default: false },
    studentEditLock: { type: Object, default: () => ({ locked: false }) },
    schoolRegion: { type: Object, default: null },
});

const { programSlug, programLabel, programBase } = useSchoolProgramContext(props);
const page = usePage();
const programPrefix = computed(() =>
    props.programPrefix
    || page.props.programPrefix
    || programBase.value.split('/').pop(),
);
const isSports = computed(() => props.eventType === 'sports' || programSlug.value === 'sports-meet');
const isLocked = computed(() => !!props.studentEditLock?.locked);

const displayEvents = computed(() => {
    if (!props.focusEventId) return props.events ?? [];
    return (props.events ?? []).filter((e) => e.id === props.focusEventId);
});

onMounted(() => {
    const urlHeadId = new URLSearchParams(usePage().url.split('?')[1] ?? '').get('head_id')
        ?? new URLSearchParams(usePage().url.split('?')[1] ?? '').get('head');

    for (const event of props.events ?? []) {
        if (props.eventType !== 'sports') continue;
        const heads = event.head_navigation?.headsForFilter ?? [];
        if (!heads.length) continue;

        if (!sportsHeadFilter[event.id]) {
            const matched = urlHeadId && heads.some((h) => String(h.id) === String(urlHeadId));
            sportsHeadFilter[event.id] = matched ? urlHeadId : heads[0].id;
        }
    }

    if (!props.focusEventId) return;
    requestAnimationFrame(() => {
        document.getElementById(`event-${props.focusEventId}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });

    if (props.lazyLoadStudents) {
        for (const event of props.events ?? []) {
            loadStudentsForEvent(event.id);
        }
    }
});

async function loadStudentsForEvent(eventId) {
    const map = props.studentsByEvent ?? {};
    if ((map[eventId] ?? map[String(eventId)] ?? []).length) {
        return;
    }
    if (fetchedStudentsByEvent[eventId]?.length) {
        return;
    }

    try {
        const res = await fetch(`${programBase.value}/events/${eventId}/eligible-students?json=1`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) return;
        const data = await res.json();
        fetchedStudentsByEvent[eventId] = data.students ?? [];
    } catch {
        // keep empty pool — user can refresh
    }
}

const importEventId = ref('');
const importFile = ref(null);
const importForm = useForm({ event_id: '', file: null });
const showAddStudent = ref(false);
const showBulkImport = ref(false);
const showBulkAssign = ref(false);
const bulkAssignEventId = ref('');
const bulkAssignStudentIds = ref([]);
const bulkAssignItemIds = ref([]);
const bulkAssignForm = useForm({ student_ids: [], item_ids: [] });
const sportsSearch = reactive({});
const sportsAgeFilter = reactive({});
const sportsHeadFilter = reactive({});
const sportsItemFilter = reactive({});
const fetchedStudentsByEvent = reactive({});

const kalotsavItemGroups = [
    { key: 'on_stage', label: 'On stage' },
    { key: 'off_stage', label: 'Off stage' },
    { key: 'group', label: 'Group / team' },
    { key: 'other', label: 'Other' },
];

const SPORTS_AGE_ORDER = ['u8', 'u10', 'u11', 'u12', 'u14', 'u17', 'u19', 'open'];
const SPORTS_MALE_VALS = new Set(['male', 'm', 'boys', 'boy']);
const SPORTS_FEMALE_VALS = new Set(['female', 'f', 'girls', 'girl']);

function eventRegisteredStudentIds(event) {
    return new Set((event.event_registrations ?? []).map((r) => r.student_id));
}

function eventRegisteredCount(event) {
    const fromEventList = (event.event_registrations ?? []).length;
    if (fromEventList > 0) return fromEventList;
    return studentsForEvent(event.id).filter(
        (s) => s.event_registered || s.event_registration_number,
    ).length;
}

function sportsItemsForFilters(event) {
    const headId = sportsHeadFilter[event.id] ?? '';
    const itemId = sportsItemFilter[event.id] ?? '';
    let items = event?.items ?? [];

    if (headId) {
        items = items.filter((i) => Number(i.head_id || 0) === Number(headId));
    }
    if (itemId) {
        items = items.filter((i) => Number(i.id) === Number(itemId));
    }

    const q = (sportsSearch[event.id] ?? '').trim().toLowerCase();
    if (q) {
        items = items.filter((i) => String(i.title ?? '').toLowerCase().includes(q));
    }

    return items;
}

function sportsHeadOptions(event) {
    return event.head_navigation?.headsForFilter ?? [];
}

function sportsItemOptions(event) {
    const headId = sportsHeadFilter[event.id];
    const groups = event.head_navigation?.headItemGroups ?? [];
    if (headId) {
        const head = groups.find((h) => Number(h.head_id) === Number(headId));
        return head?.items ?? [];
    }
    return groups.flatMap((h) => h.items ?? []);
}

function selectSportsHead(eventId, headId) {
    sportsHeadFilter[eventId] = headId;
    sportsItemFilter[eventId] = '';
}

function sportsGroups(event) {
    const items = sportsItemsForFilters(event);
    const labels = event?.item_group_labels ?? {};
    const students = studentsForEvent(event.id);
    const allRegs = props.registrations ?? [];
    const registeredStudentIds = eventRegisteredStudentIds(event);
    const headNameById = Object.fromEntries(
        (event.head_navigation?.headsForFilter ?? []).map((h) => [Number(h.id), h.name]),
    );

    const byAge = {};
    for (const item of items) {
        const key = item.age_group || 'open';
        if (!byAge[key]) byAge[key] = [];
        byAge[key].push(item);
    }

    return Object.keys(byAge)
        .filter((key) => (byAge[key]?.length ?? 0) > 0)
        .sort((a, b) => {
            const ai = SPORTS_AGE_ORDER.indexOf(a.toLowerCase());
            const bi = SPORTS_AGE_ORDER.indexOf(b.toLowerCase());
            return (ai < 0 ? 99 : ai) - (bi < 0 ? 99 : bi);
        })
        .map((key) => {
            const groupItems = byAge[key] ?? [];
            const headId = sportsHeadFilter[event.id] ? Number(sportsHeadFilter[event.id]) : null;
            const headName = headId ? (headNameById[headId] ?? null) : null;
            const label = labels[key] ?? String(key).toUpperCase();
            const itemIds = new Set(groupItems.map((i) => Number(i.id)));

            const eligiblePool = event.require_event_registration
                ? students.filter((s) => registeredStudentIds.has(s.id))
                : students;

            const eligibleCount = eligiblePool.filter(
                (s) => (s.eligible_sports_groups ?? []).map(g => g.toLowerCase()).includes(key.toLowerCase()),
            ).length;

            const registeredCount = allRegs.filter(
                (r) => Number(r.event_id) === Number(event.id)
                    && itemIds.has(Number(r.item_id))
                    && !['withdrawn', 'rejected'].includes(r.status),
            ).length;

            let openCount = 0;
            let noEligibleCount = 0;
            for (const item of groupItems) {
                const status = itemRegistrationStatus(event, item);
                if (status === 'open' || status === 'partial') openCount++;
                else if (status === 'no_eligible') noEligibleCount++;
            }

            const maleItems = groupItems.filter((i) => SPORTS_MALE_VALS.has(String(i.gender ?? '').toLowerCase()));
            const femaleItems = groupItems.filter((i) => SPORTS_FEMALE_VALS.has(String(i.gender ?? '').toLowerCase()));
            const openItems = groupItems.filter(
                (i) => !SPORTS_MALE_VALS.has(String(i.gender ?? '').toLowerCase())
                    && !SPORTS_FEMALE_VALS.has(String(i.gender ?? '').toLowerCase()),
            );

            const hasBoth = maleItems.length > 0 && femaleItems.length > 0;
            const genderGroups = [];
            if (maleItems.length) genderGroups.push({ gender: 'male', label: hasBoth ? 'Boys' : '', items: maleItems });
            if (femaleItems.length) genderGroups.push({ gender: 'female', label: hasBoth ? 'Girls' : '', items: femaleItems });
            if (openItems.length) {
                genderGroups.push({ gender: 'open', label: hasBoth ? 'Open / Mixed' : '', items: openItems });
            }
            if (!genderGroups.length) genderGroups.push({ gender: 'all', label: '', items: groupItems });

            return { key, label, headName, items: groupItems, eligibleCount, registeredCount, openCount, noEligibleCount, genderGroups };
        });
}

function filteredSportsGroups(event) {
    if (sportsHeadOptions(event).length && !sportsHeadFilter[event.id]) {
        return [];
    }
    const ageKey = sportsAgeFilter[event.id] ?? '';
    return sportsGroups(event).filter((group) => !ageKey || group.key === ageKey);
}

function sportsRegistrationSummary(event) {
    const groups = filteredSportsGroups(event);
    let open = 0;
    let registered = 0;
    let noMatch = 0;
    let total = 0;

    for (const group of groups) {
        for (const gg of group.genderGroups) {
            for (const item of gg.items) {
                total++;
                const status = itemRegistrationStatus(event, item);
                if (status === 'open' || status === 'partial') open++;
                else if (status === 'registered') registered++;
                else if (status === 'no_eligible') noMatch++;
            }
        }
    }

    if (!total) return '';

    const parts = [`${total} event${total === 1 ? '' : 's'}`];
    if (open) parts.push(`${open} open for registration`);
    if (registered) parts.push(`${registered} registered`);
    if (noMatch) parts.push(`${noMatch} need matching ${isSports.value ? 'participants' : 'students'}`);
    return parts.join(' · ');
}

function groupVisibleItemCount(group) {
    return group.genderGroups.reduce((n, gg) => n + gg.items.length, 0);
}

function clearSportsFilters(eventId) {
    sportsSearch[eventId] = '';
    sportsAgeFilter[eventId] = '';
    sportsItemFilter[eventId] = '';
    const event = props.events.find((e) => e.id === eventId);
    const heads = event?.head_navigation?.headsForFilter ?? [];
    sportsHeadFilter[eventId] = heads[0]?.id ?? '';
}

function onImportFile(e) {
    importFile.value = e.target.files[0] ?? null;
}

function submitImport() {
    importForm.event_id = importEventId.value;
    importForm.file = importFile.value;
    importForm.post(`${programBase.value}/import`, {
        forceFormData: true,
        preserveScroll: true,
    });
}

const itemGroups = kalotsavItemGroups;

function itemGroupsFor(event) {
    const grouped = event?.items_grouped ?? {};
    const labels = event?.item_group_labels ?? {};

    if (props.eventType === 'sports') {
        return Object.keys(grouped)
            .filter((key) => (grouped[key]?.length ?? 0) > 0)
            .map((key) => ({ key, label: labels[key] ?? String(key).toUpperCase() }));
    }

    if (props.eventType === 'kids_fest') {
        return Object.keys(grouped)
            .filter((key) => (grouped[key]?.length ?? 0) > 0)
            .map((key) => ({ key, label: labels[key] ?? 'Events' }));
    }

    return kalotsavItemGroups.filter((g) => (grouped[g.key]?.length ?? 0) > 0);
}

function limitLabel(val) {
    return val == null || val === '' ? '∞' : val;
}

const itemForms = reactive({});
const itemErrors = reactive({});
const eventPaymentFiles = reactive({});
const eventPaymentRefs = reactive({});

function allItemsStatic(event) {
    return event?.items ?? [];
}

function itemFormKey(eventId, itemId) {
    return `${eventId}-${itemId}`;
}

for (const e of props.events) {
    eventPaymentRefs[e.id] = '';
    sportsSearch[e.id] = '';
    sportsAgeFilter[e.id] = '';
    sportsHeadFilter[e.id] = '';
    sportsItemFilter[e.id] = '';
    for (const item of allItemsStatic(e)) {
        itemForms[itemFormKey(e.id, item.id)] = {
            team_name: '',
            student_ids: [],
            teacher_ids: [],
            standby_ids: [],
        };
    }
}

function allItems(event) {
    return event?.items ?? [];
}

function formatMoney(value) {
    const n = Number(value);
    if (Number.isNaN(n)) return '0.00';
    return n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function registrationsForItem(eventId, itemId) {
    return (props.registrations ?? []).filter(
        reg => Number(reg.event_id) === Number(eventId) && Number(reg.item_id) === Number(itemId)
            && !['withdrawn', 'rejected'].includes(reg.status),
    );
}

function registeredNames(reg) {
    const labels = (reg.participants ?? [])
        .filter(p => p.participant_role !== 'standby')
        .map((p) => {
            const name = p.student?.name ?? p.teacher?.name;
            const regNo = p.student?.reg_no ?? p.teacher?.reg_no;
            const festId = p.level_registration_number;
            if (name && festId) return `${name} (${festId})`;
            if (name && regNo) return `${name} (${regNo})`;
            return name ?? regNo;
        })
        .filter(Boolean);
    return labels.length ? labels.join(', ') : 'Registered';
}

function isGroupItemRow(item) {
    return item && ['group', 'team'].includes(item.participant_type);
}

function studentsForEvent(eventId) {
    const map = props.studentsByEvent ?? {};
    return fetchedStudentsByEvent[eventId]
        ?? map[eventId]
        ?? map[String(eventId)]
        ?? props.students
        ?? [];
}

function studentMatchesItem(student, event, item, { skipVerification = false } = {}) {
    if (!skipVerification && student.is_verified === false) {
        return false;
    }
    if (event?.academic_year_id && student.academic_year_id && event.academic_year_id !== student.academic_year_id) {
        return false;
    }
    if (props.eventType === 'kalolsavam') {
        if (!student.eligible_kalolsav) return false;
        if (item.class_group && item.class_group !== 'open' && student.kalolsav_class_group !== item.class_group) return false;
    }
    if (props.eventType === 'custom') {
        if (item.class_group && item.class_group !== 'open' && student.kalolsav_class_group !== item.class_group) return false;
    }
    if (props.eventType === 'kids_fest') {
        if (!student.eligible_kids_fest) return false;
        if (item.kids_band && item.kids_band !== 'open' && student.kids_fest_band !== item.kids_band) return false;
    }
    if (props.eventType === 'sports') {
        if (event?.require_event_registration && !eventRegisteredStudentIds(event).has(student.id)) {
            return false;
        }
        if (!student.dob) {
            return false;
        }
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

function eligibleStudentsForItem(eventId, item) {
    const event = props.events.find(e => e.id === eventId);
    const pool = studentsForEvent(eventId);
    let filtered = pool.filter(s => studentMatchesItem(s, event, item));
    if (event?.require_event_registration) {
        const registered = eventRegisteredStudentIds(event);
        filtered = filtered.filter((s) => registered.has(s.id));
    }
    return filtered;
}

function studentIneligibilityReason(student, event, item) {
    if (student.is_verified === false) {
        return 'Pending Sahodaya verification';
    }
    if (event?.academic_year_id && student.academic_year_id
        && Number(event.academic_year_id) !== Number(student.academic_year_id)) {
        return 'Not enrolled in this event\'s academic year';
    }

    const itemGender = String(item.gender ?? 'open').toLowerCase();
    const studentGender = String(student.gender ?? '').toLowerCase();
    if (!['open', 'mixed'].includes(itemGender)) {
        if (!studentGender || studentGender === 'open') {
            return 'Set gender on the student profile';
        }
        if (studentGender !== itemGender) {
            return `This item is for ${genderLabel(itemGender) ?? itemGender} only`;
        }
    }

    if (props.eventType === 'sports') {
        if (event?.require_event_registration && !eventRegisteredStudentIds(event).has(student.id)) {
            return 'Register for the event first (Event athletes section above)';
        }
        if (!student.dob) {
            return 'Date of birth is required for sports';
        }
        const itemAge = item.age_group;
        if (itemAge && itemAge !== 'open') {
            const groups = student.eligible_sports_groups ?? [];
            if (!groups.includes(itemAge)) {
                const underN = String(itemAge).replace(/^u/i, '');
                const ageHint = student.sports_age_on_cutoff != null
                    ? ` (age ${student.sports_age_on_cutoff} on cutoff)`
                    : '';
                return `Must be under ${underN} on cutoff date${ageHint} — not eligible for ${String(itemAge).toUpperCase()}`;
            }
        }
    }

    if (props.eventType === 'kalolsavam') {
        if (!student.eligible_kalolsav) return 'Not eligible for Kalotsav (Classes 3–12)';
        if (item.class_group && item.class_group !== 'open' && student.kalolsav_class_group !== item.class_group) {
            return classGroupMismatchReason(student, item);
        }
    }

    if (props.eventType === 'custom') {
        if (item.class_group && item.class_group !== 'open') {
            if (!student.kalolsav_class_group) return 'Class could not be mapped to a fest category';
            if (student.kalolsav_class_group !== item.class_group) {
                return classGroupMismatchReason(student, item);
            }
        }
    }

    if (props.eventType === 'kids_fest') {
        if (!student.eligible_kids_fest) return 'Not eligible for Kids Fest (Pre-KG to Class 2)';
    }

    return 'Not eligible for this item';
}

function itemFeeLines(event) {
    const lines = event.school_fee?.breakdown?.items ?? [];
    return lines.filter(line => !String(line.label).toLowerCase().includes('school registration'));
}

function itemFeesDue(event) {
    return itemFeeLines(event).reduce((sum, line) => sum + Number(line.amount || 0), 0);
}

function schoolMembershipFeeAmount(event) {
    const line = (event.school_fee?.breakdown?.items ?? []).find(
        l => String(l.label).toLowerCase().includes('school registration'),
    );
    return line?.amount ?? event.school_fee?.school_registration_fee ?? 0;
}

function canRegister(event) {
    if (event.school_fest_registration_closed || props.school?.fest_registration_closed) {
        return false;
    }
    if (event.registration_locked) {
        return false;
    }
    return event.status === 'registration_open';
}

function statusLabel(status) {
    const labels = {
        published: 'Published — registration not open',
        registration_open: 'Registration open',
        ongoing: 'Event ongoing',
        completed: 'Completed',
        draft: 'Draft',
    };
    return labels[status] ?? status;
}

function statusClass(status) {
    if (status === 'registration_open') return 'bg-green-50 text-green-700';
    if (status === 'published') return 'bg-amber-50 text-amber-800';
    if (status === 'ongoing') return 'bg-blue-50 text-blue-700';
    return 'bg-gray-100 text-gray-600';
}

function registrationClosedMessage(event) {
    if (event.school_fest_registration_closed || props.school?.fest_registration_closed) {
        return 'Fest registration has been closed for your school by Sahodaya. Contact your coordinator to reopen.';
    }
    if (event.registration_locked) {
        return 'Registration is locked for this event.';
    }
    if (event.status === 'published') {
        return 'This event is published but registration has not been opened yet. Check back when your Sahodaya opens registration.';
    }
    if (event.status === 'ongoing') {
        return 'Registration is closed — this event is already in progress.';
    }
    if (event.status === 'completed') {
        return 'This event has ended. Registration is no longer available.';
    }
    return 'Registration is not open for this event.';
}

function studentOptionLabel(student) {
    const parts = [];
    if (student.event_registration_number) {
        parts.push(`Fest ID ${student.event_registration_number}`);
    }
    if (student.reg_no) parts.push(student.reg_no);
    parts.push(student.class_name || 'no class');
    if (student.sports_age_on_cutoff != null) parts.push(`age ${student.sports_age_on_cutoff}`);
    if (student.sports_age_group) parts.push(String(student.sports_age_group).toUpperCase());
    if (student.kalolsav_class_group) parts.push(`Cat ${categoryShort(student)}`);
    if (student.kids_fest_band) parts.push(student.kids_fest_band);
    const g = genderLabel(student.gender);
    if (g) parts.push(g);
    return parts.join(' · ');
}

function categoryShort(student) {
    const map = { lp: '1', up: '2', hs: '3', hss: '4' };
    return map[student.kalolsav_class_group] ?? student.kalolsav_class_group;
}

function classGroupMismatchReason(student, item) {
    const labels = {
        lp: 'Classes 3 & 4',
        up: 'Classes 5, 6 & 7',
        hs: 'Classes 8, 9 & 10',
        hss: 'Classes 11 & 12',
    };
    const expected = labels[item.class_group] ?? item.class_group?.toUpperCase?.() ?? item.class_group;
    const actual = labels[student.kalolsav_class_group]
        ?? (student.class_name ? `Class ${student.class_name}` : student.kalolsav_class_group?.toUpperCase?.() ?? 'another category');

    return `Student is in ${actual}, but this item is for ${expected}`;
}

function performerCount(reg) {
    const performers = reg.participants?.filter(p => p.participant_role !== 'standby') ?? reg.participants ?? [];
    return performers.length;
}

function standbyCount(reg) {
    return reg.participants?.filter(p => p.participant_role === 'standby').length ?? 0;
}

function canWithdraw(reg) {
    if (['withdrawn', 'rejected'].includes(reg.status)) return false;
    const event = props.events.find(e => e.id === reg.event_id);
    if (!event) return reg.status === 'submitted';
    if (event.results_published || ['completed', 'cancelled'].includes(event.status)) return false;
    return event.status === 'registration_open' || reg.status === 'submitted';
}

function withdraw(id) {
    if (!confirm('Cancel this registration?')) return;
    router.post(`${programBase.value}/registrations/${id}/withdraw`, {}, { preserveScroll: true });
}

function itemRegistrationCount(eventId, itemId) {
    return registrationsForItem(eventId, itemId).length;
}

function itemMaxPerSchool(item) {
    const max = Number(item.max_per_school ?? 1);
    return max > 0 ? max : 1;
}

function isItemFull(event, item) {
    return itemRegistrationCount(event.id, item.id) >= itemMaxPerSchool(item);
}

function itemRegWindowMessage(item) {
    if (item.registration_open !== false) return '';
    if (item.reg_start && item.reg_end) {
        return `Registration for this item is closed (window was ${item.reg_start} – ${item.reg_end}).`;
    }
    if (item.reg_end) return `Registration for this item closed on ${item.reg_end}.`;
    if (item.reg_start) return `Registration for this item opens on ${item.reg_start}.`;
    return 'Registration is closed for this item.';
}

function itemBlockReason(event, item) {
    if (item.registration_open === false) {
        return itemRegWindowMessage(item);
    }

    if (isItemFull(event, item)) {
        const max = itemMaxPerSchool(item);
        return max === 1
            ? 'Your school already has an entry for this item (max 1 per school).'
            : `Maximum ${max} entries per school for this item — limit reached.`;
    }

    const quotas = event.quotas;
    if (!quotas) return '';

    const limits = quotas.limits ?? {};
    if (item.stage_type === 'on_stage' && limits.max_onstage_per_student != null
        && quotas.used.on_stage >= limits.max_onstage_per_student) {
        return `School on-stage participation limit reached (max ${limits.max_onstage_per_student}).`;
    }
    if (item.stage_type === 'off_stage' && limits.max_offstage_per_student != null
        && quotas.used.off_stage >= limits.max_offstage_per_student) {
        return `School off-stage participation limit reached (max ${limits.max_offstage_per_student}).`;
    }
    if (['group', 'team'].includes(item.participant_type) && limits.max_group_per_student != null
        && quotas.used.group >= limits.max_group_per_student) {
        return `School group/team participation limit reached (max ${limits.max_group_per_student}).`;
    }

    return '';
}

function isItemBlocked(event, item) {
    return Boolean(itemBlockReason(event, item));
}

function itemEligibleParticipantCount(eventId, item) {
    return eligibleStudentsForItem(eventId, item).length;
}

/** @deprecated alias */
function itemEligibleAthleteCount(eventId, item) {
    return itemEligibleParticipantCount(eventId, item);
}

function itemNoEligibleHint(event, item) {
    const pool = studentsForEvent(event.id);
    if (!pool.length) {
        return 'No students on record — add students first.';
    }

    const pendingVerify = pool.filter((s) => s.is_verified === false);
    const verifyBlockedMatches = pendingVerify.filter(
        (s) => studentMatchesItem(s, event, item, { skipVerification: true }),
    );

    if (verifyBlockedMatches.length > 0) {
        const noun = isSports.value ? 'participant' : 'student';
        if (pendingVerify.length === pool.length) {
            return `All ${noun}s are awaiting Sahodaya verification. Sahodaya admin: Membership → Student verification.`;
        }

        return `${verifyBlockedMatches.length} ${noun}${verifyBlockedMatches.length === 1 ? '' : 's'} match this item but need Sahodaya verification first.`;
    }

    const teamMin = Number(item.min_group_size ?? item.criteria_json?.min_playing ?? 0);
    if (teamMin > 1 && ['group', 'team'].includes(item.participant_type)) {
        const matching = pool.filter((s) => studentMatchesItem(s, event, item, { skipVerification: true })).length;
        if (matching > 0 && matching < teamMin) {
            return `This team item needs ${teamMin} students — only ${matching} eligible on record.`;
        }
    }

    if (isSports.value) {
        return 'No participants meet age/gender for this item.';
    }

    return 'No students match this item (class category, gender, or academic year).';
}

function itemRegistrationStatus(event, item) {
    if (item.registration_open === false) {
        return 'closed';
    }

    const regs = itemRegistrationCount(event.id, item.id);
    const max = itemMaxPerSchool(item);

    if (isItemFull(event, item)) {
        return regs > 0 ? 'registered' : 'full';
    }

    if (itemEligibleParticipantCount(event.id, item) === 0) {
        return 'no_eligible';
    }

    if (regs > 0 && max > 1) {
        return 'partial';
    }

    return 'open';
}

function itemStatusMeta(event, item) {
    const status = itemRegistrationStatus(event, item);
    const eligible = itemEligibleParticipantCount(event.id, item);
    const regs = itemRegistrationCount(event.id, item.id);
    const max = itemMaxPerSchool(item);

    if (status === 'registered') {
        return {
            label: 'Registered',
            badgeClass: 'bg-emerald-50 text-emerald-700 border-emerald-100',
            hint: max === 1 ? 'Entry submitted for this event' : `${regs} of ${max} school entries used`,
        };
    }

    if (status === 'full') {
        return {
            label: 'Full',
            badgeClass: 'bg-amber-50 text-amber-800 border-amber-100',
            hint: '',
        };
    }

    if (status === 'no_eligible') {
        return {
            label: 'No match',
            badgeClass: 'bg-slate-100 text-slate-600 border-slate-200',
            hint: itemNoEligibleHint(event, item),
        };
    }

    if (status === 'closed') {
        return {
            label: 'Closed',
            badgeClass: 'bg-amber-50 text-amber-800 border-amber-100',
            hint: itemRegWindowMessage(item),
        };
    }

    const participantNoun = isSports.value ? 'participant' : 'student';

    if (status === 'partial') {
        return {
            label: 'Open',
            badgeClass: 'bg-indigo-50 text-indigo-700 border-indigo-100',
            hint: `${regs}/${max} entries · ${eligible} ${participantNoun}${eligible === 1 ? '' : 's'} eligible`,
        };
    }

    return {
        label: 'Open',
        badgeClass: 'bg-indigo-50 text-indigo-700 border-indigo-100',
        hint: `${eligible} ${participantNoun}${eligible === 1 ? '' : 's'} can register`,
    };
}

function itemRowId(eventId, itemId) {
    return `reg-item-${itemFormKey(eventId, itemId)}`;
}

function extractItemErrors(errors, itemId) {
    const key = `items.${itemId}`;
    const messages = errors?.[key];
    if (Array.isArray(messages)) return messages.join(' ');
    if (typeof messages === 'string') return messages;
    return '';
}

function scrollToItemRow(eventId, itemId) {
    const el = document.getElementById(itemRowId(eventId, itemId));
    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function submitItem(event, item) {
    const key = itemFormKey(event.id, item.id);
    const form = itemForms[key];
    const standby = (form.standby_ids ?? []).slice(0, 2);

    delete itemErrors[key];

    if (isItemBlocked(event, item)) {
        itemErrors[key] = itemBlockReason(event, item);
        scrollToItemRow(event.id, item.id);
        return;
    }

    if (!['group', 'team'].includes(item.participant_type) && (form.student_ids?.length ?? 0) > 1) {
        itemErrors[key] = 'This item allows only one participant.';
        scrollToItemRow(event.id, item.id);
        return;
    }

    router.post(`${programBase.value}/register`, {
        event_id: event.id,
        item_id: item.id,
        team_name: form.team_name,
        student_ids: form.student_ids,
        teacher_ids: form.teacher_ids,
        standby_ids: standby,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            delete itemErrors[key];
            form.student_ids = [];
            form.teacher_ids = [];
            form.standby_ids = [];
            form.team_name = '';
        },
        onError: (errors) => {
            itemErrors[key] = extractItemErrors(errors, item.id)
                || errors.registration
                || page.props.flash?.error
                || 'Could not register for this item.';
            scrollToItemRow(event.id, item.id);
        },
    });
}

function submitBulkAssign() {
    const event = props.events.find((ev) => ev.id === Number(bulkAssignEventId.value));
    if (!event) return;

    const studentIds = Object.values(itemForms)
        .flatMap((form) => form.student_ids ?? [])
        .filter(Boolean);
    const itemIds = (event.items ?? []).filter((item) => bulkAssignItemIds.value.includes(item.id)).map((i) => i.id);

    if (!studentIds.length || !itemIds.length) {
        alert('Pick athletes in item rows and select items for bulk assign.');
        return;
    }

    bulkAssignForm.student_ids = [...new Set(studentIds)];
    bulkAssignForm.item_ids = itemIds;
    bulkAssignForm.post(`${programBase.value}/events/${event.id}/bulk-assign`, {
        preserveScroll: true,
        onSuccess: () => {
            bulkAssignForm.reset();
            showBulkAssign.value = false;
        },
    });
}

function uploadEventPayment(event) {
    const file = eventPaymentFiles[event.id];
    if (!file) {
        alert('Choose a payment proof file first, or skip — registration does not require it.');
        return;
    }
    router.post(`${programBase.value}/events/${event.id}/payment`, {
        payment_proof: file,
        transaction_ref: eventPaymentRefs[event.id] || null,
    }, { forceFormData: true, preserveScroll: true });
}
</script>
