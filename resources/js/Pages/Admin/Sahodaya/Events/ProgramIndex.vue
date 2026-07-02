<template>
    <SahodayaEventsLayout :title="program.label" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :program="program"
                         :program-events="events" :show-header-title="false">
        <PageHeader
            :title="program.label"
            eyebrow="Programs"
            :description="program.description"
        />

        <div v-if="isSports" class="rounded-xl border border-sky-100 bg-sky-50 px-4 py-3 mb-6 text-sm text-sky-950">
            <p class="font-semibold">Sports Meet workflow</p>
            <p class="mt-1 text-xs text-sky-900/90">
                <strong>1 Setup</strong> age groups & item catalog →
                <strong>2 Create event</strong> from the list below →
                <strong>3 Run day</strong> registrations, marks, results inside the event →
                <strong>4 Records</strong> athletic records & house championship stay at program level.
            </p>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-6 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.events }}</p>
                <p class="text-xs text-slate-500 mt-1">Events</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">{{ stats.active_events }}</p>
                <p class="text-xs text-slate-500 mt-1">Active / open</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.registrations }}</p>
                <p class="text-xs text-slate-500 mt-1">Registrations</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-green-700">₹{{ fmt(stats.fees_collected) }}</p>
                <p class="text-xs text-slate-500 mt-1">Fees collected</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-600">{{ stats.fees_pending }}</p>
                <p class="text-xs text-slate-500 mt-1">Fees pending</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-indigo-700">{{ stats.results_published }}</p>
                <p class="text-xs text-slate-500 mt-1">Results published</p>
            </div>
        </div>

        <!-- ── Quick actions — sports has different shortcuts ── -->
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-8">
            <template v-if="isSports">
                <Link :href="sahodayaProgramHref(sahodaya.id, program.slug, 'age-groups')"
                      class="card card--muted !py-4 hover:border-[color:var(--brand-blue)]/30 transition">
                    <p class="text-sm font-semibold text-slate-900">Age groups</p>
                    <p class="text-xs text-slate-500 mt-1">U8 – U19 · Open · fees</p>
                </Link>
                <Link :href="`${catalogBase}${eventQuery}`"
                      class="card card--muted !py-4 hover:border-[color:var(--brand-blue)]/30 transition">
                    <p class="text-sm font-semibold text-slate-900">Items master</p>
                    <p class="text-xs text-slate-500 mt-1">{{ catalogSummary.enabled }} active items</p>
                </Link>
                <Link :href="`${catalogBase}/assign${eventQuery}`"
                      class="card card--muted !py-4 hover:border-[color:var(--brand-blue)]/30 transition">
                    <p class="text-sm font-semibold text-slate-900">Assign items to event</p>
                    <p class="text-xs text-slate-500 mt-1">Load items into a sports event</p>
                </Link>
            </template>
            <template v-else>
                <Link :href="`${catalogBase}${eventQuery}`"
                      class="card card--muted !py-4 hover:border-[color:var(--brand-blue)]/30 transition">
                    <p class="text-sm font-semibold text-slate-900">Item catalog</p>
                    <p class="text-xs text-slate-500 mt-1">{{ catalogSummary.enabled }} enabled items</p>
                </Link>
                <Link :href="`${catalogBase}/assign${eventQuery}`"
                      class="card card--muted !py-4 hover:border-[color:var(--brand-blue)]/30 transition">
                    <p class="text-sm font-semibold text-slate-900">Assign to event</p>
                    <p class="text-xs text-slate-500 mt-1">Import catalog into a fest</p>
                </Link>
            </template>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events`"
                  class="card card--muted !py-4 hover:border-[color:var(--brand-blue)]/30 transition">
                <p class="text-sm font-semibold text-slate-900">All events</p>
                <p class="text-xs text-slate-500 mt-1">Cross-program directory</p>
            </Link>
            <div class="card card--muted !py-4">
                <p class="text-sm font-semibold text-slate-900">Active now</p>
                <p class="text-xs text-slate-500 mt-1">{{ stats.active_events }} of {{ stats.events }} events open</p>
            </div>
        </div>

        <!-- ── Catalog section — sports shows age-group summary, others show full catalog ── -->
        <section v-if="isSports" class="mb-8">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-3">Sports items</h2>
            <div class="grid sm:grid-cols-2 gap-4">
                <Link :href="`${catalogBase}${eventQuery}`" class="card hover:border-[color:var(--brand-blue)]/40 transition group !py-5">
                    <p class="font-semibold text-slate-900 group-hover:text-[color:var(--brand-blue)]">Items overview</p>
                    <p class="text-sm text-slate-500 mt-1">
                        {{ catalogSummary.enabled }} active items across age groups.
                    </p>
                </Link>
                <Link :href="`${catalogBase}/assign${eventQuery}`" class="card hover:border-[color:var(--brand-blue)]/40 transition group !py-5">
                    <p class="font-semibold text-slate-900 group-hover:text-[color:var(--brand-blue)]">Assign items to event</p>
                    <p class="text-sm text-slate-500 mt-1">Load sports items into a specific sports event.</p>
                </Link>
            </div>
        </section>

        <section v-else class="mb-8">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-3">Item catalog</h2>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <div class="card card--muted !py-4 text-center">
                    <p class="text-xl font-bold">{{ catalogSummary.total }}</p>
                    <p class="text-xs text-slate-500 mt-1">Catalog items</p>
                </div>
                <div class="card card--muted !py-4 text-center">
                    <p class="text-xl font-bold text-emerald-700">{{ catalogSummary.enabled }}</p>
                    <p class="text-xs text-slate-500 mt-1">Enabled</p>
                </div>
                <div class="card card--muted !py-4 text-center">
                    <p class="text-xl font-bold">{{ catalogSummary.cksc }}</p>
                    <p class="text-xs text-slate-500 mt-1">CKSC seed</p>
                </div>
                <div class="card card--muted !py-4 text-center">
                    <p class="text-xl font-bold">{{ catalogSummary.custom }}</p>
                    <p class="text-xs text-slate-500 mt-1">Custom</p>
                </div>
            </div>
            <div class="grid sm:grid-cols-3 gap-4">
                <Link :href="`${catalogBase}${eventQuery}`" class="card hover:border-[color:var(--brand-blue)]/40 transition group !py-5">
                    <p class="font-semibold text-slate-900 group-hover:text-[color:var(--brand-blue)]">Catalog overview</p>
                    <p class="text-sm text-slate-500 mt-1">Browse sections and catalog activity.</p>
                </Link>
                <Link :href="`${catalogBase}/master${eventQuery}`" class="card hover:border-[color:var(--brand-blue)]/40 transition group !py-5">
                    <p class="font-semibold text-slate-900 group-hover:text-[color:var(--brand-blue)]">Master setup</p>
                    <p class="text-sm text-slate-500 mt-1">Enable items, set fees, bulk actions.</p>
                </Link>
                <Link :href="`${catalogBase}/assign${eventQuery}`" class="card hover:border-[color:var(--brand-blue)]/40 transition group !py-5">
                    <p class="font-semibold text-slate-900 group-hover:text-[color:var(--brand-blue)]">Assign to event</p>
                    <p class="text-sm text-slate-500 mt-1">Import enabled items into a fest event.</p>
                </Link>
            </div>
        </section>

        <div v-if="eventsByLevel" class="grid grid-cols-3 gap-3 mb-8">
            <div class="card card--muted text-center !py-4">
                <p class="text-xl font-bold">{{ eventsByLevel.school ?? 0 }}</p>
                <p class="text-xs text-slate-500 mt-1">School rounds</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-xl font-bold text-indigo-700">{{ eventsByLevel.sahodaya ?? 0 }}</p>
                <p class="text-xs text-slate-500 mt-1">Sahodaya rounds</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-xl font-bold text-amber-700">{{ eventsByLevel.state ?? 0 }}</p>
                <p class="text-xs text-slate-500 mt-1">State rounds</p>
            </div>
        </div>

        <section v-if="schoolParticipation?.length" class="card mb-8">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500 mb-3">School participation</h2>
            <div class="flex flex-wrap gap-2">
                <span v-for="s in schoolParticipation" :key="s.id"
                      class="text-xs px-2 py-1 rounded-full border"
                      :class="participationClass(s)">
                    {{ s.name }}
                </span>
            </div>
            <p class="text-xs text-slate-500 mt-3">Green = registered · Blue border = fee paid</p>
        </section>

        <div class="space-y-6">
            <form @submit.prevent="createEvent" class="card space-y-4">
                <div>
                    <h3 class="section-title">Create {{ program.label }} event</h3>
                    <p class="section-desc mt-1">Add a new round or season for this program.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Event title" :error="form.errors.title" class-extra="sm:col-span-2" required>
                        <template #default="{ id }">
                            <input :id="id" v-model="form.title" class="field" placeholder="Event title" required>
                        </template>
                    </FormField>
                    <FormField label="Round" :error="form.errors.level_round" class-extra="sm:col-span-2">
                        <template #default="{ id }">
                            <select :id="id" v-model="form.level_round" class="field">
                                <option value="sahodaya">Sahodaya round (cluster-wide)</option>
                                <option value="school">School round template</option>
                            </select>
                        </template>
                    </FormField>
                </div>
                <div>
                    <p class="form-label mb-2">Future conduct levels</p>
                    <p v-if="program.eventType === 'sports'" class="section-desc mb-3">
                        Sports meets run at school and Sahodaya cluster only — no state round.
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <label v-for="(label, key) in selectableLevelLabels" :key="key" class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" :value="key" v-model="form.conduct_levels">
                            {{ label }}
                        </label>
                    </div>
                    <InputError :message="form.errors.conduct_levels" class="mt-2" />
                </div>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Creating…' : `Create ${program.label} event` }}
                </button>
            </form>

            <div class="card overflow-hidden p-0">
                <div class="border-b border-slate-100 px-5 py-4 bg-slate-50/80">
                    <h3 class="section-title">Events in this program</h3>
                </div>
                <EmptyState
                    v-if="!events.length"
                    :title="`No ${program.label} events yet`"
                    :description="`Create your first ${program.label} event using the form above.`"
                    icon="🏆"
                />
                <table v-else class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Level</th>
                            <th>Status</th>
                            <th>Items</th>
                            <th>Registrations</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="event in events" :key="event.id">
                            <td class="font-medium text-slate-900">
                                {{ event.title }}
                                <span v-if="event.state_program_id" class="ml-1 text-xs text-amber-700">(state)</span>
                            </td>
                            <td class="text-xs">{{ levelLabels[event.level_round] ?? event.level_round }}</td>
                            <td>
                                <span class="status-pill" :class="statusClass(event.status)">{{ event.status }}</span>
                            </td>
                            <td>{{ event.items_count }}</td>
                            <td>{{ event.registrations_count }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

import { sahodayaProgramHref } from '@/support/sahodayaPrograms.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    events: Array,
    levelLabels: Object,
    stats: { type: Object, default: () => ({ events: 0, active_events: 0, registrations: 0, items: 0, fees_collected: 0, fees_pending: 0, results_published: 0 }) },
    catalogSummary: { type: Object, default: () => ({ total: 0, enabled: 0, cksc: 0, custom: 0 }) },
    catalogSections: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    event: { type: Object, default: null },
    schoolParticipation: { type: Array, default: () => [] },
    eventsByLevel: { type: Object, default: null },
});

const isSports = computed(() => props.program.eventType === 'sports');

const eventQuery = computed(() => (props.event?.id ? `?event_id=${props.event.id}` : ''));

const catalogBase = computed(() => sahodayaProgramHref(props.sahodaya.id, props.program.slug, 'catalog'));

const form = useForm({
    title: '',
    event_type: props.program.eventType,
    level_round: 'sahodaya',
    conduct_levels: ['sahodaya'],
});

const selectableLevelLabels = computed(() => {
    const all = props.levelLabels ?? {};
    if (props.program.eventType === 'sports') {
        return Object.fromEntries(Object.entries(all).filter(([k]) => ['school', 'sahodaya'].includes(k)));
    }

    return all;
});

function statusClass(status) {
    return {
        draft: 'status-pill--draft',
        published: 'status-pill--published',
        registration_open: 'status-pill--open',
        ongoing: 'status-pill--ongoing',
        completed: 'status-pill--completed',
    }[status] ?? 'status-pill--draft';
}

function createEvent() {
    form.event_type = props.program.eventType;
    form.post(`/sahodaya-admin/${props.sahodaya.id}/events`, {
        preserveScroll: true,
        onSuccess: () => form.reset('title'),
    });
}

function fmt(v) {
    return Number(v ?? 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });
}

function participationClass(s) {
    if (s.registered && s.fee_paid) return 'bg-green-50 border-green-300 text-green-900';
    if (s.registered) return 'bg-green-50 border-green-200 text-green-800';
    if (s.fee_paid) return 'bg-blue-50 border-blue-200 text-blue-800';
    return 'bg-slate-50 border-slate-200 text-slate-500';
}
</script>
