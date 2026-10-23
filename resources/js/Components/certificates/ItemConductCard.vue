<template>
    <details class="card mb-6 !p-4">
        <summary class="cursor-pointer font-semibold">Certificate phases — venue and dates</summary>
        <p class="my-3 text-sm text-gray-600">Create a certificate phase, set its venue and dates, then select its items. All phases use the same template. Unassigned items use event details.</p>
        <div class="flex flex-wrap gap-2 mb-4">
            <button type="button" class="btn-secondary" @click="newPhase">+ New certificate phase</button>
            <button v-for="phase in phases" :key="phase.id" type="button" class="btn-secondary" :class="{ '!bg-indigo-100': form.phase_id === phase.id }" @click="editPhase(phase)">{{ phase.name }} · {{ phaseCount(phase.id) }} items</button>
        </div>
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="block text-sm mb-2">Item order
                    <select v-model="sort" class="field w-full">
                        <option value="published_asc">Results published — oldest first</option>
                        <option value="published_desc">Results published — newest first</option>
                        <option value="name">Item name</option>
                        <option value="code">Item code</option>
                    </select>
                </label>
                <input v-model="search" class="field w-full mb-2" placeholder="Search item name, code, category or gender" aria-label="Search items" />
                <div class="max-h-60 overflow-auto border rounded-lg p-2">
                    <label v-for="item in filtered" :key="item.id" class="flex gap-2 p-2 text-sm">
                        <input v-model="form.item_ids" type="checkbox" :value="item.id" />
                        <span>{{ item.item_code }} · {{ item.title }} · {{ item.class_group }} · {{ item.gender }}
                            <span class="block text-xs text-indigo-700">{{ phaseName(item.certificate_phase_id) }} · {{ publishedLabel(item.results_published_at) }}</span>
                        </span>
                    </label>
                </div>
                <p class="text-xs mt-2">{{ form.item_ids.length }} selected. Selecting an item from another phase moves it into this phase when saved.</p>
            </div>
            <form class="space-y-3" @submit.prevent="save">
                <label class="block text-sm">Phase name<input v-model="form.name" class="field w-full" required placeholder="e.g. Phase 1 — Off-stage" /></label>
                <label class="block text-sm">Venue / location<input v-model="form.venue" class="field w-full" required placeholder="School / venue name" /></label>
                <div class="grid grid-cols-2 gap-3">
                    <label class="text-sm">Conducted from<input v-model="form.date_start" type="date" required class="field w-full" /></label>
                    <label class="text-sm">Until (optional)<input v-model="form.date_end" type="date" :min="form.date_start || undefined" class="field w-full" /></label>
                </div>
                <p v-for="(error, key) in form.errors" :key="key" class="text-sm text-red-600">{{ error }}</p>
                <div class="flex gap-3">
                    <button class="btn-primary" :disabled="form.processing">Save phase and assigned items</button>
                    <button v-if="form.phase_id" type="button" class="btn-secondary !text-red-700" :disabled="form.processing || removing" @click="removePhase">Remove phase</button>
                </div>
                <p class="text-xs text-gray-500">Uncheck an item and save to restore its event defaults. Existing affected merit certificates need regeneration. The student's school and certificate issue date stay unchanged.</p>
            </form>
        </div>
    </details>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';
const props = defineProps({ base: String, items: { type: Array, default: () => [] }, phases: { type: Array, default: () => [] } });
const search = ref('');
const removing = ref(false);
const { confirm } = useConfirm();
const sort = ref('published_asc');
const form = useForm({ phase_id: null, name: '', item_ids: [], venue: '', date_start: '', date_end: '' });
const filtered = computed(() => props.items.filter(item => `${item.item_code} ${item.title} ${item.class_group} ${item.gender}`.toLowerCase().includes(search.value.toLowerCase())).sort((a, b) => {
    if (sort.value === 'name') return a.title.localeCompare(b.title) || a.id - b.id;
    if (sort.value === 'code') return String(a.item_code || '').localeCompare(String(b.item_code || ''), undefined, { numeric: true }) || a.id - b.id;
    const first = a.results_published_at ? new Date(a.results_published_at).getTime() : null;
    const second = b.results_published_at ? new Date(b.results_published_at).getTime() : null;
    if (first === null || second === null) return first === second ? a.id - b.id : first === null ? 1 : -1;
    return (sort.value === 'published_desc' ? second - first : first - second) || a.id - b.id;
}));
const publishedLabel = value => value ? `Published ${new Date(value).toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' })}` : 'Results not published';
const phaseName = id => props.phases.find(phase => phase.id === id)?.name || 'Event defaults';
const phaseCount = id => props.items.filter(item => item.certificate_phase_id === id).length;
function newPhase() {
    form.reset();
    form.clearErrors();
}
function editPhase(phase) {
    newPhase();
    Object.assign(form, {
        phase_id: phase.id, name: phase.name, venue: phase.venue,
        date_start: phase.date_start?.slice(0, 10) || '', date_end: phase.date_end?.slice(0, 10) || '',
        item_ids: props.items.filter(item => item.certificate_phase_id === phase.id).map(item => item.id),
    });
}
function save() {
    form.post(`${props.base}/phases`, { preserveScroll: true, onSuccess: () => {
        if (!form.phase_id) newPhase();
    } });
}
async function removePhase() {
    const phase = props.phases.find(value => value.id === form.phase_id);
    if (!phase || removing.value) return;
    const count = phaseCount(phase.id);
    if (!(await confirm({
        title: 'Remove certificate phase?',
        message: `Remove "${phase.name}"? ${count} assigned item(s) will use the event's default date and venue. Items and certificates are kept; affected merit certificates need regeneration.`,
        confirmLabel: 'Remove phase', destructive: true,
    }))) return;
    removing.value = true;
    router.delete(`${props.base}/phases/${phase.id}`, {
        preserveScroll: true,
        onSuccess: newPhase,
        onFinish: () => { removing.value = false; },
    });
}
</script>
