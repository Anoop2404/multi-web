<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <div v-if="!windows.scrutiny_open" class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
            {{ windows.scrutiny_note }} Decisions are refused until the window reopens.
        </div>

        <section class="rounded-2xl border border-slate-200/80 bg-white p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-slate-900">Sahodaya submissions</h2>
                    <p class="mt-0.5 text-xs text-slate-500">Every qualifier package sent to the State, whichever way it arrived.</p>
                </div>
                <div class="flex flex-wrap gap-2 text-xs">
                    <button v-for="f in ['all', 'awaiting', 'finalised']" :key="f" type="button"
                            class="rounded-full px-3 py-1 font-semibold"
                            :class="filter === f ? 'bg-[color:var(--brand-navy)] text-white' : 'bg-slate-100 text-slate-600'"
                            @click="filter = f">
                        {{ f }}
                    </button>
                </div>
            </div>

            <p v-if="!visible.length" class="py-10 text-center text-sm text-slate-400">No submissions match.</p>

            <ul v-else class="space-y-2">
                <li v-for="s in visible" :key="s.id" class="rounded-xl border border-slate-200 p-3">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="flex flex-wrap items-center gap-2 text-sm font-semibold text-slate-800">
                                {{ s.sahodaya }}
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase"
                                      :class="s.source === 'external' ? 'bg-sky-50 text-sky-700' : 'bg-violet-50 text-violet-700'">
                                    {{ s.source === 'external' ? 'Outside' : 'On platform' }}
                                </span>
                                <span v-if="s.district" class="text-xs font-normal text-slate-400">{{ s.district }}</span>
                            </p>
                            <p class="mt-0.5 text-xs text-slate-500">
                                {{ s.schools }} school{{ s.schools === 1 ? '' : 's' }} ·
                                {{ s.entries }} entr{{ s.entries === 1 ? 'y' : 'ies' }} ·
                                submitted {{ s.submitted_at }}
                            </p>
                            <div class="mt-1.5 flex flex-wrap gap-1.5 text-[11px]">
                                <Pill v-if="s.pending" tone="warn">{{ s.pending }} pending</Pill>
                                <Pill v-if="s.returned" tone="warn">{{ s.returned }} returned</Pill>
                                <Pill v-if="s.documents_requested" tone="warn">{{ s.documents_requested }} awaiting documents</Pill>
                                <Pill v-if="s.approved" tone="ok">{{ s.approved }} approved</Pill>
                                <Pill v-if="s.rejected" tone="bad">{{ s.rejected }} rejected</Pill>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase"
                                  :class="{
                                      'bg-amber-50 text-amber-700': s.status === 'received',
                                      'bg-emerald-50 text-emerald-700': s.status === 'approved',
                                      'bg-rose-50 text-rose-700': s.status === 'rejected',
                                  }">{{ s.status }}</span>
                            <Link :href="s.review_url" class="rounded-xl bg-[color:var(--brand-navy)] px-3 py-2 text-xs font-bold text-white">Scrutinise →</Link>
                        </div>
                    </div>
                </li>
            </ul>
        </section>
    </StateEventWorkspace>
</template>

<script setup>
import { computed, h, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';

const props = defineProps({ event: Object, events: Array, sahodayas: Array, permissions: Array, submissions: Array, windows: Object });

const Pill = (p, { slots }) => h('span', {
    class: {
        'rounded-full px-2 py-0.5 font-semibold': true,
        'bg-amber-50 text-amber-700': p.tone === 'warn',
        'bg-emerald-50 text-emerald-700': p.tone === 'ok',
        'bg-rose-50 text-rose-700': p.tone === 'bad',
    },
}, slots.default?.());
Pill.props = ['tone'];

const filter = ref('all');
const visible = computed(() => props.submissions.filter((s) => {
    if (filter.value === 'awaiting') return s.status === 'received';
    if (filter.value === 'finalised') return s.status !== 'received';
    return true;
}));
</script>
