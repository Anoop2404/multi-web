<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <h2 class="mb-3 text-sm font-bold text-slate-900">All registrations</h2>

            <!-- Sahodaya first, School second — the module's fixed hierarchy, with School disabled
                 until a Sahodaya is chosen because it otherwise matches nothing. -->
            <div class="mb-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-6">
                <select v-model="f.sahodaya_id" class="inp" @change="apply">
                    <option :value="null">All Sahodayas</option>
                    <option v-for="s in sahodayas" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <select v-model="f.item_id" class="inp" @change="apply">
                    <option :value="null">All items</option>
                    <option v-for="i in items" :key="i.id" :value="i.id">{{ i.item_code }} — {{ i.title }}</option>
                </select>
                <select v-model="f.type" class="inp" @change="apply">
                    <option :value="null">Individual and team</option>
                    <option value="individual">Individual</option>
                    <option value="team">Team</option>
                </select>
                <select v-model="f.origin" class="inp" @change="apply">
                    <option :value="null">Managed and outside</option>
                    <option value="managed">On the platform</option>
                    <option value="external">From outside</option>
                </select>
                <select v-model="f.status" class="inp" @change="apply">
                    <option :value="null">Any status</option>
                    <option value="approved">Approved</option>
                    <option value="pending">Pending</option>
                </select>
                <input v-model="f.search" type="search" class="inp" placeholder="Participant…" @keyup.enter="apply">
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[56rem] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <th class="py-2 pr-3">Sahodaya</th>
                            <th class="py-2 px-3">School</th>
                            <th class="py-2 px-3">Participant / team</th>
                            <th class="py-2 px-3">Class</th>
                            <th class="py-2 px-3">Item</th>
                            <th class="py-2 px-3 text-center">Chest</th>
                            <th class="py-2 px-3">Status</th>
                            <th class="py-2 px-3">Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in registrations.data" :key="r.id" class="border-b border-slate-100">
                            <td class="py-2 pr-3 font-medium text-slate-800">{{ r.sahodaya }}</td>
                            <td class="py-2 px-3 text-slate-600">{{ r.school }}</td>
                            <td class="py-2 px-3 text-slate-700">
                                {{ r.participants }}
                                <span v-if="r.is_team" class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-600">team</span>
                            </td>
                            <td class="py-2 px-3 text-slate-500">{{ r.class_name || '—' }}</td>
                            <td class="py-2 px-3 font-mono text-xs">{{ r.item_code }}</td>
                            <td class="py-2 px-3 text-center tabular-nums">{{ r.chest_number || '—' }}</td>
                            <td class="py-2 px-3">
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase"
                                      :class="r.status === 'approved' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'">{{ r.status }}</span>
                            </td>
                            <td class="py-2 px-3 text-xs" :class="r.attendance ? 'text-slate-700' : 'text-slate-400'">
                                {{ r.attendance || 'not marked' }}
                            </td>
                        </tr>
                        <tr v-if="!registrations.data.length">
                            <td colspan="8" class="py-10 text-center text-sm text-slate-400">No registrations match these filters.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="mt-3 text-xs text-slate-400">
                Showing {{ registrations.data.length }} of {{ registrations.total }}
            </p>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({
    event: Object, events: Array, sahodayas: Array, permissions: Array,
    registrations: Object, filters: Object, items: Array, baseUrl: String,
});

const f = reactive({
    sahodaya_id: props.filters?.sahodaya_id ?? null,
    item_id: props.filters?.item_id ?? null,
    type: props.filters?.type ?? null,
    origin: props.filters?.origin ?? null,
    status: props.filters?.status ?? null,
    search: props.filters?.search ?? '',
});

function apply() {
    const q = Object.fromEntries(Object.entries(f).filter(([, v]) => v !== null && v !== ''));
    router.get(props.baseUrl, q, { preserveState: true, replace: true });
}
</script>

<style scoped>
.inp { width: 100%; border-radius: 0.75rem; border: 1px solid rgb(203 213 225); padding: 0.5rem 0.75rem; font-size: 0.875rem; }
</style>
