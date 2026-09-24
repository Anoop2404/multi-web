<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Event staff</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Who is running the event. A name and a phone number is enough — most event
                        staff are here for three days and never sign in.
                    </p>
                </div>
                <button type="button" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white" @click="startNew()">Add staff</button>
            </div>

            <p v-if="!staff.length" class="py-8 text-center text-sm text-slate-400">No staff recorded yet.</p>

            <div v-for="(group, role) in grouped" :key="role" class="mb-4 last:mb-0">
                <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ roles[role] || role }}</p>
                <ul class="space-y-1">
                    <li v-for="s in group" :key="s.id"
                        class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 px-3 py-2"
                        :class="{ 'opacity-50': !s.is_active }">
                        <div>
                            <p class="text-sm font-medium text-slate-800">{{ s.name }}</p>
                            <p class="text-xs text-slate-500">
                                <span v-if="s.phone">{{ s.phone }}</span>
                                <span v-if="s.email"> · {{ s.email }}</span>
                                <span v-if="venueName(s.venue_id)"> · {{ venueName(s.venue_id) }}</span>
                            </p>
                        </div>
                        <div class="flex gap-2 text-xs">
                            <button type="button" class="font-semibold text-[color:var(--brand-blue)] hover:underline" @click="edit(s)">Edit</button>
                            <button type="button" class="text-slate-400 hover:text-rose-600" @click="remove(s)">Remove</button>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <section v-if="editing" class="rounded-2xl border border-[color:var(--brand-blue)]/30 bg-white p-5">
            <h3 class="mb-3 text-sm font-bold text-slate-900">{{ form.id ? 'Edit staff member' : 'New staff member' }}</h3>
            <form @submit.prevent="save" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label class="lg:col-span-2"><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Name</span><input v-model="form.name" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" required></label>
                <label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Role</span>
                    <select v-model="form.role" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <option v-for="(label, key) in roles" :key="key" :value="key">{{ label }}</option>
                    </select>
                </label>
                <label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Venue</span>
                    <select v-model="form.venue_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                        <option :value="null">Not assigned</option>
                        <option v-for="v in venues" :key="v.id" :value="v.id">{{ v.name }}</option>
                    </select>
                </label>
                <label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Phone</span><input v-model="form.phone" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></label>
                <label><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Email</span><input v-model="form.email" type="email" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></label>
                <label class="lg:col-span-2"><span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Notes</span><input v-model="form.notes" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></label>
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

const props = defineProps({ event: Object, events: Array, sahodayas: Array, permissions: Array, staff: Array, roles: Object, venues: Array, actionUrls: Object });

const editing = ref(false);
const form = useForm({ id: null, name: '', phone: '', email: '', role: 'volunteer', venue_id: null, notes: '', is_active: true });

const grouped = computed(() => props.staff.reduce((acc, s) => {
    (acc[s.role] ||= []).push(s);
    return acc;
}, {}));

function venueName(id) { return props.venues.find((v) => v.id === id)?.name; }
function startNew() { form.reset(); form.id = null; editing.value = true; }
function edit(s) { Object.assign(form, s); editing.value = true; }
function save() { form.post(props.actionUrls.store, { preserveScroll: true, onSuccess: () => { editing.value = false; form.reset(); } }); }
function remove(s) {
    if (!confirm(`Remove ${s.name}?`)) return;
    router.delete(`${props.actionUrls.destroy}/${s.id}`, { preserveScroll: true });
}
</script>

