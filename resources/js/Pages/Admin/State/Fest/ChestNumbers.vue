<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Chest numbers</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Numbers are allocated in a block per Sahodaya, and an existing number is never
                        reissued — it is already on printed sheets and ID cards.
                    </p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <span class="rounded-full bg-emerald-50 px-2.5 py-1 font-bold text-emerald-700">{{ summary.numbered }} numbered</span>
                    <span v-if="summary.unnumbered" class="rounded-full bg-amber-50 px-2.5 py-1 font-bold text-amber-700">{{ summary.unnumbered }} without a number</span>
                    <span v-if="summary.duplicates" class="rounded-full bg-rose-50 px-2.5 py-1 font-bold text-rose-700">{{ summary.duplicates }} duplicates</span>
                </div>
            </div>

            <div class="mb-4 flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-slate-50/60 p-3">
                <label>
                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Start from</span>
                    <input v-model.number="assignForm.start" type="number" min="1" class="w-28 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                </label>
                <label>
                    <span class="mb-1 block text-[10px] font-bold uppercase tracking-wider text-slate-500">Block per Sahodaya</span>
                    <input v-model.number="assignForm.block_size" type="number" min="0" class="w-32 rounded-lg border border-slate-300 px-2 py-1.5 text-sm" placeholder="0 = none">
                </label>
                <select v-model="assignForm.sahodaya_id" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                    <option :value="null">All Sahodayas</option>
                    <option v-for="s in sahodayas" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <button type="button" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white" @click="assign">
                    Assign missing numbers
                </button>
                <p class="w-full text-[11px] text-slate-500">
                    Only participants without a number are touched, so this is safe to run again after entries are added.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[46rem] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <th class="py-2 pr-3">Chest</th>
                            <th class="py-2 px-3">Participant</th>
                            <th class="py-2 px-3">Class</th>
                            <th class="py-2 px-3">Sahodaya</th>
                            <th class="py-2 px-3">School</th>
                            <th class="py-2 px-3">Item</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in register" :key="r.participant_id" class="border-b border-slate-100"
                            :class="{ 'bg-amber-50/40': !r.chest_number }">
                            <td class="py-1.5 pr-3">
                                <input :value="r.chest_number" class="w-20 rounded-lg border border-slate-300 px-2 py-1 font-mono text-xs"
                                       :placeholder="'—'" @change="setNumber(r, $event.target.value)">
                            </td>
                            <td class="py-1.5 px-3 font-medium text-slate-800">{{ r.name }}</td>
                            <td class="py-1.5 px-3 text-slate-500">{{ r.class_name || '—' }}</td>
                            <td class="py-1.5 px-3 text-slate-600">{{ r.sahodaya }}</td>
                            <td class="py-1.5 px-3 text-slate-600">{{ r.school }}</td>
                            <td class="py-1.5 px-3 font-mono text-xs text-slate-500">{{ r.item_code }}</td>
                        </tr>
                        <tr v-if="!register.length">
                            <td colspan="6" class="py-10 text-center text-sm text-slate-400">No approved participants yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({
    event: Object, events: Array, sahodayas: Array, permissions: Array,
    register: Array, summary: Object, filters: Object, actionUrls: Object, baseUrl: String,
});

const assignForm = reactive({ start: 1, block_size: 0, sahodaya_id: props.filters?.sahodaya_id ?? null });

function assign() {
    router.post(props.actionUrls.assign, { ...assignForm }, { preserveScroll: true });
}

function setNumber(row, value) {
    router.post(props.actionUrls.set, { participant_id: row.participant_id, chest_number: value || null }, { preserveScroll: true });
}
</script>
