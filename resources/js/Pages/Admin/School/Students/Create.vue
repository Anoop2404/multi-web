<template>
    <SchoolAdminLayout title="Add Student" :school="school" :show-header-title="false">
        <PageHeader title="Add Student" eyebrow="Students"
            description="Register a student with photo, class, gender, and date of birth.">
            <template #actions>
                <Link :href="`/school-admin/${school.id}/students/bulk`" class="btn-secondary">Bulk add</Link>
                <Link :href="`/school-admin/${school.id}/students`" class="btn-ghost">Back to list</Link>
            </template>
        </PageHeader>

        <div class="max-w-lg">
            <div v-if="!schoolClasses.length"
                 class="notice-banner notice-banner--warning">
                No classes are available yet. Contact your Sahodaya admin — classes are configured centrally.
            </div>

            <form v-else @submit.prevent="submit" class="card space-y-5">
                <p class="text-sm text-gray-500">
                    A Sahodaya registration number
                    (<span class="font-mono text-xs">SAHODAYA/SCHOOL/YEAR/####</span>) is assigned automatically.
                </p>

                <ProfilePhotoCropper v-model="photoFile" required />

                <FormField label="Class" required>
                    <select v-model="form.school_class_id" class="field" required>
                        <option value="">Select class</option>
                        <option v-for="c in schoolClassesSorted" :key="c.id" :value="c.id">
                            {{ formatClassOption(c) }}
                        </option>
                    </select>
                    <p v-if="form.errors.school_class_id" class="form-error">{{ form.errors.school_class_id }}</p>
                </FormField>

                <FormField label="Full name" required>
                    <input v-model="form.name" type="text" class="field" required placeholder="Student name" autofocus>
                    <p v-if="form.errors.name" class="form-error">{{ form.errors.name }}</p>
                </FormField>

                <FormGrid>
                    <FormField label="Gender" required>
                        <select v-model="form.gender" class="field" required>
                            <option value="">Select gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                        <p v-if="form.errors.gender" class="form-error">{{ form.errors.gender }}</p>
                    </FormField>
                    <FormField label="Date of birth" required>
                        <input v-model="form.dob" type="date" class="field" required>
                        <p v-if="form.errors.dob" class="form-error">{{ form.errors.dob }}</p>
                    </FormField>
                </FormGrid>

                <FormField label="Portal email">
                    <input v-model="form.email" type="email" class="field" placeholder="student@example.com (optional)">
                    <p v-if="form.errors.email" class="form-error">{{ form.errors.email }}</p>
                </FormField>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.create_login" type="checkbox"> Create student portal login
                </label>
                <FormField v-if="form.create_login" label="Portal password" required>
                    <input v-model="form.password" type="password" class="field" minlength="8" placeholder="Min 8 characters">
                    <p v-if="form.errors.password" class="form-error">{{ form.errors.password }}</p>
                </FormField>

                <p v-if="clientError" class="form-error">{{ clientError }}</p>

                <FormActions>
                    <Link :href="`/school-admin/${school.id}/students`" class="btn-ghost">Cancel</Link>
                    <button type="submit" class="btn-primary" :disabled="form.processing">Save student</button>
                </FormActions>
            </form>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import ProfilePhotoCropper from '@/Components/school/ProfilePhotoCropper.vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    school:     Object,
    categories: { type: Array, default: () => [] },
    classes:    { type: Array, default: () => [] },
});

const photoFile = ref(null);
const clientError = ref('');

const schoolClasses = computed(() =>
    (props.classes ?? []).filter(c => c.is_active !== false),
);

const schoolClassesSorted = computed(() =>
    [...schoolClasses.value].sort((a, b) =>
        (a.display_order ?? 0) - (b.display_order ?? 0)
        || String(a.name).localeCompare(String(b.name), undefined, { numeric: true }),
    ),
);

const form = useForm({
    school_class_id: '',
    name:            '',
    gender:          '',
    dob:             '',
    email:           '',
    create_login:    false,
    password:        '',
    photo:           null,
});

function formatClassOption(schoolClass) {
    const cat = props.categories.find(c => Number(c.id) === Number(schoolClass.class_category_id));
    return cat ? `Class ${schoolClass.name} (${cat.label})` : `Class ${schoolClass.name}`;
}

function submit() {
    clientError.value = '';
    if (!photoFile.value) {
        clientError.value = 'Profile photo is required.';
        return;
    }

    form
        .transform(data => ({ ...data, photo: photoFile.value }))
        .post(`/school-admin/${props.school.id}/students`, {
            forceFormData: true,
            onSuccess: () => router.visit(`/school-admin/${props.school.id}/students`),
        });
}
</script>
