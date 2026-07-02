<template>
    <SahodayaEventsLayout :title="event.title" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="event.title" eyebrow="Event overview"
                    :description="`${eventTypesLabel} · ${levelLabels[event.level_round] ?? event.level_round}`">
            <template #actions>
                <a :href="`/fest/${event.id}`" target="_blank" rel="noopener" class="btn-secondary text-xs">Public portal ↗</a>
            </template>
        </PageHeader>

        <FestEventWorkflowStepper :sahodaya-id="sahodaya.id" :event-id="event.id"
                                  :event-type="event.event_type" :current-step="'setup'" />

        <EventSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="overview" />

        <div v-if="event.state_program_id" class="notice-banner notice-banner--warning mb-4">
            <strong>State program</strong> — propagated from central admin.
        </div>

        <div class="grid sm:grid-cols-3 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Items</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.registrations }}</p>
                <p class="text-xs text-slate-500 mt-1">Registrations</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.school_rounds }}</p>
                <p class="text-xs text-slate-500 mt-1">School rounds</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2">
                <form @submit.prevent="saveEvent" class="form-section">
                    <div class="form-section-head">
                        <h3 class="form-section-title">Event details</h3>
                        <p class="form-section-hint">Title, status, and conduct levels.</p>
                    </div>
                    <div class="form-section-body space-y-4">
                        <FormGrid>
                            <FormField label="Title" class-extra="sm:col-span-2">
                                <input v-model="form.title" class="field" :disabled="!!event.state_program_id" required>
                            </FormField>
                            <FormField label="Status">
                                <select v-model="form.status" class="field">
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                    <option value="registration_open">Registration Open</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="completed">Completed</option>
                                </select>
                            </FormField>
                            <FormField label="Academic year" hint="Only students enrolled in this year can register. Defaults to active year on create.">
                                <select v-model="form.academic_year_id" class="field">
                                    <option :value="null">— Not scoped —</option>
                                    <option v-for="ay in academicYearOptions" :key="ay.id" :value="ay.id">
                                        {{ ay.label }} ({{ ay.status }})
                                    </option>
                                </select>
                            </FormField>
                            <FormField label="Results" class-extra="sm:col-span-2">
                                <CheckboxField v-model="form.results_published" label="Results published on public portal" />
                            </FormField>
                        </FormGrid>
                        <div v-if="isSports" class="rounded-xl border border-slate-200/80 bg-slate-50/80 p-4 space-y-2">
                            <p class="form-label">Sports age cutoff</p>
                            <p class="text-xs text-slate-600">{{ ageRuleSummary }}</p>
                            <div class="flex flex-wrap gap-3 text-xs">
                                <Link :href="`${base}/settings/eligibility`" class="link-brand">Age reference date →</Link>
                                <a :href="sportsAgeGroupsUrl" class="link-brand">Age categories master →</a>
                            </div>
                        </div>
                        <div v-if="!event.state_program_id" class="space-y-2">
                            <p class="form-label">Conduct levels</p>
                            <div class="flex flex-wrap gap-3">
                                <label v-for="(label, key) in selectableLevelLabels" :key="key" class="choice-chip">
                                    <input type="checkbox" class="choice-chip-input" :value="key" v-model="form.conduct_levels">
                                    <span class="choice-chip-label">{{ label }}</span>
                                </label>
                            </div>
                        </div>
                        <p v-if="form.hasErrors" class="text-sm text-red-600">
                            {{ Object.values(form.errors).flat().join(' ') }}
                        </p>
                        <FormActions>
                            <button type="submit" class="btn-primary" :disabled="form.processing">Save event</button>
                        </FormActions>
                    </div>
                </form>
            </div>

            <aside class="space-y-4">
                <EventLifecyclePanel :sahodaya-id="sahodaya.id" :event-id="event.id"
                                     :lifecycle="lifecycle" :suggested-status="suggestedStatus" />
                <div class="card space-y-3">
                    <h4 class="section-title">Fest fees</h4>
                    <p class="section-desc text-xs">Per-event registration fees — not annual membership.</p>
                    <Link :href="`${base}/settings/fees`" class="link-brand text-sm">Configure in Settings → Fees</Link>
                </div>
                <div class="card space-y-2">
                    <h4 class="section-title">Quick links</h4>
                    <Link :href="`${base}/items`" class="block text-sm link-brand">Event items setup</Link>
                    <Link :href="`${base}/settings/participation`" class="block text-sm link-brand">Participation policy</Link>
                    <Link :href="`${base}/registrations`" class="block text-sm link-brand">Registrations</Link>
                    <Link :href="`${base}/leaderboard`" class="block text-sm link-brand">Leaderboard</Link>
                    <Link :href="`${base}/activity`" class="block text-sm link-brand">Full activity log</Link>
                    <Link :href="`${base}/reports`" class="block text-sm link-brand">Reports</Link>
                </div>
            </aside>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import FestEventWorkflowStepper from '@/Components/sahodaya/FestEventWorkflowStepper.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import EventLifecyclePanel from '@/Components/sahodaya/EventLifecyclePanel.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, levelLabels: Object, feeSchedule: Object,
    stats: Object, activityLogs: { type: Array, default: () => [] },
    lifecycle: { type: Array, default: () => [] },
    suggestedStatus: { type: String, default: null },
    ageRuleSummary: { type: String, default: null },
    academicYearOptions: { type: Array, default: () => [] },
    sportsAgeGroupsUrl: { type: String, default: '' },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const isSports = computed(() => props.event.event_type === 'sports');
const eventTypesLabel = computed(() => props.event.event_type?.replace(/_/g, ' ') ?? 'Event');

const selectableLevelLabels = computed(() => {
    const keys = isSports.value ? ['school', 'sahodaya'] : Object.keys(props.levelLabels ?? {});
    return Object.fromEntries(keys.map((k) => [k, props.levelLabels[k]]));
});

const form = useForm({
    title: props.event.title,
    event_type: props.event.event_type,
    status: props.event.status,
    results_published: props.event.results_published,
    academic_year_id: props.event.academic_year_id ?? null,
    conduct_levels: [...(props.event.conduct_levels ?? ['sahodaya'])],
});

function saveEvent() {
    form.put(base, { preserveScroll: true });
}
</script>
