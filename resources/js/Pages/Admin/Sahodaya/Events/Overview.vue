<template>
    <SahodayaEventsLayout :title="event.title" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        
        <!-- Header & Primary Actions -->
        <PageHeader :title="event.title" eyebrow="Event Overview"
                    :description="`${eventTypesLabel} · ${levelLabels[event.level_round] ?? event.level_round}`">
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <a :href="publicFestUrl" target="_blank" rel="noopener" class="btn-secondary text-xs">
                        Public portal ↗
                    </a>
                    <button type="button" class="btn-primary text-xs flex items-center gap-1.5 shadow-sm" :disabled="form.processing" @click="saveEvent">
                        <span>{{ form.processing ? 'Saving...' : 'Save event' }}</span>
                    </button>
                </div>
            </template>
        </PageHeader>

        <EventSubNav v-if="event.event_type !== 'sports'"
                     :sahodaya-id="sahodaya.id" :event-id="event.id" active="overview" />

        <!-- Warnings & System Alerts -->
        <div v-if="event.state_program_id" class="notice-banner notice-banner--warning mb-4">
            <strong>State program</strong> — propagated from central admin.
        </div>

        <div v-if="mistakenSeasonIssue" class="notice-banner notice-banner--warning mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <strong>Visibility issue detected</strong> — this event
                <span v-if="mistakenSeasonIssue.navHidden">is hidden from schools</span>
                <span v-if="mistakenSeasonIssue.navHidden && mistakenSeasonIssue.partitionRole === 'sports_season'"> and </span>
                <span v-if="mistakenSeasonIssue.partitionRole === 'sports_season'">is tagged as a season hub</span>,
                but it {{ mistakenSeasonIssue.children ? 'only has empty sport events under it' : 'has no sport events under it' }} —
                this usually happens by mistake, not because it's a real multi-sport season.
                <span v-if="mistakenSeasonIssue.emptyChildren">
                    ({{ mistakenSeasonIssue.emptyChildren }} empty child event{{ mistakenSeasonIssue.emptyChildren === 1 ? '' : 's' }} with zero registrations.)
                </span>
            </div>
            <button type="button" class="btn-primary text-xs whitespace-nowrap" :disabled="fixingSeason" @click="fixMistakenSeason">
                {{ fixingSeason ? 'Fixing…' : 'Fix visibility' }}
            </button>
        </div>

        <!-- Event Phase Stepper Banner -->
        <div class="card mb-6 bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 text-white !p-5 shadow-lg">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-xl font-bold text-white backdrop-blur">
                        {{ isSports ? '⚽' : '🎭' }}
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-base font-bold text-white leading-snug">{{ event.title }}</h2>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold uppercase tracking-wider border border-white/20 bg-white/10 text-white">
                                {{ form.status }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-300 mt-0.5">
                            {{ isSports ? 'Sports Meet Event Workspace' : 'Sahodaya Event Workspace' }}
                            <span v-if="event.venue" class="ml-2 opacity-80">· 📍 {{ event.venue }}</span>
                        </p>
                    </div>
                </div>

                <div v-if="isSports" class="flex items-center gap-2">
                    <Link :href="`${base}/setup`" class="btn-secondary text-xs !bg-white/10 hover:!bg-white/20 !text-white !border-white/20">
                        ⚙️ Setup Hub
                    </Link>
                    <Link :href="`${base}/items`" class="btn-primary text-xs !bg-indigo-600 hover:!bg-indigo-500 !text-white !border-transparent">
                        Items &amp; Catalog →
                    </Link>
                </div>
            </div>

            <!-- Workflow Status Steps -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-3 border-t border-white/10">
                <div v-for="step in workflowSteps" :key="step.status"
                     class="rounded-lg p-2.5 text-xs transition border"
                     :class="form.status === step.status
                         ? 'bg-indigo-600/90 border-indigo-400 text-white shadow-inner font-bold'
                         : 'bg-white/5 border-white/10 text-slate-300 hover:bg-white/10'">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-[10px] uppercase font-mono tracking-wider opacity-80">Step {{ step.num }}</span>
                        <span v-if="form.status === step.status" class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    </div>
                    <p class="font-medium text-xs leading-tight text-white">{{ step.label }}</p>
                </div>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <div class="card card--muted !py-3.5 text-center transition hover:border-slate-300">
                <p class="text-2xl font-black text-slate-900">{{ stats.items }}</p>
                <p class="text-xs font-semibold text-slate-500 mt-0.5 uppercase tracking-wider text-[10px]">Items Enabled</p>
            </div>
            <template v-if="event.event_type === 'sports'">
                <div class="card card--muted !py-3.5 text-center transition hover:border-indigo-300">
                    <p class="text-2xl font-black text-indigo-600">{{ stats.schools_count ?? 0 }}</p>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5 uppercase tracking-wider text-[10px]">Schools Registered</p>
                </div>
                <div class="card card--muted !py-3.5 text-center transition hover:border-emerald-300">
                    <p class="text-2xl font-black text-emerald-600">{{ stats.athletes_count ?? 0 }}</p>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5 uppercase tracking-wider text-[10px]">Athletes Registered</p>
                </div>
            </template>
            <template v-else>
                <div class="card card--muted !py-3.5 text-center transition hover:border-indigo-300">
                    <p class="text-2xl font-black text-indigo-600">{{ stats.registrations }}</p>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5 uppercase tracking-wider text-[10px]">Registrations</p>
                </div>
                <div class="card card--muted !py-3.5 text-center transition hover:border-violet-300">
                    <p class="text-2xl font-black text-violet-600">{{ stats.school_rounds }}</p>
                    <p class="text-xs font-semibold text-slate-500 mt-0.5 uppercase tracking-wider text-[10px]">School Rounds</p>
                </div>
            </template>
            <div class="card card--muted !py-3.5 text-center transition hover:border-amber-300">
                <p class="text-2xl font-black" :class="form.results_published ? 'text-emerald-600' : 'text-amber-600'">
                    {{ form.results_published ? 'Published' : 'Hidden' }}
                </p>
                <p class="text-xs font-semibold text-slate-500 mt-0.5 uppercase tracking-wider text-[10px]">Public Portal Results</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left 2-cols: Event Configuration Form & Details -->
            <div class="lg:col-span-2 space-y-6">
                <form @submit.prevent="saveEvent" class="card space-y-6">
                    <div>
                        <div class="flex items-center justify-between">
                            <h3 class="section-title !mb-0">Event Settings &amp; Configuration</h3>
                            <span class="status-pill text-xs font-mono font-bold uppercase tracking-wider" :class="statusClass(form.status)">
                                {{ form.status }}
                            </span>
                        </div>
                        <p class="section-desc mt-1">Configure event phase, schedule dates, location, and rules.</p>
                    </div>

                    <!-- Core Info Block -->
                    <div class="space-y-4 pt-2 border-t border-slate-100">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">1. Basic Info &amp; Lifecycle Phase</h4>
                        <FormGrid>
                            <FormField label="Event Title" class-extra="sm:col-span-2" required>
                                <input v-model="form.title" class="field" :disabled="!!event.state_program_id" required>
                            </FormField>
                            <FormField label="Lifecycle Phase Status" class-extra="sm:col-span-2">
                                <select v-model="form.status" class="field font-medium">
                                    <option value="draft">Draft (setup — Sahodaya only)</option>
                                    <option v-if="!isSports" value="published">Published</option>
                                    <option v-if="isSports" value="published">Published (Sahodaya announce only)</option>
                                    <option value="registration_open">Registration open</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </FormField>
                        </FormGrid>

                        <div v-if="isSports" class="rounded-xl border border-sky-100 bg-sky-50/70 p-3.5 text-xs text-sky-950 space-y-1">
                            <p class="font-bold text-sky-900 flex items-center gap-1.5">
                                <span>💡</span> Sports Visibility Rule
                            </p>
                            <p class="text-sky-800 leading-relaxed">
                                Schools can only view &amp; register athletes for this sport when status is set to <strong>Registration open</strong>.
                                Releasing medals/rankings is controlled separately by the <strong>Results published</strong> setting below.
                            </p>
                        </div>

                        <FormField label="Academic Year Scope" hint="Only students enrolled in this academic year can register.">
                            <select v-model="form.academic_year_id" class="field">
                                <option :value="null">— Not scoped (All years) —</option>
                                <option v-for="ay in academicYearOptions" :key="ay.id" :value="ay.id">
                                    {{ ay.label }} ({{ ay.status }})
                                </option>
                            </select>
                        </FormField>
                    </div>

                    <!-- Schedule & Dates Section -->
                    <div class="border-t border-slate-100 pt-5 space-y-4">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">2. Event Schedule &amp; Deadlines</h4>
                        <FormGrid>
                            <FormField label="Fest Start Date">
                                <input v-model="form.event_start" type="date" class="field">
                            </FormField>
                            <FormField label="Fest End Date">
                                <input v-model="form.event_end" type="date" class="field">
                            </FormField>
                            <FormField label="Registration Opens Date">
                                <input v-model="form.registration_open" type="date" class="field">
                            </FormField>
                            <FormField label="Registration Closes Date">
                                <input v-model="form.registration_close" type="date" class="field">
                            </FormField>
                            <FormField label="Venue Location" class-extra="sm:col-span-2">
                                <input v-model="form.venue" class="field" placeholder="e.g. District Stadium, Malappuram">
                            </FormField>
                        </FormGrid>
                    </div>

                    <!-- Public Portal & Rules Section -->
                    <div class="border-t border-slate-100 pt-5 space-y-4">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">3. Public Portal &amp; Eligibility Rules</h4>
                        <FormField label="Public Results Visibility" class-extra="sm:col-span-2">
                            <CheckboxField v-model="form.results_published" label="Publish results, scores &amp; rankings on public portal" />
                        </FormField>

                        <div v-if="isSports" class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-2">
                            <p class="form-label font-bold text-slate-800">Sports Age Cutoff Rule</p>
                            <p class="text-xs text-slate-600 leading-relaxed">{{ ageRuleSummary }}</p>
                            <div class="flex flex-wrap gap-3 text-xs pt-1">
                                <Link :href="`${base}/settings/eligibility`" class="link-brand font-semibold">Age reference date →</Link>
                                <a :href="sportsAgeGroupsUrl" class="link-brand font-semibold">Age categories master →</a>
                            </div>
                        </div>

                        <div v-if="!event.state_program_id && event.event_type !== 'sports'" class="space-y-2">
                            <p class="form-label font-bold text-slate-800">Conduct Levels</p>
                            <div class="flex flex-wrap gap-3">
                                <label v-for="(label, key) in selectableLevelLabels" :key="key" class="choice-chip">
                                    <input type="checkbox" class="choice-chip-input" :value="key" v-model="form.conduct_levels">
                                    <span class="choice-chip-label">{{ label }}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <p v-if="form.hasErrors" class="text-sm text-red-600 font-medium">
                        {{ Object.values(form.errors).flat().join(' ') }}
                    </p>
                    <div class="flex justify-end pt-3 border-t border-slate-100">
                        <button type="submit" class="btn-primary flex items-center gap-1.5" :disabled="form.processing">
                            <span>{{ form.processing ? 'Saving...' : 'Save event settings' }}</span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right Column: Single Clean Progress Tracker Card -->
            <aside>
                <EventLifecyclePanel :sahodaya-id="sahodaya.id" :event-id="event.id"
                                     :event-type="event.event_type" :current-status="event.status"
                                     :lifecycle="lifecycle" :suggested-status="suggestedStatus" />
            </aside>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, useForm, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import EventLifecyclePanel from '@/Components/sahodaya/EventLifecyclePanel.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import FormGrid from '@/Components/ui/FormGrid.vue';
import FormField from '@/Components/ui/FormField.vue';
import CheckboxField from '@/Components/ui/CheckboxField.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, levelLabels: Object, feeSchedule: Object,
    stats: Object, activityLogs: { type: Array, default: () => [] },
    lifecycle: { type: Array, default: () => [] },
    suggestedStatus: { type: String, default: null },
    ageRuleSummary: { type: String, default: null },
    academicYearOptions: { type: Array, default: () => [] },
    sportsAgeGroupsUrl: { type: String, default: '' },
    eventHeadNav: { type: Object, default: () => ({ headItemGroups: [] }) },
    mistakenSeasonIssue: { type: Object, default: null },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;

const fixingSeason = ref(false);
function fixMistakenSeason() {
    fixingSeason.value = true;
    router.post(`${base}/fix-mistaken-season`, {
        delete_empty_children: !!props.mistakenSeasonIssue?.emptyChildren,
    }, {
        preserveScroll: true,
        onFinish: () => { fixingSeason.value = false; },
    });
}
const isSports = computed(() => props.event.event_type === 'sports');
const statusHint = computed(() => (isSports.value
    ? 'Setup → Registration open (schools see event) → Ongoing → Complete. Publish results separately.'
    : 'Draft → Published → Registration open → Ongoing → Completed.'));

const eventTypesLabel = computed(() => props.event.event_type?.replace(/_/g, ' ') ?? 'Event');
const publicFestUrl = computed(() => {
    const root = (props.publicUrl ?? '').replace(/\/$/, '');
    return root ? `${root}/fest/${props.event.id}` : `/fest/${props.event.id}`;
});

const selectableLevelLabels = computed(() => {
    const keys = isSports.value ? ['school', 'sahodaya'] : Object.keys(props.levelLabels ?? {});
    return Object.fromEntries(keys.map((k) => [k, props.levelLabels[k]]));
});

const workflowSteps = computed(() => [
    { num: 1, status: 'draft', label: 'Setup & Config' },
    { num: 2, status: 'published', label: 'Published' },
    { num: 3, status: 'registration_open', label: 'Registration Open' },
    { num: 4, status: 'ongoing', label: 'Ongoing Event' },
]);

function statusClass(status) {
    return {
        draft: 'bg-slate-100 text-slate-700 border-slate-200',
        published: 'bg-indigo-100 text-indigo-800 border-indigo-200',
        registration_open: 'bg-emerald-100 text-emerald-800 border-emerald-200',
        ongoing: 'bg-amber-100 text-amber-900 border-amber-200',
        completed: 'bg-violet-100 text-violet-800 border-violet-200',
    }[status] ?? 'bg-slate-100 text-slate-700 border-slate-200';
}

const form = useForm({
    title: props.event.title,
    event_type: props.event.event_type,
    status: props.event.status,
    results_published: props.event.results_published,
    academic_year_id: props.event.academic_year_id ?? null,
    event_start: props.event.event_start?.slice?.(0, 10) ?? props.event.event_start ?? '',
    event_end: props.event.event_end?.slice?.(0, 10) ?? props.event.event_end ?? '',
    registration_open: props.event.registration_open?.slice?.(0, 10) ?? props.event.registration_open ?? '',
    registration_close: props.event.registration_close?.slice?.(0, 10) ?? props.event.registration_close ?? '',
    venue: props.event.venue ?? '',
    conduct_levels: [...(props.event.conduct_levels ?? ['sahodaya'])],
});

function saveEvent() {
    form.put(base, { preserveScroll: true });
}
</script>
