<template>
    <SahodayaEventsLayout :title="`${event.title} — Phases`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Phases`" eyebrow="Rounds & levels"
                    description="Split items into named phases (e.g. Digi Fest day, Off-stage day, On-stage day). Optional — leave every item unassigned to run the event as a single phase." />

        <EventSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="phases" />

        <div class="mb-4 flex items-center gap-4 flex-wrap">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/levels`" class="link-brand text-sm">&larr; Back to Rounds & Levels</Link>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/phase-plan-wizard`" class="btn-secondary text-xs">
                ⚡ Set up in bulk with the Phase Plan Wizard
            </Link>
        </div>

        <div v-if="conductSystemLocked === 'partitioned'" class="mb-4 max-w-5xl rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
            This event currently uses region partitioning (Rounds &amp; Levels). Phases and items here stay usable, but creating a payment batch will be blocked while those partitions already have registrations.
        </div>

        <div v-if="needsBatchBeforeRoutingWorks" class="mb-4 max-w-5xl rounded-xl border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Phases are set up, but no payment batch exists yet — item-phase assignment has no effect on registration routing until you add at least one payment batch below.
        </div>

        <FestPhaseWorkflowNav class="max-w-5xl" :batches="registrationBatches" :phases="phases" />

        <section class="card mb-6 max-w-5xl space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="section-title">Payment batches</h3>
                    <p class="section-desc">Group phases into payment levels (e.g. Level 1 / Level 2, each with its own school registration fee and invoice) so a school pays once per level instead of once per phase. Optional — skip this if the event just bills its flat registration fee.</p>
                </div>
                <button v-if="registrationBatches.length" type="button" class="btn-secondary text-sm" :disabled="topologyForm.processing" @click="syncTopology">Sync operational events</button>
            </div>

            <form v-if="showAddBatch" @submit.prevent="createBatch" class="space-y-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                <div class="grid grid-cols-2 gap-2">
                    <input v-model="addBatchForm.name" class="field text-sm" placeholder="Batch name (e.g. Level 1)" required>
                    <input v-model="addBatchForm.code" class="field text-sm uppercase" placeholder="Code (e.g. LEVEL_1)" required>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-xs text-slate-500">
                        Registration opens
                        <input v-model="addBatchForm.registration_open" type="datetime-local" class="field text-sm mt-0.5">
                    </label>
                    <label class="text-xs text-slate-500">
                        Registration closes
                        <input v-model="addBatchForm.registration_close" type="datetime-local" class="field text-sm mt-0.5">
                    </label>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <label class="text-xs text-slate-500">
                        School base fee (₹)
                        <input v-model.number="addBatchForm.school_base_fee" type="number" min="0" step="0.01" class="field text-sm mt-0.5">
                    </label>
                    <label class="text-xs text-slate-500">
                        Invoice prefix
                        <input v-model="addBatchForm.invoice_prefix" class="field text-sm mt-0.5" placeholder="e.g. MCS-L1">
                    </label>
                </div>
                <label class="text-xs text-slate-500 block">
                    Student registration fee (₹ per student, optional)
                    <input v-model.number="addBatchForm.student_registration_fee" type="number" min="0" step="0.01" class="field text-sm mt-0.5" placeholder="Leave blank to use the event's own per-student rate">
                    <span class="text-[11px] text-slate-400">Charged once per student who registers under this batch — separate from, and on top of, the school base fee above. Leave blank unless this batch needs its own rate.</span>
                </label>
                <p v-if="addBatchForm.errors.code" class="text-xs text-red-600">{{ addBatchForm.errors.code }}</p>
                <div class="flex gap-2">
                    <button type="submit" class="btn-primary text-sm" :disabled="addBatchForm.processing">Add batch</button>
                    <button type="button" class="btn-ghost text-sm" @click="showAddBatch = false">Cancel</button>
                </div>
            </form>
            <button v-else type="button" class="btn-secondary text-sm w-full" @click="showAddBatch = true">+ Add payment batch</button>

            <div v-if="registrationBatches.length === 0" class="text-sm text-slate-400">
                No payment batches yet — every phase bills through the event's own single registration fee.
            </div>
            <ul v-else class="divide-y divide-slate-100 text-sm">
                <li v-for="batch in registrationBatches" :key="batch.id" class="py-2 flex items-center justify-between gap-2">
                    <template v-if="editBatchId === batch.id">
                        <div class="flex-1 space-y-2">
                            <div class="grid grid-cols-2 gap-2">
                                <input v-model="editBatchForm.name" class="field !py-1 !text-xs">
                                <input v-model="editBatchForm.code" class="field !py-1 !text-xs uppercase">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <input v-model.number="editBatchForm.school_base_fee" type="number" min="0" step="0.01" class="field !py-1 !text-xs" placeholder="School base fee">
                                <input v-model="editBatchForm.invoice_prefix" class="field !py-1 !text-xs" placeholder="Invoice prefix">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <input v-model="editBatchForm.registration_open" type="datetime-local" class="field !py-1 !text-xs">
                                <input v-model="editBatchForm.registration_close" type="datetime-local" class="field !py-1 !text-xs">
                            </div>
                            <input v-model.number="editBatchForm.student_registration_fee" type="number" min="0" step="0.01" class="field !py-1 !text-xs w-full" placeholder="Student registration fee (₹/student, blank = use event default)">
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <button type="button" class="text-xs font-semibold text-[#0f3d7a]" @click="saveBatchEdit(batch)">Save</button>
                            <button type="button" class="text-xs text-slate-500" @click="editBatchId = null">Cancel</button>
                        </div>
                    </template>
                    <template v-else>
                        <div>
                            <span class="font-semibold text-slate-700">{{ batch.name }}</span>
                            <span class="ml-2 text-xs font-mono text-slate-400">{{ batch.code }}</span>
                            <div class="text-xs text-slate-400 mt-0.5">
                                <span v-if="batch.school_base_fee">₹{{ batch.school_base_fee }} base fee</span>
                                <span v-if="batch.student_registration_fee" class="ml-2">₹{{ batch.student_registration_fee }}/student</span>
                                <span v-if="batch.registration_open || batch.registration_close" class="ml-2">
                                    Reg: {{ formatDate(batch.registration_open) }} &rarr; {{ formatDate(batch.registration_close) }}
                                </span>
                            </div>
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <button type="button" class="text-xs font-semibold text-[#0f3d7a]" @click="startBatchEdit(batch)">Edit</button>
                            <button type="button" class="text-xs text-red-600" @click="removeBatch(batch)">Remove</button>
                        </div>
                    </template>
                </li>
            </ul>
        </section>

        <div class="grid lg:grid-cols-2 gap-6 max-w-5xl">
            <div class="card space-y-4">
                <h4 class="section-title">Phases ({{ phases.length }})</h4>

                <form v-if="showAdd" @submit.prevent="createPhase" class="space-y-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                    <input v-model="addForm.name" class="field text-sm" placeholder="Phase name (e.g. Off-stage Day)" required>
                    <div class="grid grid-cols-2 gap-2">
                        <input v-model="addForm.code" class="field text-sm" placeholder="Code (optional)">
                        <input v-model.number="addForm.sort_order" type="number" class="field text-sm" placeholder="Sort order">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="text-xs text-slate-500">
                            Registration opens
                            <input v-model="addForm.registration_open" type="datetime-local" class="field text-sm mt-0.5">
                        </label>
                        <label class="text-xs text-slate-500">
                            Registration closes
                            <input v-model="addForm.registration_close" type="datetime-local" class="field text-sm mt-0.5">
                        </label>
                    </div>
                    <label class="text-xs text-slate-500 block">
                        School registration fee collected by this phase (₹)
                        <input v-model.number="addForm.school_registration_fee_share" type="number" min="0" step="0.01" class="field text-sm mt-0.5" placeholder="0.00 — leave blank if this phase charges none">
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <select v-model="addForm.registration_batch_id" class="field text-sm">
                            <option :value="null">No payment level</option>
                            <option v-for="batch in registrationBatches" :key="batch.id" :value="batch.id">{{ batch.name }}</option>
                        </select>
                        <label class="flex items-center gap-2 text-xs"><input v-model="addForm.is_regional" type="checkbox"> Regional phase</label>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary text-sm" :disabled="addForm.processing">Add phase</button>
                        <button type="button" class="btn-ghost text-sm" @click="showAdd = false">Cancel</button>
                    </div>
                </form>
                <button v-else type="button" class="btn-secondary text-sm w-full" @click="showAdd = true">+ Add phase</button>

                <div v-if="feeShareWarning" class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    {{ feeShareWarning }}
                </div>

                <div v-if="phases.length === 0" class="text-sm text-slate-400">
                    No phases yet — every item runs as a single, unnamed phase.
                </div>
                <ul v-else class="divide-y divide-slate-100 text-sm">
                    <li v-for="phase in phases" :key="phase.id" class="py-2">
                        <div v-if="editId === phase.id" class="flex items-center justify-between gap-2">
                            <div class="flex-1 space-y-2">
                                <div class="grid grid-cols-2 gap-2">
                                    <input v-model="editForm.name" class="field !py-1 !text-xs">
                                    <input v-model="editForm.code" class="field !py-1 !text-xs" placeholder="Code">
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="text-[11px] text-slate-500">
                                        Reg. opens
                                        <input v-model="editForm.registration_open" type="datetime-local" class="field !py-1 !text-xs mt-0.5">
                                    </label>
                                    <label class="text-[11px] text-slate-500">
                                        Reg. closes
                                        <input v-model="editForm.registration_close" type="datetime-local" class="field !py-1 !text-xs mt-0.5">
                                    </label>
                                </div>
                                <label class="text-[11px] text-slate-500 block">
                                    School reg. fee share (₹)
                                    <input v-model.number="editForm.school_registration_fee_share" type="number" min="0" step="0.01" class="field !py-1 !text-xs mt-0.5">
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <select v-model="editForm.registration_batch_id" class="field !py-1 !text-xs">
                                        <option :value="null">No payment level</option>
                                        <option v-for="batch in registrationBatches" :key="batch.id" :value="batch.id">{{ batch.name }}</option>
                                    </select>
                                    <label class="flex items-center gap-2 text-[11px]"><input v-model="editForm.is_regional" type="checkbox"> Regional</label>
                                </div>
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <button type="button" class="text-xs font-semibold text-[#0f3d7a]" @click="saveEdit(phase)">Save</button>
                                <button type="button" class="text-xs text-slate-500" @click="editId = null">Cancel</button>
                            </div>
                        </div>
                        <div v-else class="flex items-center justify-between gap-2">
                            <div>
                                <span class="font-semibold text-slate-700">{{ phase.name }}</span>
                                <span v-if="phase.code" class="ml-2 text-xs font-mono text-slate-400">{{ phase.code }}</span>
                                <span class="ml-2 text-xs text-slate-400">{{ itemCountForPhase(phase.id) }} item(s)</span>
                                <div class="text-xs text-slate-400 mt-0.5">
                                    <span v-if="phase.registration_open || phase.registration_close">
                                        Reg: {{ formatDate(phase.registration_open) }} &rarr; {{ formatDate(phase.registration_close) }}
                                    </span>
                                    <span v-if="phase.school_registration_fee_share" class="ml-2">
                                        School fee: ₹{{ phase.school_registration_fee_share }}
                                    </span>
                                    <span v-if="phase.registration_batch" class="ml-2">{{ phase.registration_batch.name }}</span>
                                    <span v-if="phase.is_regional" class="ml-2 text-indigo-600">Regional: {{ phase.allowed_regions?.filter((r) => r.enabled).map((r) => r.region?.name).join(', ') || 'not configured' }}</span>
                                </div>
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <button v-if="phase.is_regional && regions.length" type="button" class="text-xs font-semibold text-indigo-600" @click="startRegionEdit(phase)">Regions</button>
                                <button type="button" class="text-xs font-semibold text-[#0f3d7a]" @click="startEdit(phase)">Edit</button>
                                <button type="button" class="text-xs text-red-600" @click="removePhase(phase)">Remove</button>
                            </div>
                        </div>
                        <div v-if="regionEditId === phase.id" class="mt-2 rounded-xl border border-indigo-200 bg-indigo-50/50 p-3 space-y-2">
                            <div class="flex items-center justify-between">
                                <p class="text-xs font-semibold text-slate-600">Allowed regions for {{ phase.name }}</p>
                                <p class="text-[11px] text-slate-500">{{ regionEditIds.length }} selected</p>
                            </div>
                            <div v-if="regions.length" class="grid sm:grid-cols-2 gap-1.5 max-h-48 overflow-y-auto rounded-lg border border-indigo-100 bg-white p-2">
                                <label v-for="region in regions" :key="region.id"
                                       class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-xs cursor-pointer hover:bg-indigo-50 transition"
                                       :class="regionEditIds.includes(region.id) ? 'bg-indigo-50 text-indigo-900 font-semibold' : 'text-slate-700'">
                                    <input type="checkbox" :value="region.id" v-model="regionEditIds" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-400">
                                    <span>{{ region.name }}</span>
                                </label>
                            </div>
                            <p v-else class="text-xs text-slate-400">No active regions configured for this Sahodaya yet.</p>
                            <div class="flex gap-2">
                                <button type="button" class="btn-primary text-xs" :disabled="regionEditForm.processing" @click="saveRegionEdit(phase)">Save regions</button>
                                <button type="button" class="btn-ghost text-xs" @click="regionEditId = null">Cancel</button>
                            </div>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="card space-y-4">
                <h4 class="section-title">Assign items to a phase</h4>
                <p class="section-desc">Select items below, pick a phase, then assign. Items left unassigned belong to no phase (fine if you don't need phase-wise conduct).</p>

                <div v-if="items.length === 0" class="text-sm text-slate-400">No items on this event yet.</div>
                <template v-else>
                    <input v-model="itemSearch" type="search" class="field text-sm" placeholder="Search items by title, code, or category…">

                    <div class="flex items-center gap-2">
                        <select v-model="assignPhaseId" class="field text-sm flex-1">
                            <option :value="null">— No phase (unassign) —</option>
                            <option v-for="phase in phases" :key="phase.id" :value="phase.id">{{ phase.name }}</option>
                        </select>
                        <button type="button" class="btn-primary text-sm shrink-0" :disabled="selectedItemIds.length === 0 || assignForm.processing" @click="assignItems">
                            Assign ({{ selectedItemIds.length }})
                        </button>
                    </div>

                    <p class="text-xs text-slate-400">{{ filteredItems.length }} of {{ items.length }} item(s)</p>

                    <div class="max-h-96 overflow-y-auto rounded-xl border border-slate-200">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500 sticky top-0">
                                <tr>
                                    <th class="p-2 w-8"><input type="checkbox" :checked="allSelected" @change="toggleSelectAll"></th>
                                    <th class="p-2">Item</th>
                                    <th class="p-2">Phase</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="item in filteredItems" :key="item.id" class="bg-white">
                                    <td class="p-2 align-top"><input type="checkbox" :value="item.id" v-model="selectedItemIds"></td>
                                    <td class="p-2">
                                        <div>
                                            {{ item.title }}
                                            <span v-if="item.item_code" class="ml-1 text-xs font-mono text-slate-400">{{ item.item_code }}</span>
                                        </div>
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            <span v-if="item.stage_type" class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700 border border-indigo-100">{{ formatLabel(item.stage_type) }}</span>
                                            <span v-if="item.gender" class="inline-flex items-center rounded-full bg-violet-50 px-2 py-0.5 text-[10px] font-semibold text-violet-700 border border-violet-100">{{ formatLabel(item.gender) }}</span>
                                            <span v-if="item.participant_type" class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600 border border-slate-200">{{ formatLabel(item.participant_type) }}</span>
                                        </div>
                                    </td>
                                    <td class="p-2 text-slate-600 align-top">{{ item.phase_name || '—' }}</td>
                                </tr>
                                <tr v-if="filteredItems.length === 0">
                                    <td colspan="3" class="p-4 text-center text-sm text-slate-400">No items match "{{ itemSearch }}".</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8 max-w-5xl" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import FestPhaseWorkflowNav from '@/Components/sahodaya/FestPhaseWorkflowNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    phases: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    registrationBatches: { type: Array, default: () => [] },
    regions: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    conductSystemLocked: { type: String, default: null },
});

// Phase assignment has no effect on registration routing until a payment batch exists
// (that's what flips workflow_mode). Condition-driven, not just a one-time flash, so it
// still catches an admin who comes back later having forgotten — see
// FestEventPhaseController::assignItems()'s matching backend warning.
const needsBatchBeforeRoutingWorks = computed(() => props.phases.length > 0 && props.registrationBatches.length === 0);

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const { confirm } = useConfirm();

const showAdd = ref(false);
const editId = ref(null);
const selectedItemIds = ref([]);
const assignPhaseId = ref(null);
const itemSearch = ref('');

const filteredItems = computed(() => {
    const q = itemSearch.value.trim().toLowerCase();
    if (!q) return props.items;

    return props.items.filter((item) =>
        item.title?.toLowerCase().includes(q)
        || item.item_code?.toLowerCase().includes(q)
        || item.category?.toLowerCase().includes(q)
    );
});

function formatLabel(value) {
    if (!value) return '';
    return value.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase());
}

const addForm = useForm({
    name: '', code: '', sort_order: null, is_default: false,
    registration_open: '', registration_close: '', school_registration_fee_share: null,
    registration_batch_id: null, is_regional: false,
});
const editForm = reactive({ name: '', code: '', registration_open: '', registration_close: '', school_registration_fee_share: null, registration_batch_id: null, is_regional: false });
const assignForm = useForm({ phase_id: null, item_ids: [] });

// Payment batches — a Sahodaya defines however many of these it needs (0, 1, 2, or more),
// with whatever codes/names/fees fit its own event. Creating the first one is what turns
// on the phased/regional engine for this event (see FestRegistrationBatchController::store).
const showAddBatch = ref(false);
const editBatchId = ref(null);
const addBatchForm = useForm({
    name: '', code: '', school_base_fee: 0, student_registration_fee: null, invoice_prefix: '',
    registration_open: '', registration_close: '',
});
const editBatchForm = reactive({ name: '', code: '', school_base_fee: 0, student_registration_fee: null, invoice_prefix: '', registration_open: '', registration_close: '' });

// Per-phase allowed-region editing — posts to the existing generic
// phases/{phase}/regions endpoint (FestPhasedWorkflowController::syncPhaseRegions), which
// already accepts any phase and any region list, not just a fixed pair of phase names.
const regionEditId = ref(null);
const regionEditIds = ref([]);
const regionEditForm = useForm({ region_ids: [] });

const topologyForm = useForm({});

const allSelected = computed(() => filteredItems.value.length > 0 && filteredItems.value.every((item) => selectedItemIds.value.includes(item.id)));

function itemCountForPhase(phaseId) {
    return props.items.filter((i) => i.phase_id === phaseId).length;
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
}

// Phase C: the event's flat school registration fee (event.school_registration_fee) can be
// owned entirely by one phase or split across several via each phase's
// school_registration_fee_share — see docs/KALOTSAV_PHASED_LEVEL_FEE_PLAN.md §3 item 4. This
// is a soft warning only (a Sahodaya may legitimately want some phases to charge nothing extra
// beyond participation fees), not a validation block.
const feeShareWarning = computed(() => {
    const nominal = props.event?.school_registration_fee;
    if (nominal === null || nominal === undefined || Number(nominal) === 0) return null;
    if (props.phases.length === 0) return null;

    const sharesSet = props.phases.some((p) => p.school_registration_fee_share !== null && p.school_registration_fee_share !== undefined);
    if (!sharesSet) return null;

    const total = props.phases.reduce((sum, p) => sum + Number(p.school_registration_fee_share || 0), 0);
    const nominalNum = Number(nominal);
    if (Math.abs(total - nominalNum) < 0.01) return null;

    return total < nominalNum
        ? `Phase fee shares add up to ₹${total.toFixed(2)}, short of the event's ₹${nominalNum.toFixed(2)} school registration fee.`
        : `Phase fee shares add up to ₹${total.toFixed(2)}, more than the event's ₹${nominalNum.toFixed(2)} school registration fee.`;
});

function toggleSelectAll() {
    const filteredIds = filteredItems.value.map((item) => item.id);

    if (allSelected.value) {
        const excluded = new Set(filteredIds);
        selectedItemIds.value = selectedItemIds.value.filter((id) => !excluded.has(id));
    } else {
        selectedItemIds.value = Array.from(new Set([...selectedItemIds.value, ...filteredIds]));
    }
}

function createPhase() {
    addForm.post(`${base}/phases`, {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            showAdd.value = false;
        },
    });
}

function startEdit(phase) {
    editId.value = phase.id;
    Object.assign(editForm, {
        name: phase.name,
        code: phase.code,
        registration_open: toDatetimeLocal(phase.registration_open),
        registration_close: toDatetimeLocal(phase.registration_close),
        school_registration_fee_share: phase.school_registration_fee_share ?? null,
        registration_batch_id: phase.registration_batch_id ?? null,
        is_regional: Boolean(phase.is_regional),
    });
}

function syncTopology() {
    topologyForm.post(`${base}/phased-workflow/sync-topology`, { preserveScroll: true });
}

function createBatch() {
    addBatchForm.transform((data) => ({ ...data, code: (data.code || '').toUpperCase() })).post(`${base}/registration-batches`, {
        preserveScroll: true,
        onSuccess: () => {
            addBatchForm.reset();
            showAddBatch.value = false;
        },
    });
}

function startBatchEdit(batch) {
    editBatchId.value = batch.id;
    Object.assign(editBatchForm, {
        name: batch.name,
        code: batch.code,
        school_base_fee: Number(batch.school_base_fee ?? 0),
        student_registration_fee: batch.student_registration_fee !== null && batch.student_registration_fee !== undefined ? Number(batch.student_registration_fee) : null,
        invoice_prefix: batch.invoice_prefix ?? '',
        registration_open: toDatetimeLocal(batch.registration_open),
        registration_close: toDatetimeLocal(batch.registration_close),
    });
}

function saveBatchEdit(batch) {
    router.put(`${base}/registration-batches/${batch.id}`, { ...editBatchForm, code: (editBatchForm.code || '').toUpperCase() }, {
        preserveScroll: true,
        onSuccess: () => { editBatchId.value = null; },
    });
}

async function removeBatch(batch) {
    if (!(await confirm({ message: `Remove payment batch "${batch.name}"? Only possible while no phase, operational event, or fee is attached to it.` }))) return;
    router.delete(`${base}/registration-batches/${batch.id}`, { preserveScroll: true });
}

function startRegionEdit(phase) {
    regionEditId.value = phase.id;
    regionEditIds.value = (phase.allowed_regions || []).filter((r) => r.enabled).map((r) => r.region_id);
}

function saveRegionEdit(phase) {
    regionEditForm.region_ids = regionEditIds.value;
    regionEditForm.post(`${base}/phases/${phase.id}/regions`, {
        preserveScroll: true,
        onSuccess: () => { regionEditId.value = null; },
    });
}

// datetime-local inputs need "YYYY-MM-DDTHH:mm", not the ISO string with seconds/timezone
// that the backend sends back.
function toDatetimeLocal(value) {
    if (!value) return '';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '';
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function saveEdit(phase) {
    router.put(`${base}/phases/${phase.id}`, { ...editForm }, {
        preserveScroll: true,
        onSuccess: () => { editId.value = null; },
    });
}

async function removePhase(phase) {
    if (!(await confirm({ message: `Remove phase "${phase.name}"? Items in it become unassigned (no phase), nothing else changes.` }))) return;
    router.delete(`${base}/phases/${phase.id}`, { preserveScroll: true });
}

function assignItems() {
    assignForm.phase_id = assignPhaseId.value;
    assignForm.item_ids = selectedItemIds.value;
    assignForm.post(`${base}/phases/assign-items`, {
        preserveScroll: true,
        onSuccess: () => {
            selectedItemIds.value = [];
        },
    });
}
</script>
