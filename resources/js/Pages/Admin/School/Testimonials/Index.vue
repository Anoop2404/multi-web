<template>
    <SchoolAdminLayout title="Testimonials" :school="school" :show-header-title="false">
        <PageHeader title="Testimonials" eyebrow="Website"
            description="Add, search and manage parent, student and alumni testimonials displayed publicly." />


        <div class="space-y-6">
            <!-- Add / Edit form -->
            <div class="card">
                <h3 class="font-bold text-gray-800 mb-4">{{ editing ? 'Edit Testimonial' : 'Add Testimonial' }}</h3>
                <form @submit.prevent="save" class="space-y-4">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="form-label mb-1.5">Name *</label>
                            <input v-model="form.name" type="text" required
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Designation</label>
                            <input v-model="form.designation" type="text" placeholder="Parent of Class X Student, Alumnus..."
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Display Order</label>
                            <input v-model="form.display_order" type="number" min="0" placeholder="0"
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Rating</label>
                            <select v-model="form.rating" class="field">
                                <option v-for="value in 5" :key="value" :value="value">{{ value }} star{{ value === 1 ? '' : 's' }}</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label mb-1.5">Testimonial Quote *</label>
                            <textarea v-model="form.quote" rows="4" required
                                      placeholder="Share their experience..."
                                      class="field resize-none"></textarea>
                        </div>
                        <ImageUploadField v-model="form.photo" label="Person photo"
                            :preview-url="editingItem?.photo_url || ''" :allow-remove="!editing"
                            :max-size-mb="4" :error="form.errors.photo"
                            help="Square or portrait image recommended · up to 4 MB" />
                        <div class="flex items-center gap-2 pt-5">
                            <input type="checkbox" id="is_active" v-model="form.is_active" class="rounded">
                            <label for="is_active" class="text-sm text-gray-700">Show on website</label>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" :disabled="form.processing"
                                class="bg-teal-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-teal-700 transition disabled:opacity-50">
                            {{ editing ? 'Save Changes' : 'Add Testimonial' }}
                        </button>
                        <button v-if="editing" type="button" @click="cancelEdit"
                                class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                    </div>
                </form>
            </div>

            <SahodayaDataTable
                label="Website testimonials"
                :columns="columns"
                :links="testimonials.links"
                :meta="testimonials"
                :has-rows="testimonials.data.length > 0"
                empty="No testimonials found"
                empty-description="Try clearing the filters, or add a testimonial using the form above."
                empty-icon="⭐"
                min-width-class="min-w-[54rem]"
            >
                <template #toolbar>
                    <form class="grid gap-3 sm:grid-cols-[minmax(14rem,1fr)_11rem_auto] sm:items-end" @submit.prevent="applyFilters">
                        <label class="form-label">Search
                            <input v-model="filterForm.search" type="search" class="field mt-1" placeholder="Name, designation or quote">
                        </label>
                        <label class="form-label">Visibility
                            <SearchableSelect v-model="filterForm.status" class="mt-1" :options="statusOptions" :all-option="true" all-label="All testimonials" :searchable="false" />
                        </label>
                        <div class="flex gap-2">
                            <button type="submit" class="btn-primary text-sm">Apply</button>
                            <button v-if="hasFilters" type="button" class="btn-ghost text-sm" @click="clearFilters">Clear</button>
                        </div>
                    </form>
                </template>

                        <tr v-for="t in testimonials.data" :key="t.id" class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <img v-if="t.photo_url" :src="t.photo_url" :alt="t.name" class="w-9 h-9 rounded-full object-cover border border-gray-100 shrink-0">
                                    <div class="w-9 h-9 rounded-full bg-teal-50 flex items-center justify-center text-teal-600 font-bold text-sm shrink-0" v-else>
                                        {{ t.name[0] }}
                                    </div>
                                    <p class="font-medium text-gray-800">{{ t.name }}</p>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-gray-500 text-xs">{{ t.designation || '—' }}</td>
                            <td class="px-5 py-3 text-gray-400 text-xs">{{ t.display_order ?? '—' }}</td>
                            <td class="px-5 py-3 text-amber-500 text-xs">{{ '★'.repeat(t.rating || 0) }}</td>
                            <td class="px-5 py-3">
                                <span :class="t.is_active ? 'bg-green-50 text-green-700' : 'text-gray-300'"
                                      class="text-xs font-medium">
                                    {{ t.is_active ? '● Active' : '○ Hidden' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right space-x-3">
                                <button @click="startEdit(t)" class="text-xs text-blue-500 hover:underline">Edit</button>
                                <button @click="remove(t)" class="text-xs text-red-400 hover:underline">Delete</button>
                            </td>
                        </tr>
            </SahodayaDataTable>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import ImageUploadField from '@/Components/Website/ImageUploadField.vue';
import SahodayaDataTable from '@/Components/SahodayaDataTable.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { computed, reactive, ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();

const props = defineProps({
    school:       Object,
    testimonials: Object,
    filters: { type: Object, default: () => ({}) },
});

const editing = ref(null);
const editingItem = computed(() => props.testimonials.data.find(item => item.id === editing.value) ?? null);
const columns = [
    { key: 'name', label: 'Name' },
    { key: 'designation', label: 'Designation' },
    { key: 'order', label: 'Order' },
    { key: 'rating', label: 'Rating' },
    { key: 'visibility', label: 'Visibility' },
    { key: 'actions', label: 'Actions', align: 'right' },
];
const statusOptions = [
    { value: 'active', label: 'Active' },
    { value: 'hidden', label: 'Hidden' },
];
const filterForm = reactive({
    search: props.filters.search ?? '',
    status: props.filters.status ?? '',
});
const hasFilters = computed(() => Boolean(filterForm.search || filterForm.status));
const base = `/school-admin/${props.school.id}/testimonials`;

function applyFilters() {
    router.get(base, {
        search: filterForm.search || undefined,
        status: filterForm.status || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}

function clearFilters() {
    filterForm.search = '';
    filterForm.status = '';
    applyFilters();
}

const form = useForm({
    name:          '',
    designation:   '',
    quote:         '',
    rating:        5,
    display_order: '',
    is_active:     true,
    photo:         null,
});

function startEdit(t) {
    editing.value        = t.id;
    form.name            = t.name;
    form.designation     = t.designation ?? '';
    form.quote           = t.quote;
    form.rating          = t.rating ?? 5;
    form.display_order   = t.display_order ?? '';
    form.is_active       = t.is_active ?? true;
    form.photo           = null;
}

function cancelEdit() {
    editing.value = null;
    form.reset();
    form.is_active = true;
    form.rating = 5;
}

function save() {
    if (editing.value) {
        form.transform(d => ({ ...d, _method: 'PUT' }))
            .post(`/school-admin/${props.school.id}/testimonials/${editing.value}`, {
                forceFormData: true,
                onSuccess: () => { editing.value = null; form.reset(); form.is_active = true; form.rating = 5; },
            });
    } else {
        form.post(`/school-admin/${props.school.id}/testimonials`, {
            forceFormData: true,
            onSuccess: () => { form.reset(); form.is_active = true; form.rating = 5; },
        });
    }
}

async function remove(t) {
    if (!(await confirm({ message: `Delete testimonial from "${t.name}"?`, destructive: true }))) return;
    router.delete(`/school-admin/${props.school.id}/testimonials/${t.id}`);
}
</script>
