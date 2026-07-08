<template>
    <SchoolAdminLayout title="Bulk Add Students" :school="school" :show-header-title="false">
        <PageHeader title="Bulk Add Students" eyebrow="Students"
            description="Add up to 25 students at once. Each new record starts unverified — your Sahodaya admin verifies students centrally.">
            <template #actions>
                <Link :href="`/school-admin/${school.id}/students/create`" class="btn-secondary">Add one student</Link>
                <Link :href="`/school-admin/${school.id}/students`" class="btn-ghost">Back to list</Link>
            </template>
        </PageHeader>

        <div class="max-w-6xl">
            <div v-if="!schoolClasses.length"
                 class="notice-banner notice-banner--warning">
                No classes are available yet. Contact your Sahodaya admin — classes are configured centrally.
            </div>

            <form v-else @submit.prevent="submit" class="card space-y-5">
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 text-sm text-gray-600">
                    <p><strong>Required per row:</strong> photo, full name, class, gender, date of birth.</p>
                </div>

                <div class="rounded-2xl border border-gray-100 overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 border-b border-gray-100">
                                <tr>
                                    <th class="w-10 px-2 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">#</th>
                                    <th class="w-28 px-2 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Photo *</th>
                                    <th class="px-2 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide min-w-[180px]">Full name *</th>
                                    <th class="w-44 px-2 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Class *</th>
                                    <th class="w-32 px-2 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Gender *</th>
                                    <th class="w-40 px-2 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">DOB *</th>
                                    <th class="w-14 px-2 py-3"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-50">
                                <tr v-for="(row, index) in bulkRows" :key="row.key" class="hover:bg-gray-50/50">
                                    <td class="px-2 py-2 text-xs text-gray-400 font-mono align-middle">{{ index + 1 }}</td>
                                    <td class="px-2 py-2 align-middle">
                                        <BulkRowPhotoInput v-model="row.photo" />
                                    </td>
                                    <td class="px-2 py-2 align-middle">
                                        <input v-model="row.name" type="text" class="field !py-2" required placeholder="Student name">
                                    </td>
                                    <td class="px-2 py-2 align-middle">
                                        <select v-model="row.school_class_id" class="field !py-2" required>
                                            <option value="">Class</option>
                                            <option v-for="c in schoolClassesSorted" :key="c.id" :value="c.id">
                                                {{ classShortLabel(c) }}
                                            </option>
                                        </select>
                                    </td>
                                    <td class="px-2 py-2 align-middle">
                                        <select v-model="row.gender" class="field !py-2" required>
                                            <option value="">—</option>
                                            <option value="male">Male</option>
                                            <option value="female">Female</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </td>
                                    <td class="px-2 py-2 align-middle">
                                        <input v-model="row.dob" type="date" class="field !py-2" required>
                                    </td>
                                    <td class="px-2 py-2 align-middle text-right">
                                        <button v-if="bulkRows.length > 1" type="button"
                                                class="text-xs text-red-600 font-semibold hover:underline"
                                                @click="removeBulkRow(index)">
                                            Remove
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <button type="button" class="btn-secondary text-xs" :disabled="bulkRows.length >= 25" @click="addBulkRow">
                    + Add row
                </button>

                <p v-if="clientError" class="form-error">{{ clientError }}</p>
                <p v-if="form.errors.students" class="form-error">{{ form.errors.students }}</p>

                <FormActions>
                    <Link :href="`/school-admin/${school.id}/students`" class="btn-ghost">Cancel</Link>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        Save {{ filledRowCount }} student{{ filledRowCount === 1 ? '' : 's' }}
                    </button>
                </FormActions>
            </form>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import BulkRowPhotoInput from '@/Components/school/BulkRowPhotoInput.vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    school:     Object,
    categories: { type: Array, default: () => [] },
    classes:    { type: Array, default: () => [] },
});

const clientError = ref('');
let bulkKey = 0;

function createBulkRow(copyFrom = null) {
    return {
        key: ++bulkKey,
        name: '',
        gender: '',
        dob: '',
        school_class_id: copyFrom?.school_class_id ?? '',
        photo: null,
    };
}

const bulkRows = ref([createBulkRow()]);

const form = useForm({ students: [] });

const schoolClasses = computed(() =>
    (props.classes ?? []).filter(c => c.is_active !== false),
);

const schoolClassesSorted = computed(() =>
    [...schoolClasses.value].sort((a, b) =>
        (a.display_order ?? 0) - (b.display_order ?? 0)
        || String(a.name).localeCompare(String(b.name), undefined, { numeric: true }),
    ),
);

const filledRowCount = computed(() =>
    bulkRows.value.filter(row => row.name.trim() !== '').length || bulkRows.value.length,
);

function addBulkRow() {
    if (bulkRows.value.length >= 25) return;
    const last = bulkRows.value[bulkRows.value.length - 1];
    bulkRows.value.push(createBulkRow(last));
}

function removeBulkRow(index) {
    bulkRows.value.splice(index, 1);
}

function classShortLabel(schoolClass) {
    const cat = props.categories.find(c => Number(c.id) === Number(schoolClass.class_category_id));
    return cat ? `${schoolClass.name} · ${cat.label}` : String(schoolClass.name);
}

function submit() {
    clientError.value = '';

    const students = bulkRows.value
        .filter(row => row.name.trim() !== '')
        .map(row => ({
            school_class_id: row.school_class_id,
            name: row.name.trim(),
            gender: row.gender,
            dob: row.dob,
            photo: row.photo,
        }));

    if (!students.length) {
        clientError.value = 'Enter at least one student name.';
        return;
    }

    if (students.some(row => !row.photo)) {
        clientError.value = 'Upload a photo for each student.';
        return;
    }

    if (students.some(row => !row.school_class_id)) {
        clientError.value = 'Select class for each student.';
        return;
    }

    if (students.some(row => !row.gender)) {
        clientError.value = 'Select gender for each student.';
        return;
    }

    if (students.some(row => !row.dob)) {
        clientError.value = 'Date of birth is required for each student.';
        return;
    }

    form
        .transform(() => ({ students }))
        .post(`/school-admin/${props.school.id}/students/bulk`, {
            forceFormData: true,
            onSuccess: () => router.visit(`/school-admin/${props.school.id}/students`),
        });
}
</script>
