<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-3">
                <h2 class="text-sm font-bold text-slate-900">Grade &amp; Rank Points</h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    What a placing is worth to its Sahodaya. Leave a grade or a position blank to mean
                    "any" — the most specific matching rule wins, so "A, 1st" beats "A, any".
                </p>
            </div>

            <p class="mb-4 rounded-xl px-3 py-2 text-xs"
               :class="source.source === 'event' ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-800'">
                <strong>In force:</strong> {{ source.label }}.
                <span v-if="source.source !== 'event'">Saving any rule below makes this event's own table authoritative.</span>
            </p>

            <div class="grid gap-4 lg:grid-cols-2">
                <div v-for="group in [false, true]" :key="String(group)">
                    <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        {{ group ? 'Group / team items' : 'Individual items' }}
                    </p>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-200 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                <th class="py-2">Grade</th><th class="py-2">Position</th><th class="py-2 text-right">Points</th><th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="rule in rulesFor(group)" :key="rule._key" class="border-b border-slate-100">
                                <td class="py-1.5 pr-2">
                                    <input v-model="rule.grade" list="grade-names" class="fld" placeholder="Any grade">
                                </td>
                                <td class="py-1.5 pr-2">
                                    <input v-model.number="rule.position" type="number" min="1" class="fld" placeholder="Any">
                                </td>
                                <td class="py-1.5 pr-2">
                                    <input v-model.number="rule.points" type="number" min="0" class="fld text-right">
                                </td>
                                <td class="py-1.5 text-right">
                                    <button type="button" class="text-xs text-slate-400 hover:text-rose-600" @click="remove(rule)">×</button>
                                </td>
                            </tr>
                            <tr v-if="!rulesFor(group).length">
                                <td colspan="4" class="py-4 text-center text-xs text-slate-400">No rules yet.</td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="mt-2 rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700" @click="add(group)">
                        Add {{ group ? 'group' : 'individual' }} rule
                    </button>
                </div>
            </div>

            <datalist id="grade-names"><option v-for="g in grades" :key="g" :value="g" /></datalist>

            <p v-if="form.errors.rules" class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ form.errors.rules }}</p>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <button type="button" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white" :disabled="form.processing" @click="save()">Save rules</button>
                <span class="text-xs text-slate-400">Recompute an item's result to apply changes to marks already ranked.</span>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <h3 class="text-sm font-bold text-slate-900">Load a standard table</h3>
            <p class="mt-0.5 text-xs text-slate-500">Replaces the grade scale and these rules with the manual's numbers.</p>
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
    rules: Array, source: Object, standards: Array, grades: Array, actionUrls: Object,
});

let seq = 0;
const hydrate = (rules) => rules.map((r) => ({
    _key: ++seq,
    grade: r.grade ?? '',
    position: r.position ?? null,
    is_group: !!r.is_group,
    points: Number(r.points),
}));

const form = useForm({ rules: hydrate(props.rules) });
watch(() => props.rules, (rules) => { form.rules = hydrate(rules); });

function rulesFor(isGroup) { return form.rules.filter((r) => r.is_group === isGroup); }
function add(isGroup) { form.rules.push({ _key: ++seq, grade: '', position: null, is_group: isGroup, points: 0 }); }
function remove(rule) { form.rules.splice(form.rules.indexOf(rule), 1); }
function save() {
    form.transform((data) => ({
        rules: data.rules.map(({ grade, position, is_group, points }) => ({
            grade: grade || null, position: position || null, is_group, points,
        })),
    })).post(props.actionUrls.save, { preserveScroll: true });
}
function applyStandard(standard) {
    if (!confirm(`Replace this event's grade scale and point rules with "${standard.label}"?`)) return;
    router.post(props.actionUrls.applyStandard, { table: standard.key }, { preserveScroll: true });
}
</script>

<style scoped>
.fld { width: 100%; border-radius: 0.75rem; border: 1px solid rgb(203 213 225); padding: 0.4rem 0.7rem; font-size: 0.875rem; }
</style>
