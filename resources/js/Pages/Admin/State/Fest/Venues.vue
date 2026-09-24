<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Venues and stages</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        A venue is the building; stages and rooms sit inside it and are what an item is
                        scheduled at. Green rooms and reporting desks are places too.
                    </p>
                </div>
                <button type="button" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white" @click="startNew()">
                    Add place
                </button>
            </div>

            <p v-if="!venues.length" class="py-8 text-center text-sm text-slate-400">
                No venues yet. Scheduling needs at least one stage or room.
            </p>

            <ul v-else class="space-y-1">
                <li v-for="v in tree" :key="v.id">
                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 px-3 py-2"
                         :class="{ 'opacity-50': !v.is_active }">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-slate-800">
                                {{ v.name }}
                                <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-600">{{ v.kind.replace('_', ' ') }}</span>
                                <span v-if="v.code" class="ml-1 font-mono text-[11px] text-slate-400">{{ v.code }}</span>
                            </p>
                            <p class="text-xs text-slate-500">
                                <span v-if="v.capacity">Capacity {{ v.capacity }}</span>
                                <span v-if="v.officer_name"> · {{ v.officer_name }}<span v-if="v.officer_phone"> ({{ v.officer_phone }})</span></span>
                                <span v-if="v.address"> · {{ v.address }}</span>
                            </p>
                        </div>
                        <div class="flex gap-2 text-xs">
                            <button type="button" class="font-semibold text-[color:var(--brand-blue)] hover:underline" @click="edit(v)">Edit</button>
                            <button type="button" class="text-slate-400 hover:text-rose-600" @click="remove(v)">Remove</button>
                        </div>
                    </div>

                    <ul v-if="v.children.length" class="ml-6 mt-1 space-y-1 border-l border-slate-200 pl-3">
                        <li v-for="c in v.children" :key="c.id"
                            class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 px-3 py-2"
                            :class="{ 'opacity-50': !c.is_active }">
                            <div>
                                <p class="text-sm text-slate-700">
                                    {{ c.name }}
                                    <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-slate-600">{{ c.kind.replace('_', ' ') }}</span>
                                </p>
                                <p class="text-xs text-slate-500">
                                    <span v-if="c.capacity">Capacity {{ c.capacity }}</span>
                                    <span v-if="c.officer_name"> · {{ c.officer_name }}</span>
                                </p>
                            </div>
                            <div class="flex gap-2 text-xs">
                                <button type="button" class="font-semibold text-[color:var(--brand-blue)] hover:underline" @click="edit(c)">Edit</button>
                                <button type="button" class="text-slate-400 hover:text-rose-600" @click="remove(c)">Remove</button>
                            </div>
                        </li>
                    </ul>
                </li>
            </ul>
        </section>

        <section v-if="editing" class="rounded-2xl border border-[color:var(--brand-blue)]/30 bg-white p-5">
            <h3 class="mb-3 text-sm font-bold text-slate-900">{{ form.id ? 'Edit place' : 'New place' }}</h3>
            <form @submit.prevent="save" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="lg:col-span-2"><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Name</span><input v-model="form.name" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required></label>
                <label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Kind</span>
                    <select v-model="form.kind" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <option v-for="k in kinds" :key="k" :value="k">{{ k.replace('_', ' ') }}</option>
                    </select>
                </label>
                <label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Inside</span>
                    <select v-model="form.parent_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <option :value="null">Top level</option>
                        <option v-for="v in venues.filter(x => x.kind === 'venue' && x.id !== form.id)" :key="v.id" :value="v.id">{{ v.name }}</option>
                    </select>
                </label>
                <label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Code</span><input v-model="form.code" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></label>
                <label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Capacity</span><input v-model.number="form.capacity" type="number" min="1" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></label>
                <label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Officer</span><input v-model="form.officer_name" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></label>
                <label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Officer phone</span><input v-model="form.officer_phone" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="lg:col-span-4"><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Address</span><input v-model="form.address" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="lg:col-span-4"><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Directions</span><textarea v-model="form.directions" rows="2" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea></label>
                <div class="flex items-end gap-2 lg:col-span-4">
                    <button type="submit" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white">Save</button>
                    <button type="button" class="text-xs text-slate-500 underline" @click="editing = false">Cancel</button>
                </div>
            </form>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({ event: Object, events: Array, sahodayas: Array, permissions: Array, venues: Array, kinds: Array, actionUrls: Object });

const editing = ref(false);
const form = useForm({ id: null, name: '', code: '', kind: 'venue', parent_id: null, capacity: null, address: '', directions: '', officer_name: '', officer_phone: '', is_active: true });

const tree = computed(() => props.venues.filter((v) => !v.parent_id)
    .map((v) => ({ ...v, children: props.venues.filter((c) => c.parent_id === v.id) })));

function startNew() { form.reset(); form.id = null; editing.value = true; }
function edit(v) { Object.assign(form, v); editing.value = true; }
function save() { form.post(props.actionUrls.store, { preserveScroll: true, onSuccess: () => { editing.value = false; form.reset(); } }); }
function remove(v) {
    if (!confirm(`Remove ${v.name}?`)) return;
    router.delete(`${props.actionUrls.destroy}/${v.id}`, { preserveScroll: true });
}
</script>

