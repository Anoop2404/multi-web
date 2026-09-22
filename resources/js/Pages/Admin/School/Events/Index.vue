<template>
    <SchoolAdminLayout title="Events" :school="school" :show-header-title="false">
        <PageHeader title="Events" eyebrow="Programs"
            description="Search and manage events displayed on the public school calendar.">
            <template #actions>
                <Link :href="`/school-admin/${school.id}/events/create`"
                      class="btn-primary text-sm">
                    + New Event
                </Link>
            </template>
        </PageHeader>

        <SahodayaDataTable
            label="Public school events"
            :columns="columns"
            :links="events.links"
            :meta="events"
            :sort="filters.sort"
            :dir="filters.dir"
            :has-rows="events.data.length > 0"
            empty="No events found"
            empty-description="Try clearing the filters, or add an event to the public calendar."
            empty-icon="📅"
            @sort="toggleSort"
        >
            <template #toolbar>
                <form class="grid gap-3 sm:grid-cols-[minmax(14rem,1fr)_12rem_auto] sm:items-end" @submit.prevent="applyFilters">
                    <label class="form-label">Search
                        <input v-model="filterForm.search" type="search" class="field mt-1" placeholder="Event title or venue">
                    </label>
                    <label class="form-label">Date
                        <SearchableSelect v-model="filterForm.status" class="mt-1" :options="statusOptions" :all-option="true" all-label="All events" :searchable="false" />
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary text-sm">Apply</button>
                        <button v-if="hasFilters" type="button" class="btn-ghost text-sm" @click="clearFilters">Clear</button>
                    </div>
                </form>
            </template>

            <tr v-for="event in events.data" :key="event.id" class="hover:bg-gray-50">
                <td class="px-4 py-3 font-medium text-gray-800 max-w-sm">
                    <Link :href="`/school-admin/${school.id}/events/${event.id}/edit`" class="hover:text-[#0f3d7a] hover:underline">{{ event.title }}</Link>
                </td>
                <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                    {{ new Date(event.start_date).toLocaleDateString('en-IN') }}
                </td>
                <td class="px-4 py-3 text-gray-500">{{ event.venue || '—' }}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <Link :href="`/school-admin/${school.id}/events/${event.id}/edit`" class="btn-ghost text-xs">Edit</Link>
                    <button type="button" @click="destroy(event)" class="btn-ghost text-xs text-red-600">Delete</button>
                </td>
            </tr>

            <template #empty-actions>
                <Link :href="`/school-admin/${school.id}/events/create`" class="btn-primary mt-4 text-sm">Add event</Link>
            </template>
        </SahodayaDataTable>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { useConfirm } from '@/composables/useConfirm';
import SahodayaDataTable from '@/Components/SahodayaDataTable.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
const { confirm } = useConfirm();

const props = defineProps({
    school: Object,
    events: Object,
    filters: { type: Object, default: () => ({}) },
});

const columns = [
    { key: 'title', label: 'Title', sortable: true },
    { key: 'start_date', label: 'Date', sortable: true },
    { key: 'venue', label: 'Venue', sortable: true },
    { key: 'actions', label: 'Actions', align: 'right' },
];
const statusOptions = [
    { value: 'upcoming', label: 'Upcoming' },
    { value: 'past', label: 'Past' },
];
const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});
const hasFilters = computed(() => Boolean(filterForm.search || filterForm.status));
const base = `/school-admin/${props.school.id}/events`;

function visit(extra = {}) {
    router.get(base, {
        search: filterForm.search || undefined,
        status: filterForm.status || undefined,
        sort: extra.sort ?? props.filters.sort ?? 'start_date',
        dir: extra.dir ?? props.filters.dir ?? 'asc',
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function applyFilters() { visit(); }

function clearFilters() {
    filterForm.search = '';
    filterForm.status = '';
    visit();
}

function toggleSort(key) {
    visit({ sort: key, dir: props.filters.sort === key && props.filters.dir === 'asc' ? 'desc' : 'asc' });
}

async function destroy(event) {
    if (!(await confirm({ message: `Delete "${event.title}"?`, destructive: true }))) return;
    router.delete(`/school-admin/${props.school.id}/events/${event.id}`);
}
</script>
