<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Prize categories</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        A trophy is a named group of items. Assign the items, choose which titles it
                        awards, and the champions are worked out from published results. An "overall"
                        category covers every item, so it never goes stale as items are added.
                    </p>
                </div>
                <button type="button" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white" @click="startNew()">Add category</button>
            </div>

            <p v-if="!categories.length" class="py-8 text-center text-sm text-slate-400">
                No prize categories yet. Add one — for example "Dance Champion" over the dance items.
            </p>

            <ul v-else class="space-y-1">
                <li v-for="c in categories" :key="c.id" class="rounded-xl border border-slate-200 px-3 py-2"
                    :class="{ 'opacity-60': !c.is_active }">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800">
                                {{ c.name }}
                                <span v-if="c.is_overall" class="ml-1 rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-indigo-700">Overall</span>
                                <span v-if="!c.is_active" class="ml-1 text-[10px] uppercase text-slate-400">inactive</span>
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ c.award_labels.join(' · ') }}
                                · top {{ c.honour_count }}
                                · <span v-if="c.is_overall">every item</span>
                                  <span v-else>{{ c.item_count }} item{{ c.item_count === 1 ? '' : 's' }}</span>
                            </p>
                            <p v-if="c.description" class="text-xs text-slate-400">{{ c.description }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2 text-xs">
                            <button v-if="!c.is_overall" type="button" class="font-semibold text-[color:var(--brand-blue)] hover:underline" @click="openItems(c)">Items</button>
                            <button type="button" class="font-semibold text-[color:var(--brand-blue)] hover:underline" @click="edit(c)">Edit</button>
                            <button type="button" class="text-slate-400 hover:text-rose-600" @click="remove(c)">Remove</button>
                        </div>
                    </div>

                    <!-- Item picker, inline so the list stays the context. -->
                    <div v-if="itemsFor === c.id" class="mt-3 rounded-xl border border-[color:var(--brand-blue)]/30 p-3">
                        <div class="mb-2 flex flex-wrap items-center gap-2 text-xs">
                            <span class="font-semibold text-slate-600">{{ picked.length }} selected</span>
                            <button type="button" class="text-[color:var(--brand-blue)] hover:underline" @click="pickByTag('category', c)">Select by item category…</button>
                            <button type="button" class="text-slate-500 hover:underline" @click="picked = []">Clear</button>
                        </div>
                        <div class="max-h-64 overflow-y-auto rounded-lg border border-slate-200">
                            <label v-for="i in items" :key="i.id" class="flex items-center gap-2 border-b border-slate-100 px-2 py-1 text-xs last:border-0">
                                <input v-model="picked" type="checkbox" :value="i.id">
                                <span class="font-mono text-slate-400">{{ i.item_code }}</span>
                                <span class="flex-1 truncate">{{ i.title }}</span>
                                <span class="text-slate-400">{{ i.category }}<span v-if="i.class_group"> · {{ i.class_group }}</span></span>
                            </label>
                        </div>
                        <div class="mt-2 flex gap-2">
                            <button type="button" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-1.5 text-xs font-bold text-white" @click="saveItems(c)">Save items</button>
                            <button type="button" class="text-xs text-slate-500 underline" @click="itemsFor = null">Cancel</button>
                        </div>
                    </div>
                </li>
            </ul>

            <form v-if="editing" @submit.prevent="save" class="mt-4 grid gap-3 rounded-xl border border-[color:var(--brand-blue)]/30 p-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="lg:col-span-2"><span class="lbl">Name</span><input v-model="form.name" class="fld" placeholder="Dance Champion" required></label>
                <label><span class="lbl">Code</span><input v-model="form.code" class="fld" :placeholder="slug" :readonly="!!form.id"></label>
                <label><span class="lbl">Honour top</span><input v-model.number="form.honour_count" type="number" min="1" max="50" class="fld"></label>
                <label class="lg:col-span-4"><span class="lbl">Description</span><input v-model="form.description" class="fld"></label>

                <fieldset class="lg:col-span-2">
                    <span class="lbl">Awards</span>
                    <label v-for="(label, key) in awardTypes" :key="key" class="mr-3 inline-flex items-center gap-1.5 text-xs text-slate-600">
                        <input v-model="form.awards" type="checkbox" :value="key"> {{ label }}
                    </label>
                    <p v-if="form.errors.awards" class="mt-1 text-xs text-rose-600">{{ form.errors.awards }}</p>
                </fieldset>

                <label class="flex items-end gap-2 text-xs text-slate-600">
                    <input v-model="form.is_overall" type="checkbox"> Overall — every item counts
                </label>
                <label class="flex items-end gap-2 text-xs text-slate-600">
                    <input v-model="form.is_active" type="checkbox"> Active
                </label>

                <div class="flex items-end gap-2 lg:col-span-4">
                    <button type="submit" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white">Save category</button>
                    <button type="button" class="text-xs text-slate-500 underline" @click="editing = false">Cancel</button>
                </div>
            </form>
        </section>

        <!-- Who is winning, per category. -->
        <section v-for="s in standings" :key="s.category.id" class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">{{ s.category.name }}</h3>
                    <p class="text-xs text-slate-500">{{ s.category.award_labels.join(' · ') }}</p>
                </div>
                <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold"
                      :class="s.is_complete ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800'">
                    {{ s.items_counted }} of {{ s.items }} item{{ s.items === 1 ? '' : 's' }} published
                </span>
            </div>

            <p v-if="!s.is_complete" class="mb-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                Provisional — some items in this category have no published result yet, so these
                standings will move.
            </p>

            <div class="grid gap-4 lg:grid-cols-3">
                <div v-for="kind in ['individual', 'school', 'sahodaya']" :key="kind">
                    <template v-if="s[kind]">
                        <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ awardTypes[kind] }}</p>
                        <p v-if="!s[kind].length" class="text-xs text-slate-400">Nothing scored yet.</p>
                        <ol v-else class="space-y-1">
                            <li v-for="(row, i) in s[kind]" :key="i" class="flex items-start justify-between gap-2 rounded-lg border border-slate-200 px-2 py-1.5 text-xs">
                                <span class="min-w-0">
                                    <span class="font-bold text-slate-700">{{ row.rank }}</span>
                                    <span v-if="row.is_tied" class="ml-0.5 text-[10px] font-semibold text-amber-600" title="Joint">=</span>
                                    <span class="ml-1 font-medium text-slate-800">{{ row.name }}</span>
                                    <span v-if="row.school" class="block text-slate-400">{{ row.school }}</span>
                                    <span v-else-if="row.sahodaya" class="block text-slate-400">{{ row.sahodaya }}</span>
                                </span>
                                <span class="shrink-0 text-right">
                                    <span class="font-bold text-slate-800">{{ row.points }}</span>
                                    <span class="block text-[10px] text-slate-400">{{ row.firsts }} × 1st</span>
                                </span>
                            </li>
                        </ol>
                    </template>
                </div>
            </div>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({
    event: Object, events: Array, sahodayas: Array, permissions: Array,
    categories: Array, standings: Array, awardTypes: Object, items: Array, actionUrls: Object,
});

const editing = ref(false);
const itemsFor = ref(null);
const picked = ref([]);

const form = useForm({
    id: null, code: '', name: '', description: '',
    awards: ['individual'], is_overall: false, honour_count: 3, sort_order: 0, is_active: true,
});

const slug = computed(() => (form.name || '').toLowerCase().trim().replace(/[^a-z0-9]+/g, '-'));

function startNew() {
    form.reset();
    form.id = null;
    form.awards = ['individual'];
    editing.value = true;
}
function edit(c) {
    Object.assign(form, {
        id: c.id, code: c.code, name: c.name, description: c.description,
        awards: [...c.awards], is_overall: c.is_overall, honour_count: c.honour_count,
        sort_order: c.sort_order, is_active: c.is_active,
    });
    editing.value = true;
}
function save() {
    form.post(props.actionUrls.save, { preserveScroll: true, onSuccess: () => { editing.value = false; } });
}
function remove(c) {
    if (!confirm(`Remove "${c.name}"? Its item assignments go with it.`)) return;
    router.delete(`${props.actionUrls.destroy}/${c.id}`, { preserveScroll: true });
}

function openItems(c) {
    itemsFor.value = c.id;
    picked.value = [...c.item_ids];
}
function pickByTag(field, category) {
    const tag = window.prompt(`Add every item whose ${field} is… (${[...new Set(props.items.map((i) => i[field]).filter(Boolean))].join(', ')})`);
    if (!tag) return;
    const add = props.items.filter((i) => (i[field] || '').toLowerCase() === tag.toLowerCase().trim()).map((i) => i.id);
    picked.value = [...new Set([...picked.value, ...add])];
}
function saveItems(c) {
    router.post(`${props.actionUrls.assign}/${c.id}/items`, { item_ids: picked.value }, {
        preserveScroll: true,
        onSuccess: () => { itemsFor.value = null; },
    });
}
</script>

<style scoped>
.lbl { display: block; margin-bottom: 0.25rem; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: rgb(100 116 139); }
.fld { width: 100%; border-radius: 0.75rem; border: 1px solid rgb(203 213 225); padding: 0.4rem 0.7rem; font-size: 0.875rem; }
</style>
