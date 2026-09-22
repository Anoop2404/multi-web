<template>
    <SchoolAdminLayout title="News Articles" :school="school" :show-header-title="false">
        <PageHeader title="News Articles" eyebrow="Website"
            description="Search, review and manage the announcements published on your school website.">
            <template #actions>
                <Link :href="`/school-admin/${school.id}/news/create`"
                      class="btn-primary">
                    + New Article
                </Link>
            </template>
        </PageHeader>

        <SahodayaDataTable
            label="News articles"
            :columns="columns"
            :links="articles.links"
            :meta="articles"
            :sort="filters.sort"
            :dir="filters.dir"
            :has-rows="articles.data.length > 0"
            empty="No articles found"
            empty-description="Try clearing the filters, or create your first news article."
            empty-icon="📰"
            @sort="toggleSort"
        >
            <template #toolbar>
                <form class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(14rem,1fr)_12rem_12rem_auto] lg:items-end" @submit.prevent="applyFilters">
                    <label class="form-label">Search
                        <input v-model="filterForm.search" type="search" class="field mt-1" placeholder="Title or category">
                    </label>
                    <label class="form-label">Status
                        <SearchableSelect v-model="filterForm.status" class="mt-1" :options="statusOptions" :all-option="true" all-label="All statuses" :searchable="false" />
                    </label>
                    <label class="form-label">Category
                        <SearchableSelect v-model="filterForm.category" class="mt-1" :options="categories" :all-option="true" all-label="All categories" />
                    </label>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary text-sm">Apply</button>
                        <button v-if="hasFilters" type="button" class="btn-ghost text-sm" @click="clearFilters">Clear</button>
                    </div>
                </form>
            </template>

            <tr v-for="article in articles.data" :key="article.id" class="hover:bg-gray-50 transition">
                <td class="px-4 py-3 font-medium text-gray-800 max-w-sm">
                    <Link :href="`/school-admin/${school.id}/news/${article.id}/edit`" class="hover:text-[#0f3d7a] hover:underline">{{ article.title }}</Link>
                </td>
                <td class="px-4 py-3 text-gray-500">{{ article.category || '—' }}</td>
                <td class="px-4 py-3 text-gray-500 text-xs whitespace-nowrap">
                    {{ article.published_at ? new Date(article.published_at).toLocaleDateString('en-IN') : 'Draft' }}
                </td>
                <td class="px-4 py-3">
                    <span v-if="article.is_featured" class="text-amber-600 text-xs font-semibold">★ Featured</span>
                    <span v-else class="text-gray-300 text-xs">—</span>
                </td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                    <Link :href="`/school-admin/${school.id}/news/${article.id}/edit`" class="btn-ghost text-xs">Edit</Link>
                    <button type="button" @click="destroy(article)" class="btn-ghost text-xs text-red-600">Delete</button>
                </td>
            </tr>

            <template #empty-actions>
                <Link :href="`/school-admin/${school.id}/news/create`" class="btn-primary mt-4 text-sm">Create article</Link>
            </template>
        </SahodayaDataTable>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import SahodayaDataTable from '@/Components/SahodayaDataTable.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    school:   Object,
    articles: Object,
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const columns = [
    { key: 'title', label: 'Title', sortable: true },
    { key: 'category', label: 'Category', sortable: true },
    { key: 'published_at', label: 'Published', sortable: true },
    { key: 'featured', label: 'Featured' },
    { key: 'actions', label: 'Actions', align: 'right' },
];
const statusOptions = [
    { value: 'published', label: 'Published' },
    { value: 'draft', label: 'Draft' },
];
const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
    category: props.filters.category ?? '',
});
const hasFilters = computed(() => Boolean(filterForm.search || filterForm.status || filterForm.category));
const base = `/school-admin/${props.school.id}/news`;

function visit(extra = {}) {
    router.get(base, {
        search: filterForm.search || undefined,
        status: filterForm.status || undefined,
        category: filterForm.category || undefined,
        sort: extra.sort ?? props.filters.sort ?? 'published_at',
        dir: extra.dir ?? props.filters.dir ?? 'desc',
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function applyFilters() { visit(); }

function clearFilters() {
    filterForm.search = '';
    filterForm.status = '';
    filterForm.category = '';
    visit();
}

function toggleSort(key) {
    visit({ sort: key, dir: props.filters.sort === key && props.filters.dir === 'asc' ? 'desc' : 'asc' });
}

const { confirm } = useConfirm();

async function destroy(article) {
    if (!(await confirm({ message: `Delete "${article.title}"?`, destructive: true }))) return;
    router.delete(`/school-admin/${props.school.id}/news/${article.id}`);
}
</script>
