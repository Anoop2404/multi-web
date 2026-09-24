<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section v-if="uncovered.length" class="rounded-2xl border border-amber-300 bg-amber-50 p-4">
            <h2 class="text-sm font-bold text-amber-900">{{ uncovered.length }} place(s) in use with nobody rostered</h2>
            <p class="mt-0.5 text-xs text-amber-800">
                These stages have items scheduled on them and no volunteer or official on duty that day.
            </p>
            <ul class="mt-2 space-y-0.5 text-xs text-amber-900">
                <li v-for="(u, i) in uncovered" :key="i">{{ u.date }} · {{ u.venue }} · {{ u.items }} item(s)</li>
            </ul>
        </section>

        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Volunteers &amp; officials</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Who is where, session by session. People are added under
                        <Link :href="actionUrls.staff" class="font-semibold text-[color:var(--brand-blue)] hover:underline">Event Staff</Link>;
                        this page puts them on duty.
                    </p>
                </div>
                <div class="flex items-end gap-2">
                    <label>
                        <span class="lbl">Day</span>
                        <input :value="filters.date || ''" type="date" class="fld" @change="filterDay($event.target.value)">
                    </label>
                    <button type="button" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white" @click="showForm = !showForm">Assign duty</button>
                </div>
            </div>

            <form v-if="showForm" @submit.prevent="assign" class="mb-4 grid gap-3 rounded-xl border border-[color:var(--brand-blue)]/30 p-3 sm:grid-cols-2 lg:grid-cols-4">
                <label><span class="lbl">Person</span>
                    <select v-model="form.staff_id" class="fld" required>
                        <option value="">Select…</option>
                        <option v-for="s in staff" :key="s.id" :value="s.id">{{ s.name }} — {{ s.role }}</option>
                    </select>
                </label>
                <label><span class="lbl">Date</span><input v-model="form.duty_on" type="date" class="fld" required></label>
                <label><span class="lbl">Session</span>
                    <select v-model="form.session" class="fld">
                        <option v-for="n in sessionNames" :key="n" :value="n">{{ n.replace('_', ' ') }}</option>
                    </select>
                </label>
                <label><span class="lbl">Place</span>
                    <select v-model="form.venue_id" class="fld">
                        <option :value="null">Not set</option>
                        <option v-for="v in venues" :key="v.id" :value="v.id">{{ v.name }}</option>
                    </select>
                </label>
                <label class="lg:col-span-2"><span class="lbl">Duty</span><input v-model="form.duty" class="fld" placeholder="Stage manager, gate, green room…"></label>
                <div class="flex items-end gap-2 lg:col-span-2">
                    <button type="submit" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white">Assign</button>
                    <button type="button" class="text-xs text-slate-500 underline" @click="showForm = false">Cancel</button>
                </div>
            </form>

            <p v-if="!roster.length" class="py-8 text-center text-sm text-slate-400">Nobody is rostered yet.</p>

            <div v-for="(duties, day) in byDay" :key="day" class="mb-4 last:mb-0">
                <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ day }}</p>
                <ul class="space-y-1">
                    <li v-for="d in duties" :key="d.id" class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 px-3 py-2">
                        <div>
                            <p class="text-sm font-medium text-slate-800">
                                {{ d.staff }}
                                <span class="font-normal text-slate-500">· <span class="capitalize">{{ d.session.replace('_', ' ') }}</span></span>
                            </p>
                            <p class="text-xs text-slate-500">
                                <span v-if="d.role" class="capitalize">{{ d.role.replace('_', ' ') }}</span>
                                <span v-if="d.venue"> · {{ d.venue }}</span>
                                <span v-if="d.duty"> · {{ d.duty }}</span>
                                <span v-if="d.phone"> · {{ d.phone }}</span>
                            </p>
                        </div>
                        <button type="button" class="text-xs text-slate-400 hover:text-rose-600" @click="remove(d)">Remove</button>
                    </li>
                </ul>
            </div>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({
    event: Object, events: Array, sahodayas: Array, permissions: Array,
    roster: Array, uncovered: Array, staff: Array, venues: Array,
    sessionNames: Array, filters: Object, actionUrls: Object, baseUrl: String,
});

const showForm = ref(false);
const form = useForm({ staff_id: '', duty_on: '', session: 'morning', venue_id: null, duty: '', notes: '' });

const byDay = computed(() => props.roster.reduce((acc, d) => {
    (acc[d.day] ||= []).push(d);
    return acc;
}, {}));

function filterDay(date) {
    router.get(props.baseUrl, date ? { date } : {}, { preserveState: true, replace: true });
}
function assign() {
    form.post(props.actionUrls.assign, { preserveScroll: true, onSuccess: () => { showForm.value = false; form.reset(); } });
}
function remove(duty) {
    if (!confirm(`Remove ${duty.staff} from ${duty.session.replace('_', ' ')} on ${duty.day}?`)) return;
    router.delete(`${props.actionUrls.remove}/${duty.id}`, { preserveScroll: true });
}
</script>

<style scoped>
.lbl { display: block; margin-bottom: 0.25rem; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: rgb(100 116 139); }
.fld { width: 100%; border-radius: 0.75rem; border: 1px solid rgb(203 213 225); padding: 0.5rem 0.75rem; font-size: 0.875rem; }
</style>
