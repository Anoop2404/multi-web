<template>
    <SchoolAdminLayout title="Achievements" :school="school" :show-header-title="false">
        <PageHeader title="Achievements" eyebrow="Website"
            description="Add and manage awards shown publicly, with search by category, level and academic year." />

        <div class="space-y-6">
            <form class="card grid gap-3 sm:grid-cols-2 lg:grid-cols-[minmax(13rem,1fr)_11rem_11rem_11rem_auto] lg:items-end" @submit.prevent="applyFilters">
                <label class="form-label">Search
                    <input v-model="filterForm.search" type="search" class="field mt-1" placeholder="Title or description">
                </label>
                <div>
                    <label class="form-label mb-1.5">Category</label>
                    <SearchableSelect v-model="filterForm.category" :options="categoryOptions" :all-option="true" all-label="All" />
                </div>
                <div>
                    <label class="form-label mb-1.5">Level</label>
                    <SearchableSelect v-model="filterForm.level" :options="levelOptions" :all-option="true" all-label="All" />
                </div>
                <div>
                    <label class="form-label mb-1.5">Academic year</label>
                    <SearchableSelect v-model="filterForm.academic_year" :options="academicYears" :all-option="true" all-label="All" />
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="btn-primary text-sm">Filter</button>
                    <button v-if="hasFilters" type="button" class="btn-ghost text-sm" @click="clearFilters">Clear</button>
                </div>
            </form>

            <div class="card">
                <h3 class="font-bold text-gray-800 mb-4">{{ editing ? 'Edit Achievement' : 'Add Achievement' }}</h3>
                <form @submit.prevent="save" class="space-y-4">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="form-label mb-1.5">Title *</label>
                            <input v-model="form.title" type="text" required class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Category</label>
                            <SearchableSelect v-model="form.category" :options="categoryOptions" :all-option="true" all-label="— Select —" />
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Level</label>
                            <SearchableSelect v-model="form.level" :options="levelOptions" :all-option="true" all-label="— Select —" />
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Academic year</label>
                            <input v-model="form.academic_year" type="text" placeholder="2024-25" class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Date Achieved</label>
                            <input v-model="form.achieved_at" type="date" class="field">
                        </div>
                        <ImageUploadField v-model="form.image" label="Photo or trophy image"
                            :preview-url="editingItem?.image_url || ''" :allow-remove="!editing"
                            :max-size-mb="4" :error="form.errors.image"
                            help="JPG, PNG, WebP or GIF · up to 4 MB" />
                        <div class="sm:col-span-2">
                            <label class="form-label mb-1.5">Description</label>
                            <textarea v-model="form.description" rows="3" class="field resize-none"></textarea>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" :disabled="form.processing"
                                class="bg-amber-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-amber-700 transition disabled:opacity-50">
                            {{ editing ? 'Save Changes' : 'Add Achievement' }}
                        </button>
                        <button v-if="editing" type="button" @click="cancelEdit"
                                class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                    </div>
                </form>
            </div>

            <SahodayaDataTable
                label="School achievements"
                :columns="columns"
                :links="achievements.links"
                :meta="achievements"
                :has-rows="achievements.data.length > 0"
                empty="No achievements found"
                empty-description="Try clearing the filters, or add an achievement using the form above."
                empty-icon="🏆"
                min-width-class="min-w-[58rem]"
            >
                        <tr v-for="item in achievements.data" :key="item.id" class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <img v-if="item.image_url" :src="item.image_url" :alt="item.title" class="h-10 w-10 object-cover rounded-lg border border-gray-100">
                                    <div class="w-10 h-10 bg-amber-50 rounded-lg flex items-center justify-center text-amber-600 text-lg" v-else>★</div>
                                    <div>
                                        <p class="font-medium text-gray-800">
                                            {{ item.title }}
                                            <span v-if="item.is_system_generated" class="ml-1 text-[10px] uppercase tracking-wide text-indigo-600">system</span>
                                        </p>
                                        <p v-if="item.description" class="text-xs text-gray-400 line-clamp-1">{{ item.description }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span v-if="item.category" class="text-xs bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full font-medium">
                                    {{ categories[item.category] || item.category }}
                                </span>
                                <span v-else class="text-gray-300">—</span>
                            </td>
                            <td class="px-5 py-3 text-gray-500 text-xs">{{ levels[item.level] || item.level || '—' }}</td>
                            <td class="px-5 py-3 text-gray-500 text-xs">{{ item.academic_year || '—' }}</td>
                            <td class="px-5 py-3 text-gray-400 text-xs">
                                {{ item.achieved_at ? new Date(item.achieved_at).toLocaleDateString('en-IN') : '—' }}
                            </td>
                            <td class="px-5 py-3 text-right space-x-3">
                                <template v-if="!item.is_system_generated">
                                    <button @click="startEdit(item)" class="text-xs text-blue-500 hover:underline">Edit</button>
                                    <button @click="remove(item)" class="text-xs text-red-400 hover:underline">Delete</button>
                                </template>
                                <span v-else class="text-xs text-slate-400">Locked</span>
                            </td>
                        </tr>
            </SahodayaDataTable>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import ImageUploadField from '@/Components/Website/ImageUploadField.vue';
import SahodayaDataTable from '@/Components/SahodayaDataTable.vue';
import { computed, reactive, ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';
const { confirm } = useConfirm();

const props = defineProps({
    school: Object,
    achievements: Object,
    categories: { type: Object, default: () => ({}) },
    levels: { type: Object, default: () => ({}) },
    academicYears: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const categoryOptions = computed(() => Object.entries(props.categories).map(([value, label]) => ({ value, label })));
const levelOptions = computed(() => Object.entries(props.levels).map(([value, label]) => ({ value, label })));

const editing = ref(null);
const editingItem = computed(() => props.achievements.data.find(item => item.id === editing.value) ?? null);
const filterForm = reactive({
    search: props.filters.search || '',
    category: props.filters.category || '',
    level: props.filters.level || '',
    academic_year: props.filters.academic_year || '',
});
const hasFilters = computed(() => Object.values(filterForm).some(Boolean));
const columns = [
    { key: 'achievement', label: 'Achievement' },
    { key: 'category', label: 'Category' },
    { key: 'level', label: 'Level' },
    { key: 'year', label: 'Year' },
    { key: 'date', label: 'Date' },
    { key: 'actions', label: 'Actions', align: 'right' },
];

const form = useForm({
    title: '',
    category: '',
    level: '',
    academic_year: '',
    achieved_at: '',
    description: '',
    image: null,
});

function applyFilters() {
    router.get(`/school-admin/${props.school.id}/achievements`, { ...filterForm }, { preserveState: true, preserveScroll: true, replace: true });
}

function clearFilters() {
    filterForm.search = '';
    filterForm.category = '';
    filterForm.level = '';
    filterForm.academic_year = '';
    applyFilters();
}

function startEdit(item) {
    editing.value = item.id;
    form.title = item.title;
    form.category = item.category ?? '';
    form.level = item.level ?? '';
    form.academic_year = item.academic_year ?? '';
    form.achieved_at = item.achieved_at?.slice(0, 10) ?? '';
    form.description = item.description ?? '';
    form.image = null;
}

function cancelEdit() {
    editing.value = null;
    form.reset();
}

function save() {
    if (editing.value) {
        form.transform(d => ({ ...d, _method: 'PUT' }))
            .post(`/school-admin/${props.school.id}/achievements/${editing.value}`, {
                forceFormData: true,
                onSuccess: () => { editing.value = null; form.reset(); },
            });
    } else {
        form.post(`/school-admin/${props.school.id}/achievements`, {
            forceFormData: true,
            onSuccess: () => form.reset(),
        });
    }
}

async function remove(item) {
    if (!(await confirm({ message: `Delete "${item.title}"?`, destructive: true }))) return;
    router.delete(`/school-admin/${props.school.id}/achievements/${item.id}`);
}
</script>
