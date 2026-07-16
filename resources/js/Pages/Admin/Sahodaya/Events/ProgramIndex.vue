<template>
    <SahodayaEventsLayout :title="program.label" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :program="program"
                         :program-events="sidebarEvents" :show-header-title="false">
        <PageHeader
            :title="program.label"
            eyebrow="Programs"
            :description="program.description"
        />

        <div v-if="isSports" class="rounded-xl border border-sky-100 bg-sky-50 px-4 py-3 mb-6 text-sm text-sky-950">
            <p class="font-semibold">Sports Meet — season &amp; sport events</p>
            <ol class="mt-2 list-decimal pl-4 text-xs text-sky-900/90 space-y-1">
                <li>Configure age groups and catalog on the <strong>season</strong> hub.</li>
                <li>Each sport (Athletics, Chess, …) is its own <strong>event</strong> with fees, items, and schedule.</li>
                <li>Open registration, enter marks, and publish results inside each sport event.</li>
            </ol>
        </div>

        <div v-if="isSports && seasonRemittance"
             class="rounded-xl border px-4 py-3 mb-6 text-sm"
             :class="seasonRemittance.done
                 ? 'border-emerald-200 bg-emerald-50 text-emerald-950'
                 : 'border-amber-200 bg-amber-50 text-amber-950'">
            <p class="font-semibold">{{ seasonRemittance.label }}</p>
            <p class="mt-1 text-xs opacity-90">{{ seasonRemittance.hint }}</p>
            <Link v-if="!seasonRemittance.done"
                  :href="`/sahodaya-admin/${sahodaya.id}/state-remittances`"
                  class="inline-block mt-2 text-xs font-semibold underline">
                Open state remittances →
            </Link>
        </div>

        <div v-if="isSports && seasonEvent"
             class="rounded-xl border border-slate-200 bg-white px-4 py-4 mb-6 space-y-3">
            <div class="flex flex-wrap items-center gap-3 text-sm text-slate-700">
                <span class="font-medium text-slate-500">{{ seasonEvent.title }}</span>
                <span class="status-pill text-xs" :class="statusClass(seasonEvent.status)">{{ seasonEvent.status }}</span>
                <span v-if="seasonEvent.partition_role === 'sports_season'"
                      class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">
                    Season — config only (age groups, cutoff, remittance)
                </span>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${seasonEvent.id}/setup`" class="text-xs font-medium text-slate-500 underline ml-auto">
                    Season settings →
                </Link>
            </div>
            <p class="text-xs text-slate-600">
                Add sports from the season's Setup hub ("+ Add sport"). Open each sport event to load items, set fees, and open registration.
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
                    <p class="text-sm font-semibold text-slate-900">Assign items to a sport</p>
                    <p class="text-xs text-slate-500 mt-1">Pick Chess / Aquatics / … then load items</p>
                </Link>
                <Link :href="sahodayaProgramHref(sahodaya.id, program.slug, 'results')"
                      class="card card--muted !py-4 hover:border-[color:var(--brand-blue)]/30 transition">
                    <p class="text-sm font-semibold text-slate-900">Cluster results</p>
                    <p class="text-xs text-slate-500 mt-1">Published marks across events</p>
                </Link>
                <Link :href="sahodayaProgramHref(sahodaya.id, program.slug, 'rankings')"
                      class="card card--muted !py-4 hover:border-[color:var(--brand-blue)]/30 transition">
                    <p class="text-sm font-semibold text-slate-900">School rankings</p>
                    <p class="text-xs text-slate-500 mt-1">House & school points standings</p>
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
                    <h3 class="section-title">{{ isSports ? 'Create Sports Meet season' : `Create ${program.label} event` }}</h3>
                    <p class="section-desc mt-1">
                        {{ isSports
                            ? 'Creates the season (age groups, cutoff, remittance). Add each sport — Athletics, Chess, … — from its Setup hub afterwards; sports start empty and you load items per sport.'
                            : 'Add a new round or season for this program.' }}
                    </p>
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
                            <th>Sidebar</th>
                            <th>Items</th>
                            <th>Registrations</th>
                            <th></th>
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
                            <td>
                                <button type="button"
                                        class="text-xs font-medium"
                                        :class="event.nav_hidden ? 'text-slate-400' : 'text-emerald-700'"
                                        @click="toggleNavHidden(event)">
                                    {{ event.nav_hidden ? 'Hidden' : 'Visible' }}
                                </button>
                            </td>
                            <td>{{ event.items_count }}</td>
                            <td>{{ event.registrations_count }}</td>
                            <td class="text-right whitespace-nowrap">
                                <Link :href="eventManageUrl(event)" class="link-brand">
                                    Manage →
                                </Link>
                                <button v-if="!event.registrations_count && !event.state_program_id"
                                        type="button"
                                        class="ml-3 text-xs font-medium text-rose-600 hover:text-rose-800"
                                        @click="deleteEvent(event)">
                                    Delete
                                </button>
                            </td>
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
import { Link, router, useForm } from '@inertiajs/vue3';
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
    seasonEvent: { type: Object, default: null },
    seasonRemittance: { type: Object, default: null },
});

const isSports = computed(() => props.program.eventType === 'sports');

function eventManageUrl(event) {
    const base = `/sahodaya-admin/${props.sahodaya.id}/events/${event.id}`;
    if (!isSports.value) return base;
    if (props.seasonEvent?.id && String(event.id) === String(props.seasonEvent.id)) {
        return `${base}/setup`;
    }
    return `${base}?overview=1`;
}

const sidebarEvents = computed(() => (props.events ?? []).filter((ev) => !ev.nav_hidden));

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

function toggleNavHidden(event) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${event.id}/toggle-nav-hidden`, {}, { preserveScroll: true });
}

function deleteEvent(event) {
    if (!window.confirm(`Delete "${event.title}"? This cannot be undone. A sports season deletes its child sport events too. Anything with registrations is blocked — hide it instead.`)) {
        return;
    }
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/events/${event.id}`, { preserveScroll: true });
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
