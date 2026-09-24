<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Grade Master</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        What score earns what grade. Grades decide points, so this is the scale every
                        result is built on.
                    </p>
                </div>
                <label class="min-w-[14rem]">
                    <span class="lbl">Scale for</span>
                    <select :value="filters.item_id || ''" class="fld" @change="pickItem($event.target.value)">
                        <option value="">Every item (event-wide)</option>
                        <option v-for="i in items" :key="i.id" :value="i.id">{{ i.item_code }} — {{ i.title }}</option>
                    </select>
                </label>
            </div>

            <!-- Which tier is actually in force. Without this an operator cannot tell a scale they
                 set from one inherited from the event or from the manual. -->
            <p class="mb-4 rounded-xl px-3 py-2 text-xs"
               :class="source.source === 'event' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'">
                <strong>In force:</strong> {{ source.label }}.
                <span v-if="filters.item_id && !isItemOverride">
                    This item has no scale of its own, so it uses the event-wide one shown below. Saving here creates an override for this item only.
                </span>
            </p>

            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        <th class="py-2">Grade</th><th class="py-2">From</th><th class="py-2">To</th><th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(band, i) in form.bands" :key="i" class="border-b border-slate-100">
                        <td class="py-1.5 pr-2"><input v-model="band.grade" class="fld" placeholder="A"></td>
                        <td class="py-1.5 pr-2"><input v-model.number="band.min_score" type="number" step="0.01" class="fld"></td>
                        <td class="py-1.5 pr-2"><input v-model.number="band.max_score" type="number" step="0.01" class="fld"></td>
                        <td class="py-1.5 text-right">
                            <button type="button" class="text-xs text-slate-400 hover:text-rose-600" @click="form.bands.splice(i, 1)">Remove</button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <p v-if="form.errors.bands" class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ form.errors.bands }}</p>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <button type="button" class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700" @click="addBand()">Add band</button>
                <button type="button" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white" :disabled="form.processing" @click="save()">Save scale</button>
                <span class="text-xs text-slate-400">Bands must not overlap or leave a gap.</span>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <h3 class="text-sm font-bold text-slate-900">Load a standard table</h3>
            <p class="mt-0.5 text-xs text-slate-500">
                Replaces both the grade scale and the point rules with the manual's own numbers. The
                same tables the Sahodaya side loads — the manual applies at every level.
            </p>
            <ul class="mt-3 space-y-2">
                <li v-for="s in standards" :key="s.key" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 px-3 py-2">
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-slate-800">{{ s.label }}</p>
                        <p class="text-xs text-slate-500">{{ s.description }}</p>
                    </div>
                    <button type="button" class="rounded-lg border border-[color:var(--brand-blue)]/40 px-3 py-1.5 text-xs font-bold text-[color:var(--brand-blue)]" @click="applyStandard(s)">Load</button>
                </li>
            </ul>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { ref, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({
    event: Object, events: Array, sahodayas: Array, permissions: Array,
    bands: Array, filters: Object, isItemOverride: Boolean, source: Object,
    standards: Array, items: Array, actionUrls: Object, baseUrl: String,
});

const form = useForm({
    item_id: props.filters.item_id || null,
    bands: props.bands.map((b) => ({ grade: b.grade, min_score: Number(b.min_score), max_score: Number(b.max_score) })),
});

watch(() => props.bands, (bands) => {
    form.bands = bands.map((b) => ({ grade: b.grade, min_score: Number(b.min_score), max_score: Number(b.max_score) }));
    form.item_id = props.filters.item_id || null;
});

function addBand() {
    const lowest = form.bands.length ? Math.min(...form.bands.map((b) => Number(b.min_score))) : 101;
    form.bands.push({ grade: '', min_score: Math.max(0, lowest - 10), max_score: Math.max(0, lowest - 1) });
}
function save() { form.post(props.actionUrls.save, { preserveScroll: true }); }
function pickItem(id) { router.get(props.baseUrl, id ? { item_id: id } : {}, { preserveState: false }); }
function applyStandard(standard) {
    if (!confirm(`Replace this event's grade scale and point rules with "${standard.label}"?`)) return;
    router.post(props.actionUrls.applyStandard, { table: standard.key }, { preserveScroll: true });
}
</script>

<style scoped>
.lbl { display: block; margin-bottom: 0.25rem; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: rgb(100 116 139); }
.fld { width: 100%; border-radius: 0.75rem; border: 1px solid rgb(203 213 225); padding: 0.4rem 0.7rem; font-size: 0.875rem; }
</style>
