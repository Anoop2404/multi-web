<template>
    <SahodayaEventsLayout title="All events" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader
            title="All events"
            eyebrow="Programs"
            description="Browse every fest program or create custom and teacher events."
        />

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ stats.events }}</p>
                <p class="text-xs text-slate-500 mt-1">Total events</p>
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
                <p class="text-xl font-bold">{{ stats.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Event items</p>
            </div>
        </div>

        <div class="space-y-6">
            <div class="hub-grid">
                <HubCard
                    v-for="program in programs"
                    :key="program.slug"
                    :href="sahodayaProgramHref(sahodaya.id, program.slug)"
                    :icon="programIcons[program.slug]"
                    :label="program.label"
                    :hint="program.description"
                />
            </div>

            <form @submit.prevent="createEvent" class="card space-y-4">
                <div>
                    <h3 class="section-title">Create custom event</h3>
                    <p class="section-desc mt-1">For teacher fest or other one-off cluster events.</p>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Event title" :error="form.errors.title" required>
                        <template #default="{ id }">
                            <input :id="id" v-model="form.title" class="field" placeholder="Event title" required>
                        </template>
                    </FormField>
                    <FormField label="Event type" :error="form.errors.event_type">
                        <template #default="{ id }">
                            <select :id="id" v-model="form.event_type" class="field">
                                <option v-for="(label, key) in customEventTypes" :key="key" :value="key">{{ label }}</option>
                            </select>
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
                    <div class="flex flex-wrap gap-4">
                        <label v-for="(label, key) in levelLabels" :key="key" class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" :value="key" v-model="form.conduct_levels">
                            {{ label }}
                        </label>
                    </div>
                    <InputError :message="form.errors.conduct_levels" class="mt-2" />
                </div>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Creating…' : 'Create event' }}
                </button>
            </form>

        <div class="flex flex-wrap gap-3 items-center mb-4">
            <input v-model="eventSearch" type="search" class="field flex-1 min-w-[12rem] max-w-md"
                   placeholder="Search events…" autocomplete="off">
            <button v-if="eventSearch.trim()" type="button" class="btn-secondary text-sm" @click="eventSearch = ''">Clear</button>
        </div>

            <div class="card overflow-hidden p-0">
                <EmptyState
                    v-if="!events.length"
                    title="No events yet"
                    description="Open a program above to create Kalotsav, Sports, or Kids Fest events."
                    icon="🏆"
                />
                <table v-else class="data-table">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Level</th>
                            <th>Status</th>
                            <th>Sidebar</th>
                            <th>Items</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="event in filteredEvents" :key="event.id">
                            <td class="font-medium text-slate-900">
                                {{ event.title }}
                                <span v-if="event.state_program_id" class="ml-1 text-xs text-amber-700">(state)</span>
                            </td>
                            <td>{{ eventTypes[event.event_type] ?? event.event_type }}</td>
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
                            <td class="text-right">
                                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}`" class="link-brand">
                                    Manage →
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import { PROGRAM_SLUGS, SAHODAYA_PROGRAMS, sahodayaProgramHref } from '@/support/sahodayaPrograms.js';
import { isNavProgramVisible } from '@/support/sahodayaAdminNav.js';

const page = usePage();

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    events: Array,
    eventTypes: Object,
    levelLabels: Object,
    stats: { type: Object, default: () => ({ events: 0, active_events: 0, registrations: 0, items: 0 }) },
});

const eventSearch = ref('');

const filteredEvents = computed(() => {
    const q = eventSearch.value.trim().toLowerCase();
    if (!q) return props.events;
    return props.events.filter((event) =>
        [event.title, event.event_type, event.status, event.level_round].filter(Boolean).join(' ').toLowerCase().includes(q),
    );
});

const programs = computed(() => PROGRAM_SLUGS
    .filter((slug) => isNavProgramVisible(page.props.navVisibility, slug))
    .map((slug) => SAHODAYA_PROGRAMS[slug]));

const programIcons = {
    kalotsav: '🏆',
    'sports-meet': '🏅',
    'kids-fest': '🎈',
};

const customEventTypes = computed(() => {
    const types = props.eventTypes ?? {};
    return Object.fromEntries(
        Object.entries(types).filter(([key]) => !['kalolsavam', 'sports', 'kids_fest'].includes(key)),
    );
});

const form = useForm({
    title: '',
    event_type: 'custom',
    level_round: 'sahodaya',
    conduct_levels: ['sahodaya'],
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
    form.post(`/sahodaya-admin/${props.sahodaya.id}/events`, {
        preserveScroll: true,
        onSuccess: () => form.reset('title'),
    });
}

function toggleNavHidden(event) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${event.id}/toggle-nav-hidden`, {}, { preserveScroll: true });
}
</script>
