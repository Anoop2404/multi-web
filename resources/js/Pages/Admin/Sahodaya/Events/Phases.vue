<template>
    <SahodayaEventsLayout :title="`${event.title} — Phases`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Phases`" eyebrow="Rounds & levels"
                    description="Split items into named phases (e.g. Digi Fest day, Off-stage day, On-stage day). Optional — leave every item unassigned to run the event as a single phase." />

        <EventSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="levels" />

        <div class="mb-4">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/levels`" class="link-brand text-sm">&larr; Back to Rounds & Levels</Link>
        </div>

        <div class="grid lg:grid-cols-2 gap-6 max-w-5xl">
            <div class="card space-y-4">
                <h4 class="section-title">Phases ({{ phases.length }})</h4>

                <form v-if="showAdd" @submit.prevent="createPhase" class="space-y-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3">
                    <input v-model="addForm.name" class="field text-sm" placeholder="Phase name (e.g. Off-stage Day)" required>
                    <div class="grid grid-cols-2 gap-2">
                        <input v-model="addForm.code" class="field text-sm" placeholder="Code (optional)">
                        <input v-model.number="addForm.sort_order" type="number" class="field text-sm" placeholder="Sort order">
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary text-sm" :disabled="addForm.processing">Add phase</button>
                        <button type="button" class="btn-ghost text-sm" @click="showAdd = false">Cancel</button>
                    </div>
                </form>
                <button v-else type="button" class="btn-secondary text-sm w-full" @click="showAdd = true">+ Add phase</button>

                <div v-if="phases.length === 0" class="text-sm text-slate-400">
                    No phases yet — every item runs as a single, unnamed phase.
                </div>
                <ul v-else class="divide-y divide-slate-100 text-sm">
                    <li v-for="phase in phases" :key="phase.id" class="py-2 flex items-center justify-between gap-2">
                        <template v-if="editId === phase.id">
                            <div class="flex-1 grid grid-cols-2 gap-2">
                                <input v-model="editForm.name" class="field !py-1 !text-xs">
                                <input v-model="editForm.code" class="field !py-1 !text-xs" placeholder="Code">
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <button type="button" class="text-xs font-semibold text-[#0f3d7a]" @click="saveEdit(phase)">Save</button>
                                <button type="button" class="text-xs text-slate-500" @click="editId = null">Cancel</button>
                            </div>
                        </template>
                        <template v-else>
                            <div>
                                <span class="font-semibold text-slate-700">{{ phase.name }}</span>
                                <span v-if="phase.code" class="ml-2 text-xs font-mono text-slate-400">{{ phase.code }}</span>
                                <span class="ml-2 text-xs text-slate-400">{{ itemCountForPhase(phase.id) }} item(s)</span>
                            </div>
                            <div class="flex gap-2 shrink-0">
                                <button type="button" class="text-xs font-semibold text-[#0f3d7a]" @click="startEdit(phase)">Edit</button>
                                <button type="button" class="text-xs text-red-600" @click="removePhase(phase)">Remove</button>
                            </div>
                        </template>
                    </li>
                </ul>
            </div>

            <div class="card space-y-4">
                <h4 class="section-title">Assign items to a phase</h4>
                <p class="section-desc">Select items below, pick a phase, then assign. Items left unassigned belong to no phase (fine if you don't need phase-wise conduct).</p>

                <div v-if="items.length === 0" class="text-sm text-slate-400">No items on this event yet.</div>
                <template v-else>
                    <div class="flex items-center gap-2">
                        <select v-model="assignPhaseId" class="field text-sm flex-1">
                            <option :value="null">— No phase (unassign) —</option>
                            <option v-for="phase in phases" :key="phase.id" :value="phase.id">{{ phase.name }}</option>
                        </select>
                        <button type="button" class="btn-primary text-sm shrink-0" :disabled="selectedItemIds.length === 0 || assignForm.processing" @click="assignItems">
                            Assign ({{ selectedItemIds.length }})
                        </button>
                    </div>

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
                                <tr v-for="item in items" :key="item.id" class="bg-white">
                                    <td class="p-2"><input type="checkbox" :value="item.id" v-model="selectedItemIds"></td>
                                    <td class="p-2">
                                        {{ item.title }}
                                        <span v-if="item.item_code" class="ml-1 text-xs font-mono text-slate-400">{{ item.item_code }}</span>
                                    </td>
                                    <td class="p-2 text-slate-600">{{ item.phase_name || '—' }}</td>
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
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    phases: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;

const showAdd = ref(false);
const editId = ref(null);
const selectedItemIds = ref([]);
const assignPhaseId = ref(null);

const addForm = useForm({ name: '', code: '', sort_order: null, is_default: false });
const editForm = reactive({ name: '', code: '' });
const assignForm = useForm({ phase_id: null, item_ids: [] });

const allSelected = computed(() => props.items.length > 0 && selectedItemIds.value.length === props.items.length);

function itemCountForPhase(phaseId) {
    return props.items.filter((i) => i.phase_id === phaseId).length;
}

function toggleSelectAll() {
    selectedItemIds.value = allSelected.value ? [] : props.items.map((i) => i.id);
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
    Object.assign(editForm, { name: phase.name, code: phase.code });
}

function saveEdit(phase) {
    router.put(`${base}/phases/${phase.id}`, { ...editForm }, {
        preserveScroll: true,
        onSuccess: () => { editId.value = null; },
    });
}

function removePhase(phase) {
    if (!confirm(`Remove phase "${phase.name}"? Items in it become unassigned (no phase), nothing else changes.`)) return;
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
