<template>
    <SchoolAdminLayout title="Alumni" :school="school" :show-header-title="false">
        <PageHeader title="Alumni" eyebrow="Website"
            description="Review alumni submissions and control which profiles are approved or featured publicly." />


        <div class="space-y-4">
            <!-- Filter tabs -->
            <div class="flex flex-wrap gap-2">
                <button v-for="tab in tabs" :key="tab.value"
                        type="button"
                        @click="selectStatus(tab.value)"
                        :aria-pressed="filterForm.status === tab.value"
                        :class="filterForm.status === tab.value ? 'chip-tab chip-tab--active' : 'chip-tab'">
                    {{ tab.label }} ({{ counts[tab.value] ?? 0 }})
                </button>
            </div>

            <SahodayaDataTable
                label="Alumni submissions"
                :columns="columns"
                :links="alumni.links"
                :meta="alumni"
                :has-rows="alumni.data.length > 0"
                empty="No alumni found"
                empty-description="Try clearing the search or selecting a different status."
                empty-icon="🎓"
                min-width-class="min-w-[52rem]"
            >
                <template #toolbar>
                    <form class="flex flex-col gap-3 sm:flex-row sm:items-end" @submit.prevent="applyFilters">
                        <label class="form-label min-w-0 flex-1">Search
                            <input v-model="filterForm.search" type="search" class="field mt-1" placeholder="Name, email, batch, role or organisation">
                        </label>
                        <div class="flex gap-2">
                            <button type="submit" class="btn-primary text-sm">Search</button>
                            <button v-if="hasFilters" type="button" class="btn-ghost text-sm" @click="clearFilters">Clear</button>
                        </div>
                    </form>
                </template>

                        <tr v-for="a in alumni.data" :key="a.id" class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <img v-if="a.photo_url" :src="a.photo_url" :alt="a.name" class="w-9 h-9 rounded-full object-cover border border-gray-100 shrink-0">
                                    <div class="w-9 h-9 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold text-sm shrink-0" v-else>
                                        {{ a.name[0] }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-800">{{ a.name }}</p>
                                        <p v-if="a.email" class="text-xs text-gray-400">{{ a.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-gray-500">{{ a.batch_year }}</td>
                            <td class="px-5 py-3 text-gray-500 text-xs">
                                <span v-if="a.current_role">{{ a.current_role }}</span>
                                <span v-if="a.current_organisation" class="block text-gray-400">{{ a.current_organisation }}</span>
                            </td>
                            <td class="px-5 py-3">
                                <div class="flex flex-col gap-1">
                                    <span :class="a.is_approved ? 'text-green-600' : 'text-amber-500'"
                                          class="text-xs font-medium">
                                        {{ a.is_approved ? '✓ Approved' : '⏳ Pending' }}
                                    </span>
                                    <span v-if="a.is_featured" class="text-xs text-purple-600 font-medium">★ Featured</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <button type="button" @click="approve(a)"
                                            :class="a.is_approved ? 'text-amber-500' : 'text-green-600'"
                                            class="text-xs hover:underline">
                                        {{ a.is_approved ? 'Hide' : 'Approve' }}
                                    </button>
                                    <button type="button" @click="feature(a)"
                                            class="text-xs text-purple-500 hover:underline">
                                        {{ a.is_featured ? 'Unfeature' : 'Feature' }}
                                    </button>
                                    <button type="button" @click="remove(a)" class="text-xs text-red-500 hover:underline">Delete</button>
                                </div>
                            </td>
                        </tr>
            </SahodayaDataTable>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { computed, reactive } from 'vue';
import { router } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';
import SahodayaDataTable from '@/Components/SahodayaDataTable.vue';
const { confirm } = useConfirm();

const props = defineProps({
    school: Object,
    alumni: Object,
    counts: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const tabs = [
    { value: 'all',      label: 'All' },
    { value: 'pending',  label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'featured', label: 'Featured' },
];

const columns = [
    { key: 'alumni', label: 'Alumni' },
    { key: 'batch', label: 'Batch' },
    { key: 'role', label: 'Current role' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: 'Actions', align: 'right' },
];
const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status || 'all',
});
const hasFilters = computed(() => Boolean(filterForm.search || (filterForm.status && filterForm.status !== 'all')));
const base = `/school-admin/${props.school.id}/alumni`;

function applyFilters() {
    router.get(base, {
        search: filterForm.search || undefined,
        status: filterForm.status === 'all' ? undefined : filterForm.status,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function selectStatus(status) {
    filterForm.status = status;
    applyFilters();
}

function clearFilters() {
    filterForm.search = '';
    filterForm.status = 'all';
    applyFilters();
}

function approve(a) {
    router.patch(`/school-admin/${props.school.id}/alumni/${a.id}/approve`, {}, { preserveScroll: true });
}

function feature(a) {
    router.patch(`/school-admin/${props.school.id}/alumni/${a.id}/feature`, {}, { preserveScroll: true });
}

async function remove(a) {
    if (!(await confirm({ message: `Remove alumni "${a.name}"?`, destructive: true }))) return;
    router.delete(`/school-admin/${props.school.id}/alumni/${a.id}`);
}
</script>
