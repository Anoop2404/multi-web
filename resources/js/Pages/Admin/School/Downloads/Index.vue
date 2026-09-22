<template>
    <SchoolAdminLayout title="Downloads" :school="school" :show-header-title="false">
        <PageHeader title="Downloads" eyebrow="Website"
            description="Upload, search and manage the files visitors can download from your school website." />


        <div class="space-y-6">
            <!-- Upload form -->
            <div class="card">
                <h3 class="font-bold text-gray-800 mb-4">Upload New File</h3>
                <form @submit.prevent="upload" class="space-y-4">
                    <div class="grid sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="form-label mb-1.5">File Title *</label>
                            <input v-model="form.title" type="text" required
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Category *</label>
                            <SearchableSelect v-model="form.category" :options="categories" :all-option="false"
                                    :required="true" placeholder="Select category" />
                        </div>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4 items-end">
                        <div>
                            <label class="form-label mb-1.5">Academic Year</label>
                            <input v-model="form.academic_year" type="text" placeholder="2025-26"
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">File (PDF / DOC / XLS) *</label>
                            <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx" required
                                   @change="form.file = $event.target.files[0]"
                                   class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                        </div>
                    </div>
                    <button type="submit" :disabled="form.processing"
                            class="btn-primary px-6 py-2.5 rounded-lg text-sm font-semibold transition disabled:opacity-50">
                        Upload File
                    </button>
                </form>
            </div>

            <SahodayaDataTable
                label="Website downloads"
                :columns="columns"
                :links="downloads.links"
                :meta="downloads"
                :has-rows="downloads.data.length > 0"
                empty="No files found"
                empty-description="Try clearing the filters, or upload a file using the form above."
                empty-icon="📁"
            >
                <template #toolbar>
                    <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(14rem,1fr)_12rem_11rem_auto] lg:items-end" @submit.prevent="applyFilters">
                        <label class="form-label">Search
                            <input v-model="filterForm.search" type="search" class="field mt-1" placeholder="Title, filename or year">
                        </label>
                        <label class="form-label">Category
                            <SearchableSelect v-model="filterForm.category" class="mt-1" :options="categories" :all-option="true" all-label="All categories" />
                        </label>
                        <label class="form-label">Visibility
                            <SearchableSelect v-model="filterForm.status" class="mt-1" :options="statusOptions" :all-option="true" all-label="All files" :searchable="false" />
                        </label>
                        <div class="flex gap-2">
                            <button type="submit" class="btn-primary text-sm">Apply</button>
                            <button v-if="hasFilters" type="button" class="btn-ghost text-sm" @click="clearFilters">Clear</button>
                        </div>
                    </form>
                </template>

                <tr v-for="dl in downloads.data" :key="dl.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ dl.title }}</td>
                    <td class="px-4 py-3 text-gray-500 capitalize">{{ dl.category.replace(/_/g,' ') }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">{{ dl.academic_year || '—' }}</td>
                    <td class="px-4 py-3">
                        <span :class="dl.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-500'" class="rounded-full px-2 py-1 text-xs font-semibold">
                            {{ dl.is_active ? 'Active' : 'Hidden' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button type="button" @click="remove(dl)" class="btn-ghost text-xs text-red-600">Remove</button>
                    </td>
                </tr>
            </SahodayaDataTable>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useForm, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import { useConfirm } from '@/composables/useConfirm';
import SahodayaDataTable from '@/Components/SahodayaDataTable.vue';
const { confirm } = useConfirm();

const props = defineProps({
    school:    Object,
    downloads: Object,
    filters: { type: Object, default: () => ({}) },
});

const categories = [
    { value: 'booklist',       label: 'Book List' },
    { value: 'calendar',       label: 'Academic Calendar' },
    { value: 'circular',       label: 'Circular' },
    { value: 'question_paper', label: 'Question Paper' },
    { value: 'annual_report',  label: 'Annual Report' },
    { value: 'form',           label: 'Form' },
    { value: 'minutes',        label: 'Meeting Minutes' },
    { value: 'other',          label: 'Other' },
];
const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'hidden', label: 'Hidden' },
];
const columns = [
    { key: 'title', label: 'Title' },
    { key: 'category', label: 'Category' },
    { key: 'year', label: 'Year' },
    { key: 'visibility', label: 'Visibility' },
    { key: 'actions', label: 'Actions', align: 'right' },
];
const filterForm = reactive({
    search: props.filters.search ?? '',
    category: props.filters.category ?? '',
    status: props.filters.status ?? '',
});
const hasFilters = computed(() => Boolean(filterForm.search || filterForm.category || filterForm.status));
const base = `/school-admin/${props.school.id}/downloads`;

function applyFilters() {
    router.get(base, {
        search: filterForm.search || undefined,
        category: filterForm.category || undefined,
        status: filterForm.status || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function clearFilters() {
    filterForm.search = '';
    filterForm.category = '';
    filterForm.status = '';
    applyFilters();
}

const form = useForm({
    title:         '',
    category:      'circular',
    academic_year: '',
    file:          null,
});

function upload() {
    form.post(`/school-admin/${props.school.id}/downloads`, {
        forceFormData: true,
        onSuccess: () => form.reset(),
    });
}

async function remove(dl) {
    if (!(await confirm({ message: `Remove "${dl.title}"?`, destructive: true }))) return;
    router.delete(`/school-admin/${props.school.id}/downloads/${dl.id}`);
}
</script>
