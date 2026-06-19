<template>
    <SahodayaAdminLayout title="Schools" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :approvedSchoolsCount="approvedSchoolsCount"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
            <div>
                <p class="text-sm text-gray-600">
                    <strong class="text-[#0f3d7a]">{{ verifiedCount }}</strong> verified member schools
                    <span v-if="activeAcademicYear" class="text-gray-400"> · {{ activeAcademicYear }}</span>
                </p>
            </div>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/membership/payments`"
                  class="text-xs font-semibold text-[#0f3d7a] hover:underline">
                Pending approvals → Payments
            </Link>
        </div>

        <SahodayaDataTable :columns="columns"
                           :links="schools.links"
                           :meta="{ from: schools.from, to: schools.to, total: schools.total }"
                           :sort="filters.sort"
                           :dir="filters.dir"
                           :has-rows="!!schools.data?.length"
                           empty="No verified schools found."
                           @sort="toggleSort">
            <template #toolbar>
                <div class="flex flex-wrap items-end gap-3">
                    <div class="flex-1 min-w-[180px] max-w-sm">
                        <input v-model="filterForm.search" type="search" placeholder="Search name or prefix…"
                               @keyup.enter="applyFilters"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#0f3d7a]/20">
                    </div>
                    <input v-model="filterForm.date_from" type="date" title="Registered from"
                           class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    <input v-model="filterForm.date_to" type="date" title="Registered to"
                           class="border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    <button @click="applyFilters"
                            class="bg-[#0f3d7a] hover:bg-[#1a4f8c] text-white px-4 py-2 rounded-lg text-sm font-semibold">
                        Apply
                    </button>
                    <button v-if="hasActiveFilters" @click="clearFilters"
                            class="text-sm text-gray-500 hover:text-gray-700 px-2 py-2">
                        Clear
                    </button>
                    <a :href="exportUrl()"
                       class="ml-auto inline-flex items-center px-4 py-2 rounded-lg bg-[#eff6ff] hover:bg-[#dbeafe] text-[#0f3d7a] border border-[#bfdbfe] text-sm font-semibold transition">
                        Download Excel ↓
                    </a>
                </div>
            </template>

            <tr v-for="school in schools.data" :key="school.id" class="hover:bg-gray-50/80">
                <td class="px-4 py-3 font-medium text-gray-900">{{ school.name }}</td>
                <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ school.school_prefix || '—' }}</td>
                <td class="px-4 py-3 text-xs text-gray-500 font-mono">{{ school.affiliation || '—' }}</td>
                <td class="px-4 py-3 text-xs text-gray-500 truncate max-w-[160px]">{{ school.contact_email || '—' }}</td>
                <td class="px-4 py-3 text-xs text-gray-500">{{ school.contact_phone || '—' }}</td>
                <td class="px-4 py-3 text-right font-semibold text-[#0f3d7a]">{{ school.student_count ?? 0 }}</td>
                <td class="px-4 py-3 text-right text-gray-600">{{ school.classes_count ?? 0 }}</td>
                <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">{{ formatDate(school.created_at) }}</td>
                <td class="px-4 py-3 text-right">
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/schools/${school.id}`"
                          class="text-xs font-semibold text-[#0f3d7a] hover:underline">Details</Link>
                </td>
            </tr>
        </SahodayaDataTable>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import SahodayaDataTable from '@/Components/SahodayaDataTable.vue';
import { Link, router } from '@inertiajs/vue3';
import { reactive, computed } from 'vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String,
    approvedSchoolsCount: Number, pendingSchoolsCount: Number,
    pendingSubmissionsCount: Number, pendingPaymentsCount: Number,
    schools: Object, filters: Object,
    verifiedCount: { type: Number, default: 0 },
    activeAcademicYear: { type: String, default: null },
});

const columns = [
    { key: 'name',          label: 'School',      sortable: true },
    { key: 'school_prefix', label: 'Code',        sortable: true },
    { key: 'affiliation',   label: 'Aff. No.',    sortable: false },
    { key: 'email',         label: 'Email',       sortable: false },
    { key: 'phone',         label: 'Phone',       sortable: false },
    { key: 'students',      label: 'Students',    sortable: false, align: 'right' },
    { key: 'classes',       label: 'Classes',     sortable: false, align: 'right' },
    { key: 'created_at',    label: 'Joined',      sortable: true },
    { key: 'actions',       label: '',            sortable: false, align: 'right' },
];

const filterForm = reactive({
    search:    props.filters?.search ?? '',
    date_from: props.filters?.date_from ?? '',
    date_to:   props.filters?.date_to ?? '',
});

const hasActiveFilters = computed(() =>
    filterForm.search || filterForm.date_from || filterForm.date_to
);

function listParams(overrides = {}) {
    return {
        search:    props.filters?.search ?? '',
        date_from: props.filters?.date_from ?? '',
        date_to:   props.filters?.date_to ?? '',
        sort:      props.filters?.sort ?? 'name',
        dir:       props.filters?.dir ?? 'asc',
        ...overrides,
    };
}

function applyFilters() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools`, listParams({
        search: filterForm.search,
        date_from: filterForm.date_from,
        date_to: filterForm.date_to,
    }), { preserveState: true, replace: true });
}

function clearFilters() {
    filterForm.search = '';
    filterForm.date_from = '';
    filterForm.date_to = '';
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools`, listParams({
        search: '', date_from: '', date_to: '',
    }), { preserveState: true, replace: true });
}

function toggleSort(key) {
    const nextDir = props.filters?.sort === key && props.filters?.dir === 'asc' ? 'desc' : 'asc';
    router.get(`/sahodaya-admin/${props.sahodaya.id}/schools`, listParams({ sort: key, dir: nextDir }), {
        preserveState: true, replace: true,
    });
}

function exportUrl() {
    const params = new URLSearchParams();
    const p = listParams({
        search: filterForm.search,
        date_from: filterForm.date_from,
        date_to: filterForm.date_to,
    });
    Object.entries(p).forEach(([key, value]) => {
        if (value) params.set(key, value);
    });
    const qs = params.toString();
    return `/sahodaya-admin/${props.sahodaya.id}/schools/export${qs ? `?${qs}` : ''}`;
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>
