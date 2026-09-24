<template>
    <SahodayaEventsLayout :title="`${event.title} — Reporting Batches`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Reporting Batches`" eyebrow="Schedule"
                    :description="`Pick an item with more than ${minRegistrations} registrations, then split its teams/participants into ordered batches so they don't all report at once — Batch 1 reports/performs first, then Batch 2, and so on.`" />

        <EventSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="reporting-batches" class="mb-4" />

        <div v-if="items.length === 0" class="card text-sm text-slate-400">
            No items have more than {{ minRegistrations }} registrations yet — batching isn't needed until an item gets that large.
        </div>

        <div v-else class="grid lg:grid-cols-[280px_1fr] gap-6 items-start">
            <div class="card !p-2 space-y-1 lg:sticky lg:top-4">
                <h4 class="section-title !mb-1 px-2 pt-1">Items ({{ items.length }})</h4>
                <p class="text-xs text-slate-400 px-2 pb-1">Sorted alphabetically.</p>
                <button v-for="item in items" :key="item.id" type="button"
                        class="w-full text-left rounded-lg px-3 py-2 text-sm transition"
                        :class="selectedItem?.id === item.id ? 'bg-[#0f3d7a] text-white' : 'hover:bg-slate-50 text-slate-700'"
                        @click="jumpToItem(item.id)">
                    <div class="font-semibold truncate">{{ item.title }}</div>
                    <div class="text-xs" :class="selectedItem?.id === item.id ? 'text-white/70' : 'text-slate-400'">
                        {{ item.item_code ? `${item.item_code} · ` : '' }}{{ item.registration_count }} reg(s)
                    </div>
                </button>
            </div>

            <div v-if="!selectedItem" class="card text-sm text-slate-400">
                Pick an item on the left to view or assign its reporting batches.
            </div>

            <div v-else class="space-y-6">
                <div class="card !py-3 flex flex-wrap items-center gap-2">
                    <button type="button" class="btn-secondary text-sm" @click="showBatchMaster = true">📋 View batch master</button>
                    <Link :href="`${base}/batch-master?item_id=${selectedItem.id}`" class="btn-secondary text-sm" target="_blank">Open full page ↗</Link>
                    <a :href="`${base}/print?item_id=${selectedItem.id}&preview=1`" class="btn-secondary text-sm" target="_blank">👁 Preview PDF</a>
                    <a :href="`${base}/print?item_id=${selectedItem.id}`" class="btn-secondary text-sm">⬇ Download PDF</a>
                </div>

                <div class="grid xl:grid-cols-2 gap-6">
                    <div class="card space-y-4">
                        <h4 class="section-title">Batches for {{ selectedItem.title }} ({{ batches.length }})</h4>
                        <p class="section-desc">Listed in report order — Batch 1 reports/performs first.</p>

                        <form v-if="showAdd" @submit.prevent="createBatch" class="space-y-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                            <input v-model="addForm.label" class="field text-sm" placeholder="Batch name (e.g. Batch 1)" required>
                            <label class="text-xs text-slate-500 block">
                                Report time
                                <input v-model="addForm.report_at" type="datetime-local" class="field text-sm mt-0.5">
                            </label>
                            <label class="text-xs text-slate-500 block">
                                Sort order (lower reports first)
                                <input v-model.number="addForm.sort_order" type="number" class="field text-sm mt-0.5" placeholder="Auto if left blank">
                            </label>
                            <div class="flex gap-2">
                                <button type="submit" class="btn-primary text-sm" :disabled="addForm.processing">Add batch</button>
                                <button type="button" class="btn-ghost text-sm" @click="showAdd = false">Cancel</button>
                            </div>
                        </form>
                        <button v-else type="button" class="btn-secondary text-sm w-full" @click="showAdd = true">+ Add batch</button>

                        <div v-if="batches.length === 0" class="text-sm text-slate-400">
                            No batches yet — every registration is unassigned (reports together).
                        </div>
                        <ul v-else class="divide-y divide-slate-100 text-sm">
                            <li v-for="batch in batches" :key="batch.id" class="py-2">
                                <div v-if="editId === batch.id" class="flex items-center justify-between gap-2">
                                    <div class="flex-1 space-y-2">
                                        <input v-model="editForm.label" class="field !py-1 !text-xs">
                                        <label class="text-[11px] text-slate-500 block">
                                            Report time
                                            <input v-model="editForm.report_at" type="datetime-local" class="field !py-1 !text-xs mt-0.5">
                                        </label>
                                        <label class="text-[11px] text-slate-500 block">
                                            Sort order
                                            <input v-model.number="editForm.sort_order" type="number" class="field !py-1 !text-xs mt-0.5">
                                        </label>
                                    </div>
                                    <div class="flex gap-2 shrink-0">
                                        <button type="button" class="text-xs font-semibold text-[#0f3d7a]" @click="saveEdit(batch)">Save</button>
                                        <button type="button" class="text-xs text-slate-500" @click="editId = null">Cancel</button>
                                    </div>
                                </div>
                                <div v-else class="flex items-center justify-between gap-2">
                                    <div>
                                        <span class="font-semibold text-slate-700">{{ batch.label }}</span>
                                        <span class="ml-2 text-xs text-slate-400">{{ batch.registrations_count }} registration(s)</span>
                                        <div class="text-xs text-slate-400 mt-0.5">
                                            <span v-if="batch.report_at">Reports: {{ formatDateTime(batch.report_at) }}</span>
                                            <span class="ml-2">Order: {{ batch.sort_order }}</span>
                                        </div>
                                    </div>
                                    <div class="flex gap-2 shrink-0">
                                        <button type="button" class="text-xs font-semibold text-[#0f3d7a]" @click="startEdit(batch)">Edit</button>
                                        <button type="button" class="text-xs text-red-600" @click="removeBatch(batch)">Remove</button>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <div class="card space-y-4">
                        <h4 class="section-title">Assign registrations to a batch</h4>
                        <p class="section-desc">Grouped by batch, just like Batch Master. Move one at a time with the dropdown, or select several and bulk-assign below.</p>

                        <div v-if="registrations.length" class="flex items-center gap-2">
                            <SearchableSelect v-model="assignBatchId" class="flex-1" :options="batchOptions" :all-option="true" all-label="— No batch (unassign) —" />
                            <button type="button" class="btn-primary text-sm shrink-0" :disabled="selectedRegIds.length === 0 || assignForm.processing" @click="assignRegistrations">
                                Assign ({{ selectedRegIds.length }})
                            </button>
                        </div>

                        <BatchMasterList :selected-item="selectedItem" :batches="batches" :registrations="registrations"
                                          :assign-url="`${base}/assign`" :create-url="base"
                                          selectable v-model="selectedRegIds" searchable v-model:search="regSearch" />
                    </div>
                </div>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />

        <Modal :show="showBatchMaster" title="Batch master" :subtitle="selectedItem?.title" size="xl" @close="showBatchMaster = false">
            <BatchMasterList v-if="selectedItem" :selected-item="selectedItem" :batches="batches" :registrations="registrations"
                              :assign-url="`${base}/assign`" :create-url="base" searchable v-model:search="regSearch" />
            <template #footer>
                <a :href="selectedItem ? `${base}/print?item_id=${selectedItem.id}&preview=1` : '#'" class="btn-secondary text-sm" target="_blank">👁 Preview PDF</a>
                <a :href="selectedItem ? `${base}/print?item_id=${selectedItem.id}` : '#'" class="btn-secondary text-sm">⬇ Download PDF</a>
                <button type="button" class="btn-primary text-sm" @click="showBatchMaster = false">Close</button>
            </template>
        </Modal>
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import Modal from '@/Components/ui/Modal.vue';
import BatchMasterList from '@/Components/sahodaya/BatchMasterList.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    items: { type: Array, default: () => [] },
    selectedItem: { type: Object, default: null },
    selectedItemId: { type: [String, Number], default: null },
    batches: { type: Array, default: () => [] },
    registrations: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    minRegistrations: { type: Number, default: 15 },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reporting-batches`;
const { confirm } = useConfirm();
const minRegistrations = props.minRegistrations;

const itemOptions = computed(() => props.items.map((item) => ({
    value: String(item.id),
    label: `${item.item_code ? `${item.title} (${item.item_code})` : item.title} — ${item.registration_count} reg(s)`,
})));

function jumpToItem(itemId) {
    if (!itemId) {
        router.get(base, {}, { preserveState: true });
        return;
    }
    router.get(base, { item_id: itemId }, { preserveState: true });
}

const batchOptions = computed(() => props.batches.map((b) => ({ value: b.id, label: b.label })));

const showAdd = ref(false);
const showBatchMaster = ref(false);
const editId = ref(null);
const selectedRegIds = ref([]);
const assignBatchId = ref(null);
const regSearch = ref('');

const addForm = useForm({ item_id: props.selectedItem?.id ?? null, label: '', report_at: '', sort_order: null });
const editForm = reactive({ label: '', report_at: '', sort_order: null });
const assignForm = useForm({ item_id: props.selectedItem?.id ?? null, batch_id: null, registration_ids: [] });

function formatDateTime(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleString(undefined, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

function createBatch() {
    addForm.item_id = props.selectedItem.id;
    addForm.post(base, {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset('label', 'report_at', 'sort_order');
            showAdd.value = false;
        },
    });
}

function startEdit(batch) {
    editId.value = batch.id;
    Object.assign(editForm, {
        label: batch.label,
        report_at: batch.report_at ? batch.report_at.slice(0, 16) : '',
        sort_order: batch.sort_order,
    });
}

function saveEdit(batch) {
    router.put(`${base}/${batch.id}`, { ...editForm }, {
        preserveScroll: true,
        onSuccess: () => { editId.value = null; },
    });
}

async function removeBatch(batch) {
    if (!(await confirm({ message: `Remove batch "${batch.label}"? Its registrations become unassigned, nothing else changes.` }))) return;
    router.delete(`${base}/${batch.id}`, { preserveScroll: true });
}

function assignRegistrations() {
    assignForm.item_id = props.selectedItem.id;
    assignForm.batch_id = assignBatchId.value;
    assignForm.registration_ids = selectedRegIds.value;
    assignForm.post(`${base}/assign`, {
        preserveScroll: true,
        onSuccess: () => {
            selectedRegIds.value = [];
        },
    });
}
</script>
