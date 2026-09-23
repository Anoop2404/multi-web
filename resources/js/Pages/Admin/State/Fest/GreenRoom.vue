<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Green room</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Who is due on stage, in performance order.</p>
                </div>
                <select :value="date" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" @change="pick($event.target.value)">
                    <option value="">All days</option>
                    <option v-for="d in dates" :key="d" :value="d">{{ d }}</option>
                </select>
            </div>

            <p v-if="!slots.length" class="py-10 text-center text-sm text-slate-400">Nothing scheduled for this selection.</p>

            <div v-for="s in slots" :key="s.item_code + s.date" class="mb-4 last:mb-0">
                <div class="mb-1 flex flex-wrap items-baseline gap-2">
                    <span class="font-mono text-xs font-bold text-slate-700">{{ s.item_code }}</span>
                    <span class="text-xs text-slate-500">
                        {{ s.date }}
                        <span v-if="s.reporting_at"> · report {{ s.reporting_at }}</span>
                        <span v-if="s.starts_at"> · stage {{ s.starts_at }}</span>
                        <span v-if="s.venue"> · {{ s.venue }}</span>
                    </span>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-[10px] uppercase tracking-wider text-slate-400">
                            <th class="py-1 pr-2">#</th>
                            <th class="py-1 px-2">Chest</th>
                            <th class="py-1 px-2">Participant / team</th>
                            <th class="py-1 px-2">Sahodaya</th>
                            <th class="py-1 px-2">School</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="e in s.entries" :key="e.order" class="border-b border-slate-100">
                            <td class="py-1 pr-2 tabular-nums text-slate-400">{{ e.order }}</td>
                            <td class="py-1 px-2 font-mono text-xs">{{ e.chest_number || '—' }}</td>
                            <td class="py-1 px-2 text-slate-700">{{ e.participants }}</td>
                            <td class="py-1 px-2 text-slate-600">{{ e.sahodaya }}</td>
                            <td class="py-1 px-2 text-slate-600">{{ e.school }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({ event: Object, events: Array, sahodayas: Array, permissions: Array, slots: Array, date: String, dates: Array, baseUrl: String });

function pick(value) {
    router.get(props.baseUrl, value ? { date: value } : {}, { preserveState: true, replace: true });
}
</script>
