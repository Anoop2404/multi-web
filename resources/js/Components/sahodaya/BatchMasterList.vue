<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center gap-2">
            <input v-if="searchable" :value="modelSearch" @input="$emit('update:search', $event.target.value)"
                   type="search" class="field text-sm flex-1 min-w-[200px]" placeholder="Search by team/participant name or school…">
            <button v-if="createUrl" type="button" class="btn-secondary text-sm shrink-0" @click="showAdd = true">+ Add batch (e.g. Batch {{ batches.length + 1 }})</button>
        </div>

        <form v-if="showAdd" @submit.prevent="createBatch" class="space-y-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3">
            <input v-model="addForm.label" class="field text-sm" placeholder="Batch name (e.g. Batch 1)" required>
            <div class="grid sm:grid-cols-2 gap-2">
                <label class="text-xs text-slate-500 block">
                    Report time
                    <input v-model="addForm.report_at" type="datetime-local" class="field text-sm mt-0.5">
                </label>
                <label class="text-xs text-slate-500 block">
                    Sort order (lower reports first)
                    <input v-model.number="addForm.sort_order" type="number" class="field text-sm mt-0.5" placeholder="Auto if left blank">
                </label>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary text-sm" :disabled="addForm.processing">Add batch</button>
                <button type="button" class="btn-ghost text-sm" @click="showAdd = false">Cancel</button>
            </div>
        </form>

        <div v-if="selectable" class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50/70 p-2">
            <span class="text-xs text-slate-500 pl-1">{{ modelValue.length }} selected</span>
            <select v-model="bulkBatchId" class="field !py-1 !text-xs flex-1">
                <option value="">— No batch (unassign) —</option>
                <option v-for="b in batches" :key="b.id" :value="b.id">{{ b.label }}</option>
            </select>
            <button type="button" class="btn-primary text-sm shrink-0" :disabled="modelValue.length === 0 || bulkMoving" @click="bulkAssign">
                Assign ({{ modelValue.length }})
            </button>
        </div>

        <div v-if="registrations.length === 0" class="text-sm text-slate-400">No registrations for this item yet.</div>
        <div v-else-if="groups.every((g) => g.rows.length === 0)" class="text-sm text-slate-400">No registrations match your search.</div>

        <div v-for="group in groups" :key="group.key" v-show="group.rows.length" class="space-y-2">
            <div class="flex items-center justify-between gap-2 border-b border-slate-200 pb-1.5">
                <div v-if="editingBatchId === group.batch?.id" class="flex-1 space-y-2 py-1">
                    <div class="grid sm:grid-cols-3 gap-2">
                        <input v-model="editForm.label" class="field !py-1 !text-xs">
                        <input v-model="editForm.report_at" type="datetime-local" class="field !py-1 !text-xs">
                        <input v-model.number="editForm.sort_order" type="number" class="field !py-1 !text-xs" placeholder="Sort order">
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="text-xs font-semibold text-[#0f3d7a]" @click="saveEdit(group.batch)">Save</button>
                        <button type="button" class="text-xs text-slate-500" @click="editingBatchId = null">Cancel</button>
                    </div>
                </div>
                <template v-else>
                    <div>
                        <h5 class="font-semibold text-slate-800">{{ group.batch ? group.batch.label : 'Unassigned' }}</h5>
                        <p v-if="group.batch?.report_at" class="text-xs text-slate-500">Reports: {{ formatDateTime(group.batch.report_at) }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <span class="text-xs text-slate-400">{{ group.rows.length }} registration(s)</span>
                        <template v-if="group.batch && batchBaseUrl">
                            <button type="button" class="text-xs font-semibold text-[#0f3d7a]" @click="startEdit(group.batch)">Edit</button>
                            <button type="button" class="text-xs text-red-600" @click="removeBatch(group.batch)">Remove</button>
                        </template>
                    </div>
                </template>
            </div>

            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th v-if="selectable" class="py-1 pr-2 w-8">
                            <input type="checkbox" :checked="allSelectedIn(group)" @change="toggleGroup(group)">
                        </th>
                        <th class="py-1 pr-2 w-8">Sl</th>
                        <th class="py-1 pr-2">{{ selectedItem?.is_group ? 'Team / Participant' : 'Name' }}</th>
                        <th class="py-1 pr-2">School</th>
                        <th class="py-1 pr-2 w-48">Move to batch</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="(row, index) in group.rows" :key="row.id">
                        <td v-if="selectable" class="py-1.5 pr-2 align-top">
                            <input type="checkbox" :value="row.id" :checked="modelValue.includes(row.id)" @change="toggleRow(row.id)">
                        </td>
                        <td class="py-1.5 pr-2 align-top text-slate-500">{{ index + 1 }}</td>
                        <td class="py-1.5 pr-2 align-top">
                            <div>{{ row.name }}<span v-if="row.is_team" class="ml-1 text-xs text-slate-400">({{ row.member_count }})</span></div>
                            <div v-if="row.is_team && row.first_participant_name" class="text-xs text-slate-400">{{ row.first_participant_name }}</div>
                        </td>
                        <td class="py-1.5 pr-2 align-top text-slate-600">
                            {{ row.school }}
                            <span v-if="row.distance_km !== null && row.distance_km !== undefined" class="ml-1 text-xs text-slate-400">({{ row.distance_km }} km)</span>
                        </td>
                        <td class="py-1.5 pr-2 align-top">
                            <select class="field !py-1 !text-xs w-full" :value="row.reporting_batch_id ?? ''"
                                    :disabled="moving.has(row.id)" @change="moveRow(row, $event.target.value)">
                                <option value="">— Unassigned —</option>
                                <option v-for="b in batches" :key="b.id" :value="b.id">{{ b.label }}</option>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    selectedItem: { type: Object, default: null },
    batches: { type: Array, default: () => [] },
    registrations: { type: Array, default: () => [] },
    assignUrl: { type: String, required: true },
    createUrl: { type: String, default: null },
    batchBaseUrl: { type: String, default: null },
    selectable: { type: Boolean, default: false },
    modelValue: { type: Array, default: () => [] },
    searchable: { type: Boolean, default: false },
    search: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'update:search']);
const { confirm } = useConfirm();

const moving = reactive(new Set());
const showAdd = ref(false);
const addForm = useForm({ item_id: props.selectedItem?.id ?? null, label: '', report_at: '', sort_order: null });
const modelSearch = computed(() => props.search);

const editingBatchId = ref(null);
const editForm = reactive({ label: '', report_at: '', sort_order: null });

const bulkBatchId = ref('');
const bulkMoving = ref(false);

const filteredRegistrations = computed(() => {
    const q = props.search.trim().toLowerCase();
    if (!q) return props.registrations;

    return props.registrations.filter((row) =>
        row.name?.toLowerCase().includes(q) || row.school?.toLowerCase().includes(q)
    );
});

const groups = computed(() => {
    const byBatch = new Map(props.batches.map((b) => [b.id, { key: `batch-${b.id}`, batch: b, rows: [] }]));
    const unassigned = { key: 'unassigned', batch: null, rows: [] };

    for (const row of filteredRegistrations.value) {
        const target = row.reporting_batch_id && byBatch.has(row.reporting_batch_id)
            ? byBatch.get(row.reporting_batch_id)
            : unassigned;
        target.rows.push(row);
    }

    const list = Array.from(byBatch.values());
    list.push(unassigned);
    return list;
});

function allSelectedIn(group) {
    return group.rows.length > 0 && group.rows.every((row) => props.modelValue.includes(row.id));
}

function toggleGroup(group) {
    const ids = group.rows.map((row) => row.id);
    if (allSelectedIn(group)) {
        emit('update:modelValue', props.modelValue.filter((id) => !ids.includes(id)));
    } else {
        emit('update:modelValue', Array.from(new Set([...props.modelValue, ...ids])));
    }
}

function toggleRow(id) {
    if (props.modelValue.includes(id)) {
        emit('update:modelValue', props.modelValue.filter((v) => v !== id));
    } else {
        emit('update:modelValue', [...props.modelValue, id]);
    }
}

function formatDateTime(value) {
    if (!value) return '—';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return '—';
    return d.toLocaleString(undefined, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

function moveRow(row, value) {
    if (!props.selectedItem) return;

    moving.add(row.id);
    router.post(props.assignUrl, {
        item_id: props.selectedItem.id,
        batch_id: value || null,
        registration_ids: [row.id],
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => moving.delete(row.id),
    });
}

function bulkAssign() {
    if (!props.selectedItem || props.modelValue.length === 0) return;

    bulkMoving.value = true;
    router.post(props.assignUrl, {
        item_id: props.selectedItem.id,
        batch_id: bulkBatchId.value || null,
        registration_ids: props.modelValue,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => emit('update:modelValue', []),
        onFinish: () => { bulkMoving.value = false; },
    });
}

function createBatch() {
    if (!props.selectedItem || !props.createUrl) return;

    addForm.item_id = props.selectedItem.id;
    addForm.post(props.createUrl, {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset('label', 'report_at', 'sort_order');
            showAdd.value = false;
        },
    });
}

function startEdit(batch) {
    editingBatchId.value = batch.id;
    Object.assign(editForm, {
        label: batch.label,
        report_at: batch.report_at ? batch.report_at.slice(0, 16) : '',
        sort_order: batch.sort_order,
    });
}

function saveEdit(batch) {
    if (!props.batchBaseUrl) return;

    router.put(`${props.batchBaseUrl}/${batch.id}`, { ...editForm }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { editingBatchId.value = null; },
    });
}

async function removeBatch(batch) {
    if (!props.batchBaseUrl) return;
    if (!(await confirm({ message: `Remove batch "${batch.label}"? Its registrations become unassigned, nothing else changes.` }))) return;

    router.delete(`${props.batchBaseUrl}/${batch.id}`, { preserveScroll: true });
}
</script>
