<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section v-if="unknownCategories.length" class="rounded-2xl border border-amber-300 bg-amber-50 p-4">
            <h2 class="text-sm font-bold text-amber-900">{{ unknownCategories.length }} item(s) are in a category this event does not have</h2>
            <p class="mt-0.5 text-xs text-amber-800">Nothing checks their entries. Load the standard categories below, or add the missing ones.</p>
            <ul class="mt-2 space-y-0.5 text-xs text-amber-900">
                <li v-for="u in unknownCategories" :key="u.item_id">{{ u.item_code }} {{ u.title }} — <span class="font-mono">{{ u.class_group }}</span></li>
            </ul>
        </section>

        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Class categories</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        The categories items are coded against. A category's class range is what an
                        entry is checked on.
                    </p>
                </div>
                <div class="flex items-end gap-2">
                    <label class="min-w-[13rem]">
                        <span class="lbl">Standard scheme</span>
                        <select v-model="scheme" class="fld">
                            <option v-for="(label, key) in schemes" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </label>
                    <button type="button" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white" @click="seed()">Load</button>
                </div>
            </div>

            <p v-if="!categories.length" class="py-6 text-center text-sm text-slate-400">
                No categories yet — load a standard scheme above. Until then nothing checks which class may enter which item.
            </p>

            <ul v-else class="space-y-1">
                <li v-for="c in categories" :key="c.id" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 px-3 py-2">
                    <div>
                        <p class="text-sm font-medium text-slate-800">
                            {{ c.label }}
                            <span class="ml-1 font-mono text-[10px] text-slate-400">{{ c.code }}</span>
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ c.range }} · {{ c.items }} item{{ c.items === 1 ? '' : 's' }}
                            <span v-if="c.is_open" class="ml-1 text-emerald-700">· open</span>
                        </p>
                    </div>
                    <div class="flex gap-2 text-xs">
                        <button type="button" class="font-semibold text-[color:var(--brand-blue)] hover:underline" @click="edit(c)">Edit</button>
                        <button type="button" class="text-slate-400 hover:text-rose-600" @click="remove(c)">Remove</button>
                    </div>
                </li>
            </ul>

            <form v-if="editing" @submit.prevent="save" class="mt-4 grid gap-3 rounded-xl border border-[color:var(--brand-blue)]/30 p-3 sm:grid-cols-2 lg:grid-cols-5">
                <label><span class="lbl">Code</span><input v-model="form.code" class="fld" required :readonly="!!form.id"></label>
                <label class="lg:col-span-2"><span class="lbl">Label</span><input v-model="form.label" class="fld" required></label>
                <label><span class="lbl">Lowest class</span><input v-model.number="form.min_class" type="number" min="1" max="12" class="fld"></label>
                <label><span class="lbl">Highest class</span><input v-model.number="form.max_class" type="number" min="1" max="12" class="fld"></label>
                <label class="flex items-end gap-2 text-xs text-slate-600 lg:col-span-2">
                    <input v-model="form.is_open" type="checkbox"> Open — any class may enter
                </label>
                <div class="flex items-end gap-2 lg:col-span-3">
                    <button type="submit" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white">Save</button>
                    <button type="button" class="text-xs text-slate-500 underline" @click="editing = false">Cancel</button>
                </div>
            </form>
            <button v-else type="button" class="mt-3 rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700" @click="startNew()">Add category</button>
        </section>

        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Eligibility check</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Entries whose class falls outside their item's category, or whose team is the
                        wrong size. Reported, not refused — what to do about a Sahodaya that sent the
                        wrong pupil is the State's decision.
                    </p>
                </div>
                <div class="flex gap-2 text-center">
                    <div class="rounded-xl border border-slate-200 px-3 py-1.5">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Checked</p>
                        <p class="text-base font-bold text-slate-900">{{ summary.checked }}</p>
                    </div>
                    <div class="rounded-xl border px-3 py-1.5" :class="problemCount ? 'border-amber-300 bg-amber-50' : 'border-emerald-200 bg-emerald-50'">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Flagged</p>
                        <p class="text-base font-bold" :class="problemCount ? 'text-amber-700' : 'text-emerald-700'">{{ problemCount }}</p>
                    </div>
                </div>
            </div>

            <p v-if="!audit.length" class="py-6 text-center text-sm text-slate-400">Every entry passes.</p>

            <ul v-else class="space-y-1">
                <li v-for="row in audit" :key="row.registration_id" class="rounded-xl border px-3 py-2"
                    :class="row.status === 'unknown_class' ? 'border-slate-200' : 'border-amber-300 bg-amber-50/40'">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800">
                                {{ row.item_code }} {{ row.item }}
                                <span class="font-normal text-slate-500">· {{ row.sahodaya }}</span>
                            </p>
                            <p class="text-xs text-slate-500">{{ row.school }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                              :class="row.status === 'unknown_class' ? 'bg-slate-200 text-slate-600' : 'bg-amber-200 text-amber-900'">
                            {{ label(row.status) }}
                        </span>
                    </div>
                    <ul class="mt-1 space-y-0.5 text-xs" :class="row.status === 'unknown_class' ? 'text-slate-500' : 'text-amber-900'">
                        <li v-for="(p, i) in row.problems" :key="i">{{ p.message }}</li>
                    </ul>
                </li>
            </ul>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({
    event: Object, events: Array, sahodayas: Array, permissions: Array,
    categories: Array, unknownCategories: Array, summary: Object, audit: Array,
    items: Array, schemes: Object, filters: Object, actionUrls: Object, baseUrl: String,
});

const scheme = ref('kalotsav_category');
const editing = ref(false);
const form = useForm({ id: null, code: '', label: '', min_class: null, max_class: null, is_open: false, sort_order: 0 });

const problemCount = computed(() => props.audit.length);

const LABELS = {
    wrong_category: 'Wrong category',
    team_size: 'Team size',
    unknown_class: 'Class not recorded',
    no_category: 'No category',
};
function label(status) { return LABELS[status] || status; }

function seed() { router.post(props.actionUrls.seed, { scheme: scheme.value }, { preserveScroll: true }); }
function startNew() { form.reset(); form.id = null; editing.value = true; }
function edit(c) {
    Object.assign(form, { id: c.id, code: c.code, label: c.label, min_class: c.min_class, max_class: c.max_class, is_open: c.is_open });
    editing.value = true;
}
function save() { form.post(props.actionUrls.save, { preserveScroll: true, onSuccess: () => { editing.value = false; form.reset(); } }); }
function remove(c) {
    if (!confirm(`Remove ${c.label}?`)) return;
    router.delete(`${props.actionUrls.destroy}/${c.id}`, { preserveScroll: true });
}
</script>

<style scoped>
.lbl { display: block; margin-bottom: 0.25rem; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: rgb(100 116 139); }
.fld { width: 100%; border-radius: 0.75rem; border: 1px solid rgb(203 213 225); padding: 0.4rem 0.7rem; font-size: 0.875rem; }
</style>
