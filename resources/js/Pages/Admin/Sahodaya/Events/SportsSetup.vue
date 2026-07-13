<template>
    <SahodayaEventsLayout :title="`${event.title} — Setup`" :sahodaya="sahodaya" :event="event"
                         :show-header-title="false">
        <PageHeader :title="`${event.title} — Sports setup`" eyebrow="Setup"
                    description="Configure this sports event end-to-end: fees, Event Heads, items, rank points, then run competition by head.">
            <template #actions>
                <Link :href="competitionUrl" class="btn-primary text-sm">Open Event Heads →</Link>
            </template>
        </PageHeader>

        <FestEventWorkflowStepper :sahodaya-id="sahodaya.id" :event-id="event.id"
                                  event-type="sports" current-step="setup" />

        <SportsSetupSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="setup" />

        <div v-if="promoteStatus?.can_promote && sportsHubUrl"
             class="rounded-lg border border-indigo-100 bg-indigo-50/80 px-4 py-3 mb-6 text-sm text-indigo-950">
            <p class="font-semibold">
                Next step: promote {{ promoteStatus.pending_count }} Event Head{{ promoteStatus.pending_count === 1 ? '' : 's' }}
            </p>
            <p class="mt-1 text-xs text-indigo-900/80">
                After fees and items are set, promote heads into separate discipline events from the Sports hub.
            </p>
            <Link :href="sportsHubUrl" class="inline-block mt-2 text-xs font-semibold underline">
                Open Sports hub to promote →
            </Link>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <section class="card">
                    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <div>
                            <h3 class="section-title">Event setup checklist</h3>
                            <p class="section-desc">Work top to bottom once when creating a new sports meet.</p>
                        </div>
                        <span class="text-sm font-semibold text-slate-700">
                            {{ checklistProgress.done }}/{{ checklistProgress.total }} complete
                        </span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-100 mb-5 overflow-hidden">
                        <div class="h-full bg-emerald-500 transition-all"
                             :style="{ width: `${progressPct}%` }" />
                    </div>
                    <ol class="space-y-2">
                        <li v-for="(step, index) in checklist" :key="step.key"
                            class="rounded-xl border transition"
                            :class="step.done
                                ? 'border-emerald-200 bg-emerald-50/50'
                                : step.optional
                                    ? 'border-slate-100 bg-slate-50/60'
                                    : 'border-slate-200 bg-white hover:border-indigo-200'">
                            <Link :href="step.href" class="flex items-start gap-3 p-4 hover:no-underline group">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold"
                                      :class="step.done ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-600'">
                                    {{ step.done ? '✓' : index + 1 }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-slate-900 group-hover:text-indigo-800">
                                        {{ step.label }}
                                        <span v-if="step.optional" class="ml-1 text-[10px] font-medium uppercase tracking-wide text-slate-400">Optional</span>
                                    </p>
                                    <p class="text-xs text-slate-600 mt-0.5">{{ step.hint }}</p>
                                    <p v-if="step.detail" class="text-xs font-medium text-indigo-700 mt-1">{{ step.detail }}</p>
                                </div>
                                <span class="text-indigo-600 text-sm font-semibold shrink-0 opacity-0 group-hover:opacity-100">Open →</span>
                            </Link>
                        </li>
                    </ol>
                </section>

                <section v-if="headItemGroups.length" class="card space-y-4">
                    <div>
                        <h3 class="section-title">Competition by Event Head</h3>
                        <p class="section-desc">
                            After setup, use each Event Head for day-of operations — dates, item fees, registrations, marks, and results.
                        </p>
                    </div>
                    <div class="reports-tile-grid">
                        <div v-for="head in headItemGroups" :key="head.head_id ?? 'other'"
                             class="reports-head-card group block">
                            <p class="font-semibold text-slate-900">{{ head.head_name }}</p>
                            <p class="text-xs text-slate-500 mt-1">{{ head.item_count }} item{{ head.item_count === 1 ? '' : 's' }}</p>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold">
                                <Link :href="headUrl(head.head_id)" class="text-indigo-600 hover:underline">
                                    Head schedule →
                                </Link>
                                <Link :href="itemsForHeadUrl(head.head_id)" class="text-emerald-700 hover:underline">
                                    Items under this head →
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="space-y-4">
                <section class="card space-y-3">
                    <h4 class="section-title">This event</h4>
                    <dl class="text-sm space-y-2">
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Event Heads</dt>
                            <dd class="font-semibold">{{ stats.heads }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Enabled items</dt>
                            <dd class="font-semibold">{{ stats.items }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Linked to heads</dt>
                            <dd class="font-semibold">{{ stats.linked }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="card space-y-3">
                    <h4 class="section-title">Sahodaya-wide masters</h4>
                    <p class="text-xs text-slate-600">Shared across all sports events — configure once, then load into each event.</p>
                    <ul class="space-y-2">
                        <li v-for="master in tenantMasters" :key="master.href">
                            <Link :href="master.href" class="block rounded-lg border border-slate-100 bg-slate-50/80 p-3 hover:border-indigo-200 hover:no-underline">
                                <p class="text-sm font-semibold text-slate-900">{{ master.label }}</p>
                                <p class="text-xs text-slate-500 mt-0.5">{{ master.hint }}</p>
                            </Link>
                        </li>
                    </ul>
                </section>

                <section class="card space-y-3">
                    <h4 class="section-title">Organiser tools</h4>
                    <p class="text-xs text-slate-600">Run-the-event essentials for this program.</p>
                    <ul class="space-y-2">
                        <li v-for="tool in organiserTools" :key="tool.href">
                            <Link :href="tool.href" class="block rounded-lg border border-slate-100 bg-slate-50/80 p-3 hover:border-indigo-200 hover:no-underline">
                                <p class="text-sm font-semibold text-slate-900">{{ tool.label }}</p>
                                <p class="text-xs text-slate-500 mt-0.5">{{ tool.hint }}</p>
                            </Link>
                        </li>
                    </ul>
                </section>

                <section v-if="ageRuleSummary" class="card space-y-2">
                    <h4 class="section-title">Age rules</h4>
                    <p class="text-xs text-slate-600">{{ ageRuleSummary }}</p>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/settings/eligibility`"
                          class="text-xs font-semibold text-indigo-700 hover:underline">
                        Age cutoff settings →
                    </Link>
                </section>
            </aside>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import FestEventWorkflowStepper from '@/Components/sahodaya/FestEventWorkflowStepper.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';

const props = defineProps({
    sahodaya: Object,
    event: Object,
    checklist: { type: Array, default: () => [] },
    checklistProgress: { type: Object, default: () => ({ done: 0, total: 0 }) },
    tenantMasters: { type: Array, default: () => [] },
    headItemGroups: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
    ageRuleSummary: { type: String, default: null },
    competitionUrl: { type: String, default: null },
    sportsHubUrl: { type: String, default: null },
    promoteStatus: { type: Object, default: null },
});

const progressPct = computed(() => {
    const { done, total } = props.checklistProgress;
    if (!total) return 0;
    return Math.round((done / total) * 100);
});

const base = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`);

const organiserTools = computed(() => [
    { label: 'Event Head coordinators', href: `${base.value}/event-staff`, hint: 'Assign mark-entry staff per Event Head' },
    { label: 'ID cards', href: `${base.value}/id-cards`, hint: 'Generate participant & staff cards' },
    { label: 'Reports', href: `${base.value}/reports/by-head`, hint: 'By head, item-wise, schedules, exports' },
    { label: 'Sidebar visibility', href: `/sahodaya-admin/${props.sahodaya.id}/settings/nav-visibility`, hint: 'Show/hide programs & menus' },
]);

function headUrl(headId) {
    if (headId == null) return `${base.value}/competition?head_id=other`;
    return `${base.value}/competition?head_id=${headId}`;
}

function itemsForHeadUrl(headId) {
    if (headId == null) return `${base.value}/items?head_id=other`;
    return `${base.value}/items?head_id=${headId}`;
}
</script>
