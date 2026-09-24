<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Sahodaya standings</h2>
                    <p class="mt-0.5 text-xs text-slate-500">
                        The State competes Sahodaya against Sahodaya. Expand a row to see which Schools earned its points.
                    </p>
                </div>
                <label class="inline-flex items-center gap-2 text-xs text-slate-600">
                    <input type="checkbox" :checked="includeProvisional" class="rounded border-slate-300" @change="toggle">
                    Include provisional results
                </label>
            </div>

            <p v-if="!standings.length" class="py-10 text-center text-sm text-slate-400">
                No published results yet — standings appear as items are published.
            </p>

            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-[11px] uppercase tracking-wider text-slate-500">
                        <th class="py-2 pr-2">#</th>
                        <th class="py-2 px-2">Sahodaya</th>
                        <th class="py-2 px-2 text-center">Points</th>
                        <th class="py-2 px-2 text-center">1st</th>
                        <th class="py-2 px-2 text-center">2nd</th>
                        <th class="py-2 px-2 text-center">3rd</th>
                        <th class="py-2 px-2 text-center">Items</th>
                        <th class="py-2 px-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="s in standings" :key="s.sahodaya_id">
                        <tr class="border-b border-slate-100" :class="{ 'bg-[color:var(--brand-navy)]/5': s.rank === 1 }">
                            <td class="py-2 pr-2 font-bold tabular-nums text-slate-500">{{ s.rank }}</td>
                            <td class="py-2 px-2">
                                <span class="font-medium text-slate-800">{{ s.sahodaya }}</span>
                                <span v-if="s.district" class="ml-1 text-xs text-slate-400">{{ s.district }}</span>
                                <span v-if="s.origin === 'external'" class="ml-1 rounded bg-sky-50 px-1.5 py-0.5 text-[10px] font-semibold text-sky-700">outside</span>
                            </td>
                            <td class="py-2 px-2 text-center text-base font-extrabold tabular-nums text-slate-900">{{ s.points }}</td>
                            <td class="py-2 px-2 text-center tabular-nums">{{ s.firsts }}</td>
                            <td class="py-2 px-2 text-center tabular-nums">{{ s.seconds }}</td>
                            <td class="py-2 px-2 text-center tabular-nums">{{ s.thirds }}</td>
                            <td class="py-2 px-2 text-center tabular-nums text-slate-500">{{ s.items }}</td>
                            <td class="py-2 px-2 text-right">
                                <button type="button" class="text-xs font-semibold text-[color:var(--brand-blue)] hover:underline" @click="expand(s)">
                                    {{ expanded === s.sahodaya_id ? 'Hide' : 'Schools' }}
                                </button>
                            </td>
                        </tr>
                        <tr v-if="expanded === s.sahodaya_id" class="border-b border-slate-100 bg-slate-50/60">
                            <td colspan="8" class="px-4 py-3">
                                <p class="mb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">School contribution</p>
                                <ul class="space-y-0.5 text-xs">
                                    <li v-for="c in contribution" :key="c.school" class="flex justify-between border-b border-slate-100 py-0.5">
                                        <span class="text-slate-700">{{ c.school }}</span>
                                        <span class="tabular-nums text-slate-600">{{ c.points }} pts · {{ c.firsts }} first · {{ c.entries }} entries</span>
                                    </li>
                                </ul>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </section>

        <section v-if="individual.length" class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <h2 class="mb-3 text-sm font-bold text-slate-900">Individual championship</h2>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-[11px] uppercase tracking-wider text-slate-500">
                        <th class="py-2 pr-2">Participant</th>
                        <th class="py-2 px-2">Sahodaya</th>
                        <th class="py-2 px-2">School</th>
                        <th class="py-2 px-2 text-center">Points</th>
                        <th class="py-2 px-2 text-center">1st</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(p, i) in individual" :key="i" class="border-b border-slate-100">
                        <td class="py-1.5 pr-2 font-medium text-slate-800">{{ p.participant }}</td>
                        <td class="py-1.5 px-2 text-slate-600">{{ p.sahodaya }}</td>
                        <td class="py-1.5 px-2 text-slate-600">{{ p.school }}</td>
                        <td class="py-1.5 px-2 text-center font-bold tabular-nums">{{ p.points }}</td>
                        <td class="py-1.5 px-2 text-center tabular-nums">{{ p.firsts }}</td>
                    </tr>
                </tbody>
            </table>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({
    event: Object, events: Array, sahodayas: Array, permissions: Array,
    standings: Array, includeProvisional: Boolean, expanded: String, contribution: Array, individual: Array, baseUrl: String,
});

function query(extra = {}) {
    return { ...(props.includeProvisional ? { provisional: 1 } : {}), ...extra };
}
function expand(s) {
    const next = props.expanded === s.sahodaya_id ? {} : { sahodaya_id: s.sahodaya_id };
    router.get(props.baseUrl, query(next), { preserveState: true, replace: true });
}
function toggle(e) {
    router.get(props.baseUrl, {
        ...(e.target.checked ? { provisional: 1 } : {}),
        ...(props.expanded ? { sahodaya_id: props.expanded } : {}),
    }, { preserveState: true, replace: true });
}
</script>
