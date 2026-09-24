<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Schedule clashes</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Detected from the current schedule. Items with no time cannot clash.</p>
                </div>
                <Link :href="scheduleUrl" class="text-xs link-brand">Edit schedule →</Link>
            </div>

            <div class="mb-4 grid gap-3 sm:grid-cols-3">
                <Stat label="Participant clashes" :value="totals.participant" :tone="totals.participant ? 'bad' : 'ok'" />
                <Stat label="Stage double-booked" :value="totals.venue" :tone="totals.venue ? 'bad' : 'ok'" />
                <Stat label="Sahodaya overlaps" :value="totals.sahodaya" :tone="totals.sahodaya ? 'warn' : 'ok'" />
            </div>

            <div v-if="clashes.participant.length" class="mb-5">
                <h3 class="mb-2 text-xs font-bold uppercase tracking-wider text-rose-700">Participant clashes</h3>
                <ul class="space-y-1 text-sm">
                    <li v-for="(c, i) in clashes.participant" :key="i" class="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2">
                        <span class="font-semibold text-slate-800">{{ c.name }}</span>
                        <span v-if="c.kind === 'team'" class="ml-1 rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-rose-700">team</span>
                        <span class="text-xs text-slate-500"> · {{ c.sahodaya }} · {{ c.school }}</span>
                        <p class="text-xs text-slate-600">{{ c.date }}: {{ c.a }} and {{ c.b }}</p>
                    </li>
                </ul>
            </div>

            <div v-if="clashes.venue.length" class="mb-5">
                <h3 class="mb-2 text-xs font-bold uppercase tracking-wider text-rose-700">Stage double-booked</h3>
                <ul class="space-y-1 text-sm">
                    <li v-for="(c, i) in clashes.venue" :key="i" class="rounded-lg border border-rose-200 bg-rose-50/50 px-3 py-2">
                        <span class="font-semibold text-slate-800">{{ c.venue }}</span>
                        <p class="text-xs text-slate-600">{{ c.date }}: {{ c.a }} and {{ c.b }}</p>
                    </li>
                </ul>
            </div>

            <div v-if="clashes.sahodaya.length">
                <h3 class="mb-2 text-xs font-bold uppercase tracking-wider text-amber-700">Sahodaya overlaps</h3>
                <!-- Advisory: a large Sahodaya can cover four stages at once, a small one cannot, and
                     only the State office knows which. -->
                <p class="mb-2 text-xs text-slate-500">
                    Items running at the same time for one Sahodaya. Not necessarily a problem — a
                    large contingent can staff several stages.
                </p>
                <ul class="space-y-1 text-sm">
                    <li v-for="(c, i) in clashes.sahodaya" :key="i" class="rounded-lg border border-amber-200 bg-amber-50/50 px-3 py-2">
                        <span class="font-semibold text-slate-800">{{ c.sahodaya }}</span>
                        <p class="text-xs text-slate-600">{{ c.date }}: {{ c.a }} and {{ c.b }}</p>
                    </li>
                </ul>
            </div>

            <p v-if="!totals.participant && !totals.venue && !totals.sahodaya" class="py-10 text-center text-sm text-slate-400">
                No clashes in the current schedule.
            </p>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { h } from 'vue';
import { Link } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

defineProps({ event: Object, events: Array, sahodayas: Array, permissions: Array, clashes: Object, totals: Object, scheduleUrl: String });

const Stat = (p) => h('div', {
    class: {
        'rounded-xl border px-3 py-2.5': true,
        'border-rose-200 bg-rose-50': p.tone === 'bad',
        'border-amber-200 bg-amber-50': p.tone === 'warn',
        'border-emerald-200 bg-emerald-50': p.tone === 'ok',
    },
}, [
    h('p', { class: 'text-[10px] font-bold uppercase tracking-wider text-slate-500' }, p.label),
    h('p', { class: 'mt-0.5 text-xl font-extrabold tabular-nums text-slate-900' }, p.value),
]);
Stat.props = ['label', 'value', 'tone'];
</script>
