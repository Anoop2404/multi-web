<template>
    <Modal :show="show" title="Batch master" @close="$emit('close')">
        <div class="space-y-4">
            <p class="text-xs text-slate-500">
                Batches are shared across every item in this event — create them once here, then assign
                registrations to them from any item's page.
            </p>

            <form v-if="showAdd" @submit.prevent="createBatch" class="space-y-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                <input v-model="addForm.label" class="field text-sm" placeholder="Batch name (e.g. Batch 1)" required>
                <label class="text-xs text-slate-500 block">
                    Sort order (lower reports first)
                    <input v-model.number="addForm.sort_order" type="number" class="field text-sm mt-0.5" placeholder="Auto if left blank">
                </label>
                <div class="flex gap-2">
                    <button type="submit" class="btn-primary text-sm" :disabled="addForm.processing">Add batch</button>
                    <button type="button" class="btn-ghost text-sm" @click="showAdd = false">Cancel</button>
                </div>
            </form>
            <button v-else type="button" class="btn-secondary text-sm" @click="showAdd = true">+ Add batch (e.g. Batch {{ batches.length + 1 }})</button>

            <div v-if="batches.length === 0" class="text-sm text-slate-400">No batches yet.</div>
            <div v-else class="divide-y divide-slate-100 rounded-xl border border-slate-200 max-h-80 overflow-y-auto">
                <div v-for="(batch, index) in sortedBatches" :key="batch.id" class="p-3">
                    <div v-if="editingId === batch.id" class="space-y-2">
                        <div class="grid sm:grid-cols-2 gap-2">
                            <input v-model="editForm.label" class="field !py-1 !text-xs" placeholder="Batch name">
                            <input v-model.number="editForm.sort_order" type="number" class="field !py-1 !text-xs" placeholder="Sort order">
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="btn-primary !py-1 !px-2.5 text-xs" @click="saveEdit(batch)">Save</button>
                            <button type="button" class="btn-ghost !py-1 !px-2.5 text-xs" @click="editingId = null">Cancel</button>
                        </div>
                    </div>
                    <div v-else class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#0f3d7a]/10 text-xs font-bold text-[#0f3d7a]">
                                {{ index + 1 }}
                            </span>
                            <div>
                                <div class="font-semibold text-slate-800 text-sm">{{ batch.label }}</div>
                                <div class="text-xs text-slate-400">Sort order {{ batch.sort_order }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <button type="button" class="text-xs font-semibold text-[#0f3d7a] hover:underline" @click="startEdit(batch)">Edit</button>
                            <button type="button" class="text-xs font-semibold text-red-600 hover:underline" @click="removeBatch(batch)">Remove</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <template #footer>
            <button type="button" class="btn-ghost text-sm" @click="$emit('close')">Close</button>
        </template>
    </Modal>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';
import Modal from '@/Components/ui/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    batches: { type: Array, default: () => [] },
    createUrl: { type: String, required: true },
    batchBaseUrl: { type: String, required: true },
});

defineEmits(['close']);
const { confirm } = useConfirm();

const showAdd = ref(false);
const addForm = useForm({ label: '', sort_order: null });

const editingId = ref(null);
const editForm = reactive({ label: '', sort_order: null });

const sortedBatches = computed(() => [...props.batches].sort((a, b) => (a.sort_order ?? 0) - (b.sort_order ?? 0)));

function createBatch() {
    addForm.post(props.createUrl, {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset('label', 'sort_order');
            showAdd.value = false;
        },
    });
}

function startEdit(batch) {
    editingId.value = batch.id;
    Object.assign(editForm, {
        label: batch.label,
        sort_order: batch.sort_order,
    });
}

function saveEdit(batch) {
    router.put(`${props.batchBaseUrl}/${batch.id}`, { ...editForm }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { editingId.value = null; },
    });
}

async function removeBatch(batch) {
    if (!(await confirm({ message: `Remove batch "${batch.label}"? Its registrations become unassigned, nothing else changes.` }))) return;

    router.delete(`${props.batchBaseUrl}/${batch.id}`, { preserveScroll: true });
}
</script>
