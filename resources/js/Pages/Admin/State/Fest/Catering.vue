<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Catering</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Meals are counted by Sahodaya, not by participant — a contingent arrives and eats
                        together. Entitlement is worked out from who is competing that day; what the
                        counter actually hands over is recorded separately.
                    </p>
                </div>
                <button type="button" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white" @click="startNew()">Add sitting</button>
            </div>

            <div class="mb-4 grid gap-2 sm:grid-cols-3">
                <div class="rounded-xl border border-slate-200 px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Entitled</p>
                    <p class="text-lg font-bold text-slate-900">{{ summary.totals.entitled }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 px-3 py-2">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Issued</p>
                    <p class="text-lg font-bold text-slate-900">{{ summary.totals.issued }}</p>
                </div>
                <div class="rounded-xl border px-3 py-2" :class="summary.totals.variance > 0 ? 'border-amber-300 bg-amber-50' : 'border-slate-200'">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Variance</p>
                    <p class="text-lg font-bold" :class="summary.totals.variance > 0 ? 'text-amber-700' : 'text-slate-900'">
                        {{ summary.totals.variance > 0 ? '+' : '' }}{{ summary.totals.variance }}
                    </p>
                </div>
            </div>

            <p v-if="!summary.sessions.length" class="py-8 text-center text-sm text-slate-400">No sittings planned yet.</p>

            <ul v-else class="space-y-1">
                <li v-for="s in summary.sessions" :key="s.id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-xl border px-3 py-2"
                    :class="[s.over_capacity ? 'border-amber-300 bg-amber-50' : 'border-slate-200', { 'opacity-50': !s.is_active }]">
                    <div>
                        <p class="text-sm font-medium text-slate-800">
                            {{ s.day }} · <span class="capitalize">{{ s.session }}</span>
                            <span v-if="s.venue" class="font-normal text-slate-500"> · {{ s.venue }}</span>
                        </p>
                        <p class="text-xs text-slate-500">
                            {{ s.entitled }} entitled · {{ s.issued }} issued · {{ s.sahodayas }} Sahodaya(s)
                            <span v-if="s.capacity"> · capacity {{ s.capacity }}</span>
                            <span v-if="s.menu"> · {{ s.menu }}</span>
                        </p>
                        <p v-if="s.over_capacity" class="text-xs font-semibold text-amber-700">Over capacity for this sitting.</p>
                    </div>
                    <div class="flex gap-2 text-xs">
                        <Link :href="`${baseUrl}?session_id=${s.id}`" class="font-semibold text-[color:var(--brand-blue)] hover:underline">Open</Link>
                        <button type="button" class="font-semibold text-[color:var(--brand-blue)] hover:underline" @click="edit(s)">Edit</button>
                    </div>
                </li>
            </ul>
        </section>

        <section v-if="editing" class="rounded-2xl border border-[color:var(--brand-blue)]/30 bg-white p-5">
            <h3 class="mb-3 text-sm font-bold text-slate-900">{{ form.id ? 'Edit sitting' : 'New sitting' }}</h3>
            <form @submit.prevent="save" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <label><span class="lbl">Date</span><input v-model="form.served_on" type="date" class="fld" required></label>
                <label><span class="lbl">Sitting</span>
                    <input v-model="form.session" list="session-names" class="fld" required>
                    <datalist id="session-names"><option v-for="n in sessionNames" :key="n" :value="n" /></datalist>
                </label>
                <label><span class="lbl">Venue</span>
                    <select v-model="form.venue_id" class="fld">
                        <option :value="null">Not set</option>
                        <option v-for="v in venues" :key="v.id" :value="v.id">{{ v.name }}</option>
                    </select>
                </label>
                <label><span class="lbl">Capacity</span><input v-model="form.capacity" type="number" min="0" class="fld"></label>
                <label class="lg:col-span-3"><span class="lbl">Menu</span><input v-model="form.menu" class="fld"></label>
                <div class="flex items-end gap-2 lg:col-span-4">
                    <button type="submit" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white">Save</button>
                    <button type="button" class="text-xs text-slate-500 underline" @click="editing = false">Cancel</button>
                </div>
            </form>
        </section>

        <section v-if="selected" class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-3 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-900">{{ selected.day }} · <span class="capitalize">{{ selected.session }}</span></h3>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Entitlement counts each participant once even if they are entered for several
                        items that day, plus {{ escortAllowance }} for escorts.
                    </p>
                </div>
                <button type="button" class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-bold text-slate-700" @click="freeze()">
                    Record entitlement
                </button>
            </div>

            <p v-if="!entitlement.length" class="py-6 text-center text-sm text-slate-400">
                Nothing is scheduled on this date, so no Sahodaya is entitled to this sitting.
            </p>

            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        <th class="py-2">Sahodaya</th>
                        <th class="py-2 text-right">Competing</th>
                        <th class="py-2 text-right">Entitled</th>
                        <th class="py-2 text-right">Recorded</th>
                        <th class="py-2 text-right">Issued</th>
                        <th class="py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in entitlement" :key="row.sahodaya_id" class="border-b border-slate-100">
                        <td class="py-2">
                            {{ row.sahodaya }}
                            <span v-if="row.sahodaya_id === 'unattributed'" class="ml-1 text-[10px] uppercase text-amber-600">no Sahodaya</span>
                        </td>
                        <td class="py-2 text-right text-slate-500">{{ row.participants }}</td>
                        <td class="py-2 text-right font-semibold">{{ row.entitled }}</td>
                        <td class="py-2 text-right text-slate-500">{{ row.recorded_entitled ?? '—' }}</td>
                        <td class="py-2 text-right">
                            <input v-model="issueCounts[row.sahodaya_id]" type="number" min="0"
                                   class="w-20 rounded-lg border border-slate-300 px-2 py-1 text-right text-xs"
                                   :placeholder="String(row.issued || 0)"
                                   :disabled="row.sahodaya_id === 'unattributed'">
                        </td>
                        <td class="py-2 text-right">
                            <button type="button" class="text-xs font-semibold text-[color:var(--brand-blue)] hover:underline"
                                    :disabled="row.sahodaya_id === 'unattributed'" @click="issue(row)">Record</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({
    event: Object, events: Array, sahodayas: Array, permissions: Array,
    summary: Object, filters: Object, selected: Object, entitlement: Array,
    venues: Array, sessionNames: Array, actionUrls: Object, baseUrl: String,
});

const escortAllowance = 2;
const editing = ref(false);
const issueCounts = reactive({});
const form = useForm({ id: null, served_on: '', session: 'lunch', menu: '', venue_id: null, capacity: null, is_active: true });

function startNew() { form.reset(); form.id = null; editing.value = true; }
function edit(s) {
    Object.assign(form, { id: s.id, served_on: s.served_on, session: s.session, menu: s.menu, venue_id: s.venue_id, capacity: s.capacity, is_active: s.is_active });
    editing.value = true;
}
function save() {
    form.post(props.actionUrls.saveSession, { preserveScroll: true, onSuccess: () => { editing.value = false; form.reset(); } });
}
function freeze() {
    router.post(props.actionUrls.freeze, { session_id: props.selected.id }, { preserveScroll: true });
}
function issue(row) {
    const count = issueCounts[row.sahodaya_id];
    if (count === undefined || count === '') return;
    router.post(props.actionUrls.issue, {
        session_id: props.selected.id,
        sahodaya_id: row.sahodaya_id,
        issued_count: Number(count),
    }, { preserveScroll: true });
}
</script>

<style scoped>
.lbl { display: block; margin-bottom: 0.25rem; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: rgb(100 116 139); }
.fld { width: 100%; border-radius: 0.75rem; border: 1px solid rgb(203 213 225); padding: 0.5rem 0.75rem; font-size: 0.875rem; }
</style>
