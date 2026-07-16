<template>
    <SahodayaEventsLayout :title="`${event.title} — Setup`" :sahodaya="sahodaya" :event="event"
                         :show-header-title="false">
        <PageHeader :title="`${event.title} — Sports setup`" eyebrow="Setup"
                    :description="isSeason
                        ? 'Configure this sports season: age groups, then open each sport event for fees, items, and registration.'
                        : 'Configure this sport event: items, fees, registration windows — then open registration.'">
            <template #actions>
                <Link :href="competitionUrl" class="btn-primary text-sm">
                    {{ isSeason ? 'Open sport events →' : 'Items →' }}
                </Link>
            </template>
        </PageHeader>

        <FestEventWorkflowStepper :sahodaya-id="sahodaya.id" :event-id="event.id"
                                  event-type="sports" current-step="setup" />

        <SportsSetupSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" :event="event" active="setup" />

        <div v-if="sportsHubUrl"
             class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 mb-6 text-sm text-slate-700">
            <p v-if="isSeason">
                Each sport (Athletics, Chess, …) is its own event.
                <Link :href="sportsHubUrl" class="font-semibold underline ml-1">Open Sports hub →</Link>
            </p>
            <p v-else>
                This is a standalone sport event — schools register directly on it.
                <Link :href="sportsHubUrl" class="font-semibold underline ml-1">All sports →</Link>
            </p>
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

                <section v-if="headItemGroups.length || canAddSport" class="card space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h3 class="section-title">Sport events</h3>
                            <p class="section-desc">
                                {{ isSeason
                                    ? 'Each sport is its own event — set fees, items, registration, marks, and results there.'
                                    : 'Adding a sport turns this event into a season container — schools then register on the sports underneath, not on this event.' }}
                            </p>
                        </div>
                        <button v-if="canAddSport" type="button" class="btn-primary text-sm"
                                @click="showAddSport = !showAddSport">
                            + Add sport
                        </button>
                    </div>

                    <form v-if="showAddSport && canAddSport" class="rounded-xl border border-indigo-100 bg-indigo-50/40 p-4 space-y-3"
                          @submit.prevent="submitAddSport">
                        <div class="grid sm:grid-cols-2 gap-3">
                            <div>
                                <label class="form-label" for="add-sport-name">Sport name</label>
                                <input id="add-sport-name" v-model="addSportForm.name" type="text" required
                                       class="form-input" placeholder="e.g. Athletics, Chess, Kabaddi" />
                                <p v-if="addSportForm.errors.name" class="text-xs text-rose-600 mt-1">{{ addSportForm.errors.name }}</p>
                            </div>
                            <div>
                                <label class="form-label" for="add-sport-discipline">Discipline (optional)</label>
                                <input id="add-sport-discipline" v-model="addSportForm.sport_discipline" type="text"
                                       class="form-input" placeholder="e.g. athletics, racket" />
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="addSportForm.is_team_heading" type="checkbox" class="rounded border-slate-300" />
                            Has team items
                        </label>
                        <p class="text-xs text-slate-500">
                            Sport events start empty — load items afterwards via "Assign items to event".
                        </p>
                        <div class="flex gap-2">
                            <button type="submit" class="btn-primary text-sm" :disabled="addSportForm.processing">
                                Create sport event
                            </button>
                            <button type="button" class="btn-secondary text-sm" @click="showAddSport = false">Cancel</button>
                        </div>
                    </form>
                    <div class="reports-tile-grid">
                        <div v-for="head in headItemGroups" :key="head.head_id ?? 'other'"
                             class="reports-head-card group block">
                            <p class="font-semibold text-slate-950 text-sm leading-snug">{{ head.head_name }}</p>

                            <!-- Dates summary -->
                            <div class="mt-2 space-y-0.5 text-[10px] text-slate-500">
                                <p v-if="head.registration_open || head.registration_close" class="flex items-center gap-1">
                                    <span>📝 Reg:</span>
                                    <span>{{ formatDateRange(head.registration_open, head.registration_close) }}</span>
                                </p>
                                <p v-if="head.event_start || head.event_end" class="flex items-center gap-1">
                                    <span>🏆 Comp:</span>
                                    <span>{{ formatDateRange(head.event_start, head.event_end) }}</span>
                                </p>
                            </div>

                            <!-- Participation Stats -->
                            <div class="mt-2.5 flex flex-wrap gap-x-2 gap-y-1 text-[10px] font-medium text-slate-600">
                                <span class="bg-slate-100 px-1.5 py-0.5 rounded">{{ head.item_count }} item{{ head.item_count === 1 ? '' : 's' }}</span>
                                <span class="bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded">{{ head.schools_count ?? 0 }} school{{ head.schools_count === 1 ? '' : 's' }}</span>
                                <span class="bg-emerald-50 text-emerald-700 px-1.5 py-0.5 rounded">{{ head.athletes_count ?? 0 }} athlete{{ head.athletes_count === 1 ? '' : 's' }}</span>
                            </div>

                            <!-- Fee Config status badge -->
                            <div class="mt-2.5">
                                <span class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider border"
                                      :class="head.fees_configured ? 'bg-emerald-50 border-emerald-100 text-emerald-800' : 'bg-amber-50 border-amber-100 text-amber-800'">
                                    {{ head.fees_configured ? 'Composite billing active' : 'Fee config pending' }}
                                </span>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold pt-2 border-t border-slate-100">
                                <Link :href="sportEventUrl(head)" class="text-indigo-600 hover:underline">
                                    Open event →
                                </Link>
                                <Link :href="`${sportEventUrl(head)}/items`" class="text-emerald-700 hover:underline">
                                    Items →
                                </Link>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <aside class="space-y-4">
                <section class="card space-y-3">
                    <h4 class="section-title">{{ isSeason ? 'This season' : 'This sport event' }}</h4>
                    <dl class="text-sm space-y-2">
                        <div v-if="isSeason" class="flex justify-between gap-2">
                            <dt class="text-slate-500">Sport events</dt>
                            <dd class="font-semibold">{{ stats.heads }}</dd>
                        </div>
                        <div class="flex justify-between gap-2">
                            <dt class="text-slate-500">Enabled items</dt>
                            <dd class="font-semibold">{{ stats.items }}</dd>
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
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
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
    isSeason: { type: Boolean, default: false },
    canAddSport: { type: Boolean, default: false },
    addSportUrl: { type: String, default: null },
});

const showAddSport = ref(false);
const addSportForm = useForm({
    name: '',
    sport_discipline: '',
    is_team_heading: true,
});

function submitAddSport() {
    if (!props.addSportUrl) return;
    addSportForm.post(props.addSportUrl, {
        preserveScroll: true,
        onSuccess: () => {
            addSportForm.reset();
            showAddSport.value = false;
        },
    });
}

const progressPct = computed(() => {
    const { done, total } = props.checklistProgress;
    if (!total) return 0;
    return Math.round((done / total) * 100);
});

const organiserTools = computed(() => [
    { label: 'Event staff', href: `/sahodaya-admin/${props.sahodaya.id}/sports`, hint: 'Assign mark-entry staff per sport event' },
    { label: 'Sports hub', href: `/sahodaya-admin/${props.sahodaya.id}/sports`, hint: 'All sport events this season' },
]);

function sportEventUrl(head) {
    if (head.href) return head.href;
    return `/sahodaya-admin/${props.sahodaya.id}/events/${head.head_id}`;
}

function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatDateRange(start, end) {
    if (!start && !end) return 'Not scheduled';
    if (start && end) {
        if (start === end) return formatDate(start);
        return `${formatDate(start)} – ${formatDate(end)}`;
    }
    return start ? `From ${formatDate(start)}` : `Until ${formatDate(end)}`;
}
</script>
