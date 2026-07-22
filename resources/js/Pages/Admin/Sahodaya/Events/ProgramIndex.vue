<template>
    <SahodayaEventsLayout :title="program.label" :sahodaya="sahodaya" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :program="program"
                          :program-events="sidebarEvents" :show-header-title="false">
        <PageHeader
            :title="program.label"
            eyebrow="Programs"
            :description="program.description"
        >
            <template #actions>
                <button type="button" class="btn-primary text-xs flex items-center gap-1.5" @click="showCreateForm = !showCreateForm">
                    <span>{{ showCreateForm ? 'Close form' : '+ Create event' }}</span>
                </button>
            </template>
        </PageHeader>

        <!-- Sports season guidance banner -->
        <div v-if="isSports" class="rounded-xl border border-emerald-200/80 bg-gradient-to-r from-emerald-50/80 to-teal-50/40 p-4 mb-6 text-sm text-emerald-950 shadow-sm">
            <div class="flex items-center gap-2 mb-2">
                <span class="text-base" aria-hidden="true">🏆</span>
                <p class="font-bold text-emerald-900">Sports Meet — Season &amp; Sport Events Workspace</p>
            </div>
            <ol class="grid sm:grid-cols-3 gap-2 mt-2 text-xs text-emerald-900/90 font-medium">
                <li class="flex items-center gap-2 bg-white/60 rounded-lg p-2 border border-emerald-100">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-[10px] font-bold text-white">1</span>
                    <span>Configure age groups &amp; master catalog on <strong>Season</strong>.</span>
                </li>
                <li class="flex items-center gap-2 bg-white/60 rounded-lg p-2 border border-emerald-100">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-[10px] font-bold text-white">2</span>
                    <span>Each sport (Athletics, Chess...) is its own <strong>Event</strong> with fees.</span>
                </li>
                <li class="flex items-center gap-2 bg-white/60 rounded-lg p-2 border border-emerald-100">
                    <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-[10px] font-bold text-white">3</span>
                    <span>Open registration, record marks &amp; publish results per sport.</span>
                </li>
            </ol>
        </div>

        <div v-if="isSports && seasonRemittance"
             class="rounded-xl border px-4 py-3 mb-6 text-sm flex flex-wrap items-center justify-between gap-3"
             :class="seasonRemittance.done
                 ? 'border-emerald-200 bg-emerald-50 text-emerald-950'
                 : 'border-amber-200 bg-amber-50 text-amber-950'">
            <div>
                <p class="font-semibold">{{ seasonRemittance.label }}</p>
                <p class="mt-0.5 text-xs opacity-90">{{ seasonRemittance.hint }}</p>
            </div>
            <Link v-if="!seasonRemittance.done"
                  :href="`/sahodaya-admin/${sahodaya.id}/state-remittances`"
                  class="btn-primary text-xs !min-h-0 !py-1.5">
                Open state remittances →
            </Link>
        </div>

        <!-- Season Hub Card (if exists) -->
        <div v-if="isSports && seasonEvent"
             class="card !p-5 mb-6 bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 text-white shadow-lg space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
                <div class="flex items-center gap-2.5">
                    <span class="text-xl">⚙️</span>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="font-bold text-base text-white leading-tight">{{ seasonEvent.title }}</h3>
                            <span class="status-pill text-[10px] uppercase font-mono tracking-wider" :class="statusClass(seasonEvent.status)">
                                {{ seasonEvent.status }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-300 mt-0.5">Season Container &amp; Central Config Hub</p>
                    </div>
                </div>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${seasonEvent.id}/setup`"
                      class="btn-primary text-xs !bg-indigo-600 hover:!bg-indigo-500 !text-white !border-transparent">
                    Season Settings &amp; Setup →
                </Link>
            </div>
            <p class="text-xs text-slate-300 border-t border-slate-700/60 pt-3">
                Add sport events (Chess, Aquatics, Athletics...) from the season Setup hub. Open each sport event individually to load items, set fees, and manage registration.
            </p>
        </div>

        <!-- Metrics Dashboard -->
        <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 mb-6">
            <div class="card card--muted !py-3.5 text-center transition hover:border-slate-300">
                <p class="text-2xl font-black text-slate-900">{{ stats.events }}</p>
                <p class="text-xs font-semibold text-slate-500 mt-0.5 uppercase tracking-wider text-[10px]">{{ isSports ? 'Sport events' : 'Events' }}</p>
            </div>
            <div class="card card--muted !py-3.5 text-center transition hover:border-emerald-300">
                <p class="text-2xl font-black text-emerald-600">{{ stats.active_events }}</p>
                <p class="text-xs font-semibold text-slate-500 mt-0.5 uppercase tracking-wider text-[10px]">{{ isSports ? 'Active sports' : 'Active / open' }}</p>
            </div>
            <div class="card card--muted !py-3.5 text-center transition hover:border-indigo-300">
                <p class="text-2xl font-black text-indigo-600">{{ stats.registrations }}</p>
                <p class="text-xs font-semibold text-slate-500 mt-0.5 uppercase tracking-wider text-[10px]">{{ isSports ? 'Athletes' : 'Registrations' }}</p>
            </div>
            <div class="card card--muted !py-3.5 text-center transition hover:border-green-300">
                <p class="text-2xl font-black text-emerald-700">₹{{ fmt(stats.fees_collected) }}</p>
                <p class="text-xs font-semibold text-slate-500 mt-0.5 uppercase tracking-wider text-[10px]">Fees collected</p>
            </div>
            <div class="card card--muted !py-3.5 text-center transition hover:border-amber-300">
                <p class="text-2xl font-black text-amber-600">{{ stats.fees_pending }}</p>
                <p class="text-xs font-semibold text-slate-500 mt-0.5 uppercase tracking-wider text-[10px]">Fees pending</p>
            </div>
            <div class="card card--muted !py-3.5 text-center transition hover:border-violet-300">
                <p class="text-2xl font-black text-violet-600">{{ stats.results_published }}</p>
                <p class="text-xs font-semibold text-slate-500 mt-0.5 uppercase tracking-wider text-[10px]">Results published</p>
            </div>
        </div>

        <!-- Operations Toolkit Cards -->
        <section class="mb-8">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3 flex items-center gap-1.5">
                <span>🛠 Shortcuts &amp; Masters</span>
            </h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <template v-if="isSports">
                    <Link :href="sahodayaProgramHref(sahodaya.id, program.slug, 'age-groups')"
                          class="card card--muted !py-3.5 px-4 hover:border-indigo-400 transition group flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-slate-800 group-hover:text-indigo-600 transition">Age Groups Master</p>
                            <p class="text-[11px] text-slate-500 mt-0.5">U8 – U19 · Open · Cutoffs</p>
                        </div>
                        <span class="text-slate-400 group-hover:text-indigo-600 text-sm">→</span>
                    </Link>
                    <Link :href="`${catalogBase}${eventQuery}`"
                          class="card card--muted !py-3.5 px-4 hover:border-indigo-400 transition group flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-slate-800 group-hover:text-indigo-600 transition">Items Catalog Master</p>
                            <p class="text-[11px] text-slate-500 mt-0.5">{{ catalogSummary.enabled }} active items</p>
                        </div>
                        <span class="text-slate-400 group-hover:text-indigo-600 text-sm">→</span>
                    </Link>
                    <Link :href="`${catalogBase}/assign${eventQuery}`"
                          class="card card--muted !py-3.5 px-4 hover:border-indigo-400 transition group flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-slate-800 group-hover:text-indigo-600 transition">Assign Items to Sport</p>
                            <p class="text-[11px] text-slate-500 mt-0.5">Chess / Aquatics / Athletics...</p>
                        </div>
                        <span class="text-slate-400 group-hover:text-indigo-600 text-sm">→</span>
                    </Link>
                    <Link :href="sahodayaProgramHref(sahodaya.id, program.slug, 'results')"
                          class="card card--muted !py-3.5 px-4 hover:border-indigo-400 transition group flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-slate-800 group-hover:text-indigo-600 transition">Cluster Results</p>
                            <p class="text-[11px] text-slate-500 mt-0.5">Published marks &amp; medals</p>
                        </div>
                        <span class="text-slate-400 group-hover:text-indigo-600 text-sm">→</span>
                    </Link>
                    <Link :href="sahodayaProgramHref(sahodaya.id, program.slug, 'rankings')"
                          class="card card--muted !py-3.5 px-4 hover:border-indigo-400 transition group flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-slate-800 group-hover:text-indigo-600 transition">School Rankings</p>
                            <p class="text-[11px] text-slate-500 mt-0.5">House &amp; school points</p>
                        </div>
                        <span class="text-slate-400 group-hover:text-indigo-600 text-sm">→</span>
                    </Link>
                </template>
                <template v-else>
                    <Link :href="`${catalogBase}${eventQuery}`"
                          class="card card--muted !py-3.5 px-4 hover:border-indigo-400 transition group flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-slate-800 group-hover:text-indigo-600 transition">Item Catalog</p>
                            <p class="text-[11px] text-slate-500 mt-0.5">{{ catalogSummary.enabled }} enabled items</p>
                        </div>
                        <span class="text-slate-400 group-hover:text-indigo-600 text-sm">→</span>
                    </Link>
                    <Link :href="`${catalogBase}/assign${eventQuery}`"
                          class="card card--muted !py-3.5 px-4 hover:border-indigo-400 transition group flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-slate-800 group-hover:text-indigo-600 transition">Assign to Event</p>
                            <p class="text-[11px] text-slate-500 mt-0.5">Import catalog into a fest</p>
                        </div>
                        <span class="text-slate-400 group-hover:text-indigo-600 text-sm">→</span>
                    </Link>
                </template>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events`"
                      class="card card--muted !py-3.5 px-4 hover:border-indigo-400 transition group flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-slate-800 group-hover:text-indigo-600 transition">All Events Directory</p>
                        <p class="text-[11px] text-slate-500 mt-0.5">Cross-program directory</p>
                    </div>
                    <span class="text-slate-400 group-hover:text-indigo-600 text-sm">→</span>
                </Link>
            </div>
        </section>

        <!-- Events List & Creation Section -->
        <section class="space-y-6 mb-8">
            <!-- Create Event Collapsible Panel -->
            <div v-if="showCreateForm" class="card border-2 border-indigo-100 bg-gradient-to-b from-indigo-50/50 to-white shadow-md p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="section-title !mb-0">{{ isSports ? 'Create Sports Meet Season' : `Create ${program.label} Event` }}</h3>
                        <p class="section-desc mt-0.5">
                            {{ isSports
                                ? 'Creates the season (age groups, cutoff, remittance). Add each sport (Athletics, Chess...) from its Setup hub afterwards.'
                                : 'Add a new round or season for this program.' }}
                        </p>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-slate-600 text-lg leading-none" @click="showCreateForm = false">×</button>
                </div>
                <form @submit.prevent="createEvent" class="space-y-4 pt-2">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField label="Event title" :error="form.errors.title" class-extra="sm:col-span-2" required>
                            <template #default="{ id }">
                                <input :id="id" v-model="form.title" class="field" placeholder="e.g. Sports Meet 2026-27" required>
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
                        <p class="form-label mb-1.5">Conduct levels</p>
                        <p v-if="program.eventType === 'sports'" class="section-desc mb-2">
                            Sports meets run at school and Sahodaya cluster level.
                        </p>
                        <div class="flex flex-wrap gap-3">
                            <label v-for="(label, key) in selectableLevelLabels" :key="key" class="flex items-center gap-2 text-sm text-slate-700 font-medium">
                                <input type="checkbox" :value="key" v-model="form.conduct_levels" class="rounded border-slate-300">
                                {{ label }}
                            </label>
                        </div>
                        <InputError :message="form.errors.conduct_levels" class="mt-2" />
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" class="btn-secondary text-xs" @click="showCreateForm = false">Cancel</button>
                        <button type="submit" class="btn-primary text-xs" :disabled="form.processing">
                            {{ form.processing ? 'Creating...' : `Create ${program.label} Event` }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Events List Card -->
            <div class="card card--flush overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4 bg-slate-50/80 flex items-center justify-between gap-3">
                    <h3 class="section-title !mb-0">Events in {{ program.label }}</h3>
                    <span class="text-xs text-slate-500 font-medium">{{ events.length }} event{{ events.length === 1 ? '' : 's' }}</span>
                </div>
                <EmptyState
                    v-if="!events.length"
                    :title="`No ${program.label} events yet`"
                    :description="`Create your first ${program.label} event to get started.`"
                    icon="🏆"
                    class="p-8"
                />
                <template v-else>
                    <!-- Sports Grid Layout -->
                    <div v-if="isSports" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 p-5 bg-slate-50/40">
                        <div v-for="ev in events" :key="ev.id" class="card hover:shadow-md transition duration-200 border border-slate-200/80 flex flex-col justify-between !p-4 bg-white">
                            <div>
                                <div class="flex items-start justify-between gap-2">
                                    <h4 class="font-bold text-slate-900 leading-snug text-sm">{{ ev.title }}</h4>
                                    <span class="status-pill text-[10px] uppercase font-mono tracking-wider shrink-0" :class="statusClass(ev.status)">
                                        {{ ev.status }}
                                    </span>
                                </div>
                                
                                <div class="mt-3 space-y-1.5 text-[11px] text-slate-600 bg-slate-50 rounded-lg p-2.5 border border-slate-100">
                                    <p class="flex items-center gap-1.5">
                                        <span aria-hidden="true">📝</span>
                                        <span><strong>Reg:</strong> {{ formatDateRange(ev.registration_open, ev.registration_close) }}</span>
                                    </p>
                                    <p class="flex items-center gap-1.5">
                                        <span aria-hidden="true">🏆</span>
                                        <span><strong>Comp:</strong> {{ formatDateRange(ev.event_start, ev.event_end) }}</span>
                                    </p>
                                    <p v-if="ev.venue" class="flex items-center gap-1.5">
                                        <span aria-hidden="true">📍</span>
                                        <span class="truncate"><strong>Venue:</strong> {{ ev.venue }}</span>
                                    </p>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2 text-[10px] font-semibold text-slate-600">
                                    <span class="bg-slate-100 px-2 py-0.5 rounded-full border border-slate-200">{{ ev.items_count }} items</span>
                                    <span class="bg-indigo-50 text-indigo-700 px-2 py-0.5 rounded-full border border-indigo-100">{{ ev.registrations_count }} registrations</span>
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-t border-slate-100 flex flex-wrap justify-between items-center gap-2">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider border"
                                      :class="ev.has_sports_fees_configured ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-800'">
                                    {{ ev.has_sports_fees_configured ? 'Composite billing active' : 'Fee config pending' }}
                                </span>
                                <div class="flex items-center gap-2">
                                    <button v-if="!ev.state_program_id"
                                            type="button"
                                            class="text-xs font-semibold px-1 py-0.5"
                                            :class="ev.nav_hidden ? 'text-slate-400 hover:text-slate-600' : 'text-emerald-700 hover:text-emerald-900'"
                                            @click="toggleNavHidden(ev)">
                                        {{ ev.nav_hidden ? 'Hidden' : 'Visible' }}
                                    </button>
                                    <button v-if="!ev.registrations_count && !ev.state_program_id"
                                            type="button"
                                            class="text-xs font-semibold text-rose-600 hover:text-rose-800 px-1 py-0.5"
                                            @click="deleteEvent(ev)">
                                        Delete
                                    </button>
                                    <Link :href="eventManageUrl(ev)" class="btn-primary text-xs !min-h-0 !px-3 !py-1">
                                        Manage →
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Flat table for Kalotsav/etc -->
                    <div v-else class="overflow-x-auto">
                        <table class="data-table">
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
                                <tr v-for="ev in events" :key="ev.id">
                                    <td class="font-medium text-slate-900">
                                        {{ ev.title }}
                                        <span v-if="ev.state_program_id" class="ml-1 text-xs text-amber-700 font-semibold">(state)</span>
                                    </td>
                                    <td class="text-xs">{{ levelLabels[ev.level_round] ?? ev.level_round }}</td>
                                    <td>
                                        <span class="status-pill text-[10px]" :class="statusClass(ev.status)">{{ ev.status }}</span>
                                    </td>
                                    <td>
                                        <button type="button"
                                                class="text-xs font-semibold"
                                                :class="ev.nav_hidden ? 'text-slate-400' : 'text-emerald-700'"
                                                @click="toggleNavHidden(ev)">
                                            {{ ev.nav_hidden ? 'Hidden' : 'Visible' }}
                                        </button>
                                    </td>
                                    <td>{{ ev.items_count }}</td>
                                    <td>{{ ev.registrations_count }}</td>
                                    <td class="text-right whitespace-nowrap">
                                        <Link :href="eventManageUrl(ev)" class="btn-secondary text-xs !min-h-0 !px-2.5 !py-1">
                                            Manage →
                                        </Link>
                                        <button v-if="!ev.registrations_count && !ev.state_program_id"
                                                type="button"
                                                class="ml-2 text-xs font-medium text-rose-600 hover:text-rose-800"
                                                @click="deleteEvent(ev)">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </template>
            </div>
        </section>

        <!-- School Participation Summary -->
        <section v-if="schoolParticipation?.length" class="card mb-8">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 !mb-0">School Participation Directory</h2>
                <span class="text-xs text-slate-400">{{ schoolParticipation.length }} schools</span>
            </div>
            <div class="flex flex-wrap gap-2">
                <span v-for="s in schoolParticipation" :key="s.id"
                      class="text-xs px-2.5 py-1 rounded-full border font-medium"
                      :class="participationClass(s)">
                    {{ s.name }}
                </span>
            </div>
            <div class="flex flex-wrap gap-4 text-xs text-slate-500 mt-4 pt-3 border-t border-slate-100">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Registered</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full border-2 border-blue-500 bg-white"></span> Fee Paid</span>
            </div>
        </section>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import FormField from '@/Components/ui/FormField.vue';
import InputError from '@/Components/ui/InputError.vue';

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

const showCreateForm = ref(false);
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
        draft: 'bg-slate-100 text-slate-700 border-slate-200',
        published: 'bg-indigo-100 text-indigo-800 border-indigo-200',
        registration_open: 'bg-emerald-100 text-emerald-800 border-emerald-200',
        ongoing: 'bg-amber-100 text-amber-900 border-amber-200',
        completed: 'bg-violet-100 text-violet-800 border-violet-200',
    }[status] ?? 'bg-slate-100 text-slate-700 border-slate-200';
}

function createEvent() {
    form.event_type = props.program.eventType;
    form.post(`/sahodaya-admin/${props.sahodaya.id}/events`, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('title');
            showCreateForm.value = false;
        },
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
    if (s.registered && s.fee_paid) return 'bg-emerald-50 border-emerald-400 text-emerald-950 font-semibold';
    if (s.registered) return 'bg-emerald-50/80 border-emerald-200 text-emerald-900';
    if (s.fee_paid) return 'bg-blue-50 border-blue-400 text-blue-900';
    return 'bg-slate-50 border-slate-200 text-slate-500';
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
