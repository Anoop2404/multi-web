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
                <div class="border-t border-slate-200 pt-3">
                    <p class="form-label mb-1">Food payments payable to</p>
                    <p class="section-desc mb-2">Most events collect food payments centrally to the Sahodaya. Pick a host school instead if it runs its own catering and should be paid directly.</p>
                    <div class="flex gap-4 text-sm mb-2">
                        <label class="flex items-center gap-2">
                            <input type="radio" value="sahodaya" v-model="form.food_payee_type"> Sahodaya
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" value="host_school" v-model="form.food_payee_type"> A host school
                        </label>
                    </div>
                    <select v-if="form.food_payee_type === 'host_school'" v-model="form.food_host_school_id" class="field">
                        <option value="">— Select school —</option>
                        <option v-for="s in schoolOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                    <InputError :message="form.errors.food_host_school_id" class="mt-2" />
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
                         <template v-for="event in groupedEvents" :key="event.id">
                            <tr class="hover:bg-slate-50/70 font-medium">
                                <td class="font-medium text-slate-900">
                                    <div v-if="event.parentEvent || event.parent" class="text-xs text-indigo-700 font-semibold flex items-center gap-1 mb-0.5">
                                        <span>↳ Sub-Event of {{ (event.parentEvent || event.parent)?.title }}</span>
                                    </div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-bold text-slate-900">{{ event.title }}</span>
                                        <span v-if="event.children && event.children.length" class="text-[10px] uppercase font-extrabold bg-sky-100 text-sky-800 px-2 py-0.5 rounded border border-sky-200">
                                            Main Hub ({{ event.children.length }} Regions)
                                        </span>
                                        <span v-else-if="event.parent_event_id && event.partition_role === 'phase'" class="text-[10px] uppercase font-bold bg-purple-50 text-purple-700 px-1.5 py-0.5 rounded border border-purple-100">
                                            Phase Partition
                                        </span>
                                        <span v-else-if="event.parent_event_id && (event.partition_role === 'school' || event.level_round === 'school')" class="text-[10px] uppercase font-bold bg-emerald-50 text-emerald-700 px-1.5 py-0.5 rounded border border-emerald-100">
                                            School Round
                                        </span>
                                        <span v-else-if="event.parent_event_id || event.parent" class="text-[10px] uppercase font-bold bg-indigo-50 text-indigo-700 px-1.5 py-0.5 rounded border border-indigo-100">
                                            Region Partition
                                        </span>
                                        <span v-else-if="event.conduct_mode === 'partitioned'" class="text-[10px] uppercase font-bold bg-sky-50 text-sky-800 px-1.5 py-0.5 rounded border border-sky-100">
                                            Main Hub (Region-wise)
                                        </span>
                                        <span v-if="event.state_program_id" class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200/80 uppercase tracking-wider">State Linked</span>
                                    </div>
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
                                <td class="text-right whitespace-nowrap">
                                    <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}`" class="link-brand font-bold">
                                        Manage →
                                    </Link>
                                    <button v-if="!event.registrations_count && !event.state_program_id"
                                            type="button"
                                            class="ml-3 text-xs font-semibold text-rose-600 hover:text-rose-800"
                                            @click="deleteEvent(event)">
                                        Delete
                                    </button>
                                </td>
                            </tr>

                            <!-- Child region rows nested under parent -->
                            <tr v-for="(child, idx) in event.children" :key="`child-${child.id}`" class="bg-indigo-50/20 border-l-4 border-indigo-400">
                                <td class="pl-7 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="text-indigo-400 font-mono text-xs font-bold">{{ idx === event.children.length - 1 ? '└─' : '├─' }}</span>
                                        <span class="font-bold text-slate-800 text-xs">{{ child.title }}</span>
                                        <span class="text-[10px] uppercase font-bold bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded border border-indigo-200">
                                            {{ child.partition_role || 'Region' }} Partition
                                        </span>
                                    </div>
                                </td>
                                <td class="text-xs text-slate-500">{{ eventTypes[child.event_type] ?? child.event_type }}</td>
                                <td class="text-xs text-slate-500">{{ levelLabels[child.level_round] ?? child.level_round }}</td>
                                <td><span class="status-pill text-[10px]" :class="statusClass(child.status)">{{ child.status }}</span></td>
                                <td>
                                    <button type="button"
                                            class="text-xs font-medium"
                                            :class="child.nav_hidden ? 'text-slate-400' : 'text-emerald-700'"
                                            @click="toggleNavHidden(child)">
                                        {{ child.nav_hidden ? 'Hidden' : 'Visible' }}
                                    </button>
                                </td>
                                <td class="text-xs text-slate-600">{{ child.items_count ?? 0 }}</td>
                                <td class="text-right whitespace-nowrap">
                                    <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${child.id}`" class="text-xs font-bold text-indigo-700 hover:underline">
                                        Manage Region →
                                    </Link>
                                    <button v-if="!child.registrations_count && !child.state_program_id"
                                            type="button"
                                            class="ml-3 text-xs font-semibold text-rose-600 hover:text-rose-800"
                                            @click="deleteEvent(child)">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        </template>
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
import InputError from '@/Components/ui/InputError.vue';
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
    schoolOptions: { type: Array, default: () => [] },
});

const eventSearch = ref('');

const groupedEvents = computed(() => {
    const q = eventSearch.value.trim().toLowerCase();
    let rows = props.events || [];
    if (q) {
        rows = rows.filter((event) =>
            [event.title, event.event_type, event.status, event.level_round].filter(Boolean).join(' ').toLowerCase().includes(q),
        );
    }

    const hubs = [];
    const childrenByParent = {};

    rows.forEach(ev => {
        if (ev.parent_event_id) {
            childrenByParent[ev.parent_event_id] = childrenByParent[ev.parent_event_id] || [];
            childrenByParent[ev.parent_event_id].push(ev);
        } else {
            hubs.push(ev);
        }
    });

    const result = [];
    hubs.forEach(hub => {
        result.push({
            ...hub,
            children: childrenByParent[hub.id] || []
        });
        delete childrenByParent[hub.id];
    });

    Object.values(childrenByParent).flat().forEach(child => {
        result.push(child);
    });

    return result;
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
    food_payee_type: 'sahodaya',
    food_host_school_id: '',
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

function deleteEvent(event) {
    if (!window.confirm(`Delete "${event.title}"? This cannot be undone. Any sub-regions under it will be deleted too.`)) {
        return;
    }
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/events/${event.id}`, { preserveScroll: true });
}
</script>
