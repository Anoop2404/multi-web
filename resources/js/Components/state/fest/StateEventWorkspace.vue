<template>
    <AdminLayout :title="`${event.name} — State Kalotsav`">
        <div class="space-y-4">
            <!-- Event header and switcher -->
            <div class="rounded-3xl bg-gradient-to-r from-[color:var(--brand-navy)] via-[color:var(--brand-navy-hover)] to-[color:var(--brand-navy)] p-5 sm:p-6 text-white shadow-xl">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="space-y-1 min-w-0">
                        <div class="inline-flex items-center gap-2 rounded-full border border-[color:var(--brand-gold)]/30 bg-[color:var(--brand-gold)]/15 px-3 py-1 text-xs font-semibold text-[color:var(--brand-gold)]">
                            State Kalotsav
                            <span v-if="event.program_title" class="text-slate-300 font-normal">· {{ event.program_title }}</span>
                        </div>
                        <h1 class="truncate text-xl sm:text-2xl font-extrabold tracking-tight">{{ event.name }}</h1>
                        <p class="text-xs text-slate-300">
                            <span class="uppercase">{{ event.status }}</span>
                            <span v-if="event.starts_on"> · {{ event.starts_on }}<span v-if="event.ends_on"> → {{ event.ends_on }}</span></span>
                            <span v-if="event.scoring_locked" class="ml-2 rounded bg-amber-400/20 px-2 py-0.5 font-semibold text-amber-200">Scoring locked</span>
                            <span v-if="event.results_published" class="ml-2 rounded bg-emerald-400/20 px-2 py-0.5 font-semibold text-emerald-200">Results published</span>
                        </p>
                    </div>

                    <div v-if="events.length > 1" class="shrink-0">
                        <label class="mb-1 block text-[11px] font-semibold uppercase tracking-wider text-slate-300">Event</label>
                        <select class="w-full rounded-xl border border-white/20 bg-white/10 px-3 py-2 text-sm text-white sm:w-64"
                                :value="event.id" @change="switchEvent($event.target.value)">
                            <option v-for="e in events" :key="e.id" :value="e.id" class="text-slate-900">{{ e.name }}</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-4 lg:flex-row">
                <!-- Workspace sidebar. Already filtered to what this role may open, so an operator
                     is never shown a map of what they are not trusted with. -->
                <nav class="shrink-0 lg:w-60">
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-3">
                        <div v-for="section in nav" :key="section.section" class="mb-3 last:mb-0">
                            <p class="px-2 pb-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ section.section }}</p>
                            <ul class="space-y-0.5">
                                <li v-for="item in section.items" :key="item.href">
                                    <Link v-if="isReady(item)" :href="item.href"
                                          class="block rounded-lg px-2 py-1.5 text-xs font-medium transition"
                                          :class="isActive(item) ? 'bg-[color:var(--brand-navy)] text-white' : 'text-slate-600 hover:bg-slate-100'">
                                        {{ item.label }}
                                    </Link>
                                    <!-- Screens that arrive with their own phase. Shown, but plainly
                                         not yet available, rather than linking to a 404. -->
                                    <span v-else class="flex items-center justify-between rounded-lg px-2 py-1.5 text-xs text-slate-300" :title="`${item.label} — not built yet`">
                                        {{ item.label }}
                                        <span class="text-[9px] uppercase tracking-wide">soon</span>
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </nav>

                <div class="min-w-0 flex-1 space-y-4">
                    <StateFilterBar v-if="showFilters" :sahodayas="sahodayas" v-model="filters" />
                    <slot />
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import StateFilterBar from './StateFilterBar.vue';
import { stateEventWorkspaceNav, visibleStateNav } from '@/support/stateFestNav.js';

const props = defineProps({
    event: { type: Object, required: true },
    events: { type: Array, default: () => [] },
    sahodayas: { type: Array, default: () => [] },
    permissions: { type: Array, default: () => [] },
    showFilters: { type: Boolean, default: false },
    modelValue: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['update:modelValue']);

const filters = ref({ ...props.modelValue });
watch(filters, (v) => emit('update:modelValue', v), { deep: true });

const nav = computed(() => visibleStateNav(stateEventWorkspaceNav(props.event.id), props.permissions));

// The tabs that exist. Listed here rather than guessed from the nav so a half-built screen can never
// be linked to by accident; anything not listed renders as "soon" instead of linking to a 404.
const READY = ['', '/slots', '/reports', '/settings', '/items', '/venues', '/staff', '/submissions', '/scrutiny', '/registrations', '/pending', '/teams', '/substitutions', '/schedule', '/clashes', '/green-room', '/chest-numbers', '/attendance', '/marks', '/results', '/leaderboard', '/appeals', '/certificates', '/catering', '/volunteers', '/public-portal', '/grades', '/points', '/eligibility', '/prizes'];
function isReady(item) {
    const href = typeof item === 'string' ? item : item.href;

    // A link outside the workspace (the judge portal) is always live — it is not one of this
    // event's tabs, so the READY list has nothing to say about it.
    if (typeof item !== 'string' && item.external) return true;

    // Query strings carry a preselected filter, not a different screen: /reports?group=print is the
    // reports tab. Stripped before matching so those links are not treated as unbuilt.
    const tail = href.replace(`/admin/state/fest/${props.event.id}`, '').split('?')[0];

    // A deeper path under a ready tab is that tab: /reports/participant-cards is the reports hub
    // opening one report. Matched by segment, not by string prefix, so /reportsomething would not
    // pass as /reports.
    return READY.some((path) => tail === path || (path !== '' && tail.startsWith(`${path}/`)));
}

const page = usePage();
function isActive(item) {
    const url = page.url.split('?')[0];
    return item.exact ? url === item.href : url.startsWith(item.href);
}

function switchEvent(id) {
    router.visit(`/admin/state/fest/${id}`);
}
</script>
