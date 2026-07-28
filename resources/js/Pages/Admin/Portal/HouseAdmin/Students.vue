<template>
    <PortalLayout
        role-label="House Admin Portal"
        :title="house?.name ? `${house.name} — Students` : 'Students'"
        accent="emerald"
        :nav-items="navItems"
    >
        <div class="mb-4 flex flex-wrap items-end gap-3">
            <div class="min-w-[220px] flex-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
                <input v-model="filterForm.search" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Search by name or reg no…">
            </div>
            <button v-if="filterForm.search" type="button" class="btn-secondary text-sm" @click="clearSearch">Clear</button>
        </div>

        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Name</th>
                        <th class="p-3">Class</th>
                        <th class="p-3">Reg no</th>
                        <th class="p-3">Fest entries</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in students.data" :key="s.id" class="border-t">
                        <td class="p-3 font-medium">{{ s.name }}</td>
                        <td class="p-3 text-gray-600">{{ s.school_class?.name }}</td>
                        <td class="p-3 text-gray-500">{{ s.reg_no }}</td>
                        <td class="p-3">{{ s.fest_entries }}</td>
                    </tr>
                    <tr v-if="!students.data?.length">
                        <td colspan="4" class="p-8 text-center text-gray-400">
                            <p>No students in this house.</p>
                            <a :href="`/portal/house-admin/${tenantId}`" class="text-xs text-emerald-700 font-semibold mt-2 inline-block">← Dashboard</a>
                        </td>
                    </tr>
                </tbody>
            </table>
            <PaginationLinks :links="students.links" :meta="{ from: students.from, to: students.to, total: students.total }" />
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import PaginationLinks from '@/Components/ui/PaginationLinks.vue';
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { houseAdminPortalNavItems } from '@/support/houseAdminPortalNav.js';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';

const props = defineProps({
    tenantId: String,
    house: Object,
    students: Object,
    filters: { type: Object, default: () => ({}) },
});

const filterForm = reactive({
    search: props.filters?.search ?? '',
});

function applyFilters() {
    router.get(`/portal/house-admin/${props.tenantId}/students`, {
        search: filterForm.search || undefined,
    }, { preserveState: true, replace: true });
}

useDebouncedInertiaFilters(filterForm, applyFilters, () => props.filters);

function clearSearch() {
    filterForm.search = '';
    applyFilters();
}

const navItems = computed(() => houseAdminPortalNavItems(props.tenantId));
</script>
