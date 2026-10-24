<template>
    <SchoolAdminLayout title="School events" :school="school" :show-header-title="false">
        <PageHeader title="School events" eyebrow="Your school"
            description="Create and manage events hosted by your school." >
            <template #actions>
                <Link :href="sahodayaEventsUrl" class="btn-secondary text-sm">Sahodaya events →</Link>
            </template>
        </PageHeader>

        <div class="mb-6 flex flex-wrap gap-2" aria-label="Filter events by type">
            <button v-for="type in typeFilters" :key="type.value" type="button"
                class="rounded-full border px-4 py-2 text-sm font-medium transition focus-visible:outline focus-visible:outline-2 focus-visible:outline-indigo-500"
                :class="activeType === type.value ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-400'"
                :aria-pressed="activeType === type.value" @click="selectType(type.value)">
                {{ type.label }} <span class="ml-1 text-xs opacity-70">{{ countForType(type.value) }}</span>
            </button>
        </div>

        <div class="grid items-start gap-6 xl:grid-cols-[minmax(280px,360px)_minmax(0,1fr)]">
            <section class="card !p-0 overflow-hidden">
                <div class="border-b border-slate-100 bg-slate-50/80 p-5">
                    <span class="mb-3 inline-flex h-10 w-10 items-center justify-center rounded-xl border border-indigo-100 bg-indigo-50 text-xl" aria-hidden="true">＋</span>
                    <h2 class="text-lg font-bold text-slate-900">Create a school event</h2>
                    <p class="mt-1 text-sm text-slate-500">Start with a title and event type.</p>
                </div>
                <form class="space-y-5 p-5" @submit.prevent="createEvent">
                    <div>
                        <label for="school-event-title" class="mb-2 block text-sm font-semibold text-slate-700">Event title <span aria-hidden="true" class="text-red-600">*</span></label>
                        <input id="school-event-title" v-model="form.title" class="field w-full" :placeholder="form.event_type === 'sports' ? 'e.g. Annual Sports Day 2026' : 'e.g. School Arts Festival 2026'"
                            maxlength="255" required :aria-invalid="!!form.errors.title" aria-describedby="event-title-error">
                        <p v-if="form.errors.title" id="event-title-error" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                    </div>
                    <div>
                        <label for="school-event-type" class="mb-2 block text-sm font-semibold text-slate-700">Event type <span aria-hidden="true" class="text-red-600">*</span></label>
                        <SearchableSelect id="school-event-type" v-model="form.event_type" :options="eventTypeOptions" :all-option="false" :required="true" placeholder="Choose an event type" />
                        <p v-if="form.errors.event_type" class="mt-1 text-xs text-red-600">{{ form.errors.event_type }}</p>
                    </div>
                    <div class="rounded-xl bg-indigo-50 p-3 text-xs leading-relaxed text-indigo-900">
                        {{ form.event_type === 'sports' ? 'Register athletes, record marks and rankings, then submit winners to the Sahodaya meet.' : 'Add your items, set participation rules and register students in the event workspace.' }}
                    </div>
                    <div class="border-t border-slate-100 pt-4">
                        <button class="btn-primary w-full justify-center" :disabled="form.processing || !form.title.trim() || !form.event_type">{{ form.processing ? 'Creating event…' : 'Create event →' }}</button>
                        <p class="mt-3 text-center text-xs leading-relaxed text-slate-500">Saved as a draft. Add items and dates before opening registration.</p>
                    </div>
                </form>
            </section>

            <section class="min-w-0">
                <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900">{{ activeType ? eventTypes[activeType] : 'All school events' }}</h2>
                        <p class="mt-1 text-sm text-slate-500">{{ filteredEvents.length }} of {{ scopedEvents.length }} {{ scopedEvents.length === 1 ? 'event' : 'events' }} · hosted by your school</p>
                    </div>
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">{{ openCount }} registration open</span>
                </div>
                <div class="mb-4 flex flex-wrap gap-3">
                    <div class="min-w-[180px] flex-1">
                        <label for="school-event-search" class="sr-only">Search school events</label>
                        <input id="school-event-search" v-model="search" type="search" class="field w-full !bg-white" placeholder="Search event titles…">
                    </div>
                    <div class="min-w-[150px]">
                        <label for="school-event-status" class="sr-only">Filter by status</label>
                        <select id="school-event-status" v-model="status" class="field w-full !bg-white">
                            <option value="">All statuses</option>
                            <option v-for="value in statuses" :key="value" :value="value">{{ statusLabel(value) }}</option>
                        </select>
                    </div>
                </div>

                <div v-if="!filteredEvents.length" class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-14 text-center">
                    <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-50 text-2xl" aria-hidden="true">{{ activeType === 'sports' ? '🏅' : '📅' }}</span>
                    <h3 class="font-bold text-slate-900">{{ scopedEvents.length ? 'No matching events' : activeType === 'sports' ? 'Your first sports event starts here' : 'Create your first school event' }}</h3>
                    <p class="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-slate-500">{{ scopedEvents.length ? 'Try another title or clear the status filter.' : 'Use the form to create a draft. Your events and their registration progress will appear here.' }}</p>
                    <button v-if="search || status" type="button" class="mt-5 text-sm font-semibold text-indigo-700 hover:underline" @click="search = ''; status = ''">Clear filters</button>
                </div>
                <div v-else class="space-y-3">
                    <article v-for="event in filteredEvents" :key="event.id" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-200">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">{{ eventTypes[event.event_type] ?? event.event_type }}</p>
                                <Link :href="eventManageUrl(event)" class="break-words text-base font-bold text-slate-900 hover:text-indigo-700">{{ event.title }}</Link>
                            </div>
                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(event.status)">{{ statusLabel(event.status) }}</span>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-4">
                            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                                <span><strong class="text-slate-700">{{ event.items_count ?? 0 }}</strong> items</span>
                                <span><strong class="text-slate-700">{{ event.registrations_count ?? 0 }}</strong> entries</span>
                                <span v-if="event.event_start">{{ formatDate(event.event_start) }}</span>
                            </div>
                            <Link :href="eventManageUrl(event)" class="text-sm font-semibold text-indigo-700 hover:underline">{{ event.status === 'draft' ? 'Continue setup' : 'Manage event' }} →</Link>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({ school: Object, events: { type: Array, default: () => [] }, eventTypes: { type: Object, default: () => ({}) }, initialType: { type: String, default: '' } });
const activeType = ref(props.initialType);
const search = ref('');
const status = ref('');
const eventTypeOptions = computed(() => Object.entries(props.eventTypes).map(([value, label]) => ({ value, label })));
const typeFilters = computed(() => [{ value: '', label: 'All events' }, ...eventTypeOptions.value]);
const scopedEvents = computed(() => props.events.filter(event => !activeType.value || event.event_type === activeType.value));
const statuses = computed(() => [...new Set(scopedEvents.value.map(event => event.status))]);
const filteredEvents = computed(() => scopedEvents.value.filter(event => (!status.value || event.status === status.value) && event.title.toLowerCase().includes(search.value.trim().toLowerCase())));
const openCount = computed(() => scopedEvents.value.filter(event => event.status === 'registration_open').length);
const sahodayaEventsUrl = computed(() => `/school-admin/${props.school.id}/${({ sports: 'sports', kids_fest: 'kids-fest', english_fest: 'english-fest', science_fest: 'science-fest', teacher_fest: 'teacher-fest', custom: 'custom' })[activeType.value] ?? 'kalotsav'}`);
const form = useForm({ title: '', event_type: props.initialType || 'kalolsavam' });
function countForType(type) { return props.events.filter(event => !type || event.event_type === type).length; }
function selectType(type) {
    activeType.value = type;
    status.value = '';
    if (type) form.event_type = type;
}
function statusLabel(value) { return ({ draft: 'Draft', published: 'Published', registration_open: 'Registration open', ongoing: 'Ongoing', completed: 'Completed', cancelled: 'Cancelled' })[value] ?? value; }
function statusClass(value) {
    if (value === 'registration_open') return 'bg-emerald-50 text-emerald-700';
    if (value === 'ongoing') return 'bg-blue-50 text-blue-700';
    if (value === 'completed') return 'bg-indigo-50 text-indigo-700';
    if (value === 'cancelled') return 'bg-red-50 text-red-700';
    return 'bg-slate-100 text-slate-600';
}
function formatDate(value) { return new Intl.DateTimeFormat('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(value.slice(0, 10) + 'T00:00:00')); }
function eventManageUrl(event) { return event.event_type === 'sports' ? `/school-admin/${props.school.id}/sports/my-event/${event.id}` : `/school-admin/${props.school.id}/fest-programs/${event.id}`; }
function createEvent() {
    form.post(`/school-admin/${props.school.id}/fest-programs`, { preserveScroll: true, onSuccess: () => form.reset('title') });
}
</script>
