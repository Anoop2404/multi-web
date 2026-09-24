<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Mark entry</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ settings.judge_count }} judges ·
                        {{ settings.averaging === 'best' ? 'best score' : 'mean' }}<span v-if="settings.drop_high_low">, highest and lowest dropped</span> ·
                        {{ settings.decimals }} decimals
                    </p>
                </div>
                <div class="flex gap-2">
                    <select v-model="itemId" class="rounded-xl border border-slate-300 px-3 py-2 text-sm" @change="apply">
                        <option :value="null">Choose an item…</option>
                        <option v-for="i in items" :key="i.id" :value="i.id">{{ i.item_code }} — {{ i.title }}</option>
                    </select>
                    <button v-if="itemId" type="button" class="rounded-xl bg-[color:var(--brand-navy)] px-4 py-2 text-xs font-bold text-white" @click="aggregate">
                        Aggregate panel
                    </button>
                </div>
            </div>

            <p v-if="!itemId" class="py-10 text-center text-sm text-slate-400">Choose an item to see its entries.</p>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[50rem] text-sm">
                    <thead>
                        <tr class="border-b border-slate-200 text-left text-[11px] uppercase tracking-wider text-slate-500">
                            <th class="py-2 pr-2">Chest</th>
                            <th class="py-2 px-2">Participant</th>
                            <th class="py-2 px-2">Sahodaya</th>
                            <th class="py-2 px-2">School</th>
                            <th class="py-2 px-2">Attendance</th>
                            <th class="py-2 px-2 text-center">Score</th>
                            <th class="py-2 px-2 text-center">Grade</th>
                            <th class="py-2 px-2 text-center">Position</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in rows" :key="r.participant_id" class="border-b border-slate-100"
                            :class="{ 'bg-rose-50/40': nonCompeting(r) }">
                            <td class="py-1.5 pr-2 font-mono text-xs">{{ r.chest_number || '—' }}</td>
                            <td class="py-1.5 px-2 text-slate-800">{{ r.name }}</td>
                            <td class="py-1.5 px-2 text-slate-600">{{ r.sahodaya }}</td>
                            <td class="py-1.5 px-2 text-slate-600">{{ r.school }}</td>
                            <td class="py-1.5 px-2 text-xs" :class="nonCompeting(r) ? 'font-semibold text-rose-700' : 'text-slate-500'">
                                {{ r.attendance || 'not marked' }}
                            </td>
                            <td class="py-1.5 px-2 text-center tabular-nums">{{ r.score ?? '—' }}</td>
                            <td class="py-1.5 px-2 text-center">{{ r.grade || '—' }}</td>
                            <td class="py-1.5 px-2 text-center font-bold tabular-nums">{{ r.position ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                <p class="mt-3 text-xs text-slate-400">
                    Judges enter their own scores through the judge portal; aggregating combines them into the score shown here.
                    A participant marked absent, withdrawn or disqualified is skipped.
                </p>
            </div>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({
    event: Object, events: Array, sahodayas: Array, permissions: Array,
    items: Array, filters: Object, settings: Object, rows: Array, actionUrls: Object, baseUrl: String,
});

const itemId = ref(props.filters?.item_id ?? null);
const NON_COMPETING = ['absent', 'withdrawn', 'disqualified'];

function nonCompeting(row) { return NON_COMPETING.includes(row.attendance); }
function apply() { router.get(props.baseUrl, itemId.value ? { item_id: itemId.value } : {}, { preserveState: false }); }
function aggregate() { router.post(props.actionUrls.aggregate, { item_id: itemId.value }, { preserveScroll: true }); }
</script>
