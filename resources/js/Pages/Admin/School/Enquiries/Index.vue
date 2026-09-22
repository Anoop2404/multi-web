<template>
    <SchoolAdminLayout title="Admission Enquiries" :school="school" :show-header-title="false">
        <PageHeader title="Admission Enquiries" eyebrow="Website"
            description="Review admission enquiries, find parent contact details and track every follow-up status." />


        <div class="space-y-4">
            <!-- Status counts -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <button v-for="status in statusKeys" :key="status" type="button"
                     class="min-h-20 rounded-xl border border-gray-100 bg-white p-3 text-center shadow-sm transition hover:border-sky-200 hover:shadow"
                     :class="filterForm.status === status ? 'ring-2 ring-[#0f3d7a] border-[#0f3d7a]/30' : ''"
                     :aria-pressed="filterForm.status === status"
                     @click="toggleStatus(status)">
                    <p class="text-2xl font-bold text-gray-800">{{ counts[status] ?? 0 }}</p>
                    <p class="text-xs text-gray-500 capitalize mt-0.5">{{ status }}</p>
                </button>
            </div>

            <SahodayaDataTable
                label="Admission enquiries"
                :columns="columns"
                :links="enquiries.links"
                :meta="enquiries"
                :has-rows="enquiries.data.length > 0"
                empty="No enquiries found"
                empty-description="Try clearing the search or selecting another status."
                empty-icon="📥"
                min-width-class="min-w-[58rem]"
            >
                <template #toolbar>
                    <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="applyFilters">
                        <label class="form-label min-w-0 flex-1">Search
                            <input v-model="filterForm.search" type="search" class="field mt-1" placeholder="Student, parent, phone, email or class">
                        </label>
                        <div class="flex gap-2">
                            <button type="submit" class="btn-primary text-sm">Search</button>
                            <button v-if="hasFilters" type="button" class="btn-ghost text-sm" @click="clearFilters">Clear</button>
                        </div>
                    </form>
                </template>

                <template v-for="enq in enquiries.data" :key="enq.id">
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ enq.student_name }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ enq.class_applying }}</td>
                        <td class="px-4 py-3 text-gray-500">
                            <span class="block">{{ enq.parent_name }}</span>
                            <span class="text-xs text-gray-400">{{ enq.phone }}</span>
                        </td>
                        <td class="px-4 py-3 min-w-40">
                            <SearchableSelect :model-value="enq.status"
                                @update:model-value="(status) => updateStatus(enq, status)"
                                :options="statuses"
                                :all-option="false"
                                :searchable="false"
                                escape-overflow
                                :class="statusClass(enq.status)" />
                        </td>
                        <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                            {{ new Date(enq.created_at).toLocaleDateString('en-IN') }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" class="btn-ghost whitespace-nowrap text-xs" :aria-expanded="expand === enq.id" @click="expand = expand === enq.id ? null : enq.id">
                                {{ expand === enq.id ? 'Hide details' : 'View details' }}
                            </button>
                        </td>
                    </tr>
                    <tr v-if="expand === enq.id">
                        <td colspan="6" class="bg-blue-50 px-4 py-4 text-sm text-gray-600">
                            <div class="grid sm:grid-cols-3 gap-3">
                                <div><span class="font-semibold">DOB:</span> {{ enq.dob || '—' }}</div>
                                <div><span class="font-semibold">Email:</span> {{ enq.email || '—' }}</div>
                                <div><span class="font-semibold">Year:</span> {{ enq.academic_year || '—' }}</div>
                            </div>
                            <div v-if="enq.address" class="mt-2"><span class="font-semibold">Address:</span> {{ enq.address }}</div>
                            <div v-if="enq.message" class="mt-2"><span class="font-semibold">Message:</span> {{ enq.message }}</div>
                        </td>
                    </tr>
                </template>
            </SahodayaDataTable>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import SahodayaDataTable from '@/Components/SahodayaDataTable.vue';
import { ref, computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    school:    Object,
    enquiries: Object,
    counts:    { type: Object, default: () => ({}) },
    filters:   { type: Object, default: () => ({}) },
});

const expand = ref(null);
const statuses = ['new', 'reviewed', 'shortlisted', 'rejected'];
const statusKeys = statuses;
const columns = [
    { key: 'student', label: 'Student' },
    { key: 'class', label: 'Class' },
    { key: 'parent', label: 'Parent / Phone' },
    { key: 'status', label: 'Status' },
    { key: 'received', label: 'Received' },
    { key: 'actions', label: 'Actions', align: 'right' },
];
const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});
const hasFilters = computed(() => Boolean(filterForm.search || filterForm.status));
const base = `/school-admin/${props.school.id}/enquiries`;

function applyFilters() {
    router.get(base, {
        search: filterForm.search || undefined,
        status: filterForm.status || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function toggleStatus(status) {
    filterForm.status = filterForm.status === status ? '' : status;
    applyFilters();
}

function clearFilters() {
    filterForm.search = '';
    filterForm.status = '';
    applyFilters();
}

function statusClass(status) {
    return {
        new:        'text-blue-600',
        reviewed:   'text-amber-600',
        shortlisted:'text-green-600',
        rejected:   'text-red-500',
    }[status] ?? '';
}

function updateStatus(enq, status) {
    router.patch(`/school-admin/${props.school.id}/enquiries/${enq.id}`, { status }, { preserveScroll: true });
}
</script>
