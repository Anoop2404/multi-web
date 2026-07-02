<template>
    <SchoolAdminLayout title="Job Vacancies" :school="school" :show-header-title="false">
        <PageHeader title="Job Vacancies" eyebrow="Website"
            description="School website content and public pages." />


        <div class="space-y-6">
            <!-- Add / Edit form -->
            <div class="card">
                <h3 class="font-bold text-gray-800 mb-4">{{ editing ? 'Edit Vacancy' : 'Post New Vacancy' }}</h3>
                <form @submit.prevent="save" class="space-y-4">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="form-label mb-1.5">Position Title *</label>
                            <input v-model="form.title" type="text" required placeholder="PGT Mathematics, Office Assistant..."
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Qualification Required</label>
                            <input v-model="form.qualification" type="text" placeholder="M.Sc., B.Ed., CTET..."
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Experience</label>
                            <input v-model="form.experience" type="text" placeholder="2+ years, Fresher welcome..."
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Application Deadline</label>
                            <input v-model="form.last_date" type="date"
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Apply via Email</label>
                            <input v-model="form.apply_email" type="email" placeholder="principal@school.edu.in"
                                   class="field">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label mb-1.5">Description / Job Details</label>
                            <textarea v-model="form.description" rows="4"
                                      class="field resize-none"></textarea>
                        </div>
                        <div class="sm:col-span-2 flex items-center gap-2">
                            <input type="checkbox" id="is_active" v-model="form.is_active" class="rounded">
                            <label for="is_active" class="text-sm text-gray-700">Show on website</label>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" :disabled="form.processing"
                                class="btn-primary text-white px-5 py-2.5 rounded-lg text-sm font-semibold transition disabled:opacity-50">
                            {{ editing ? 'Save Changes' : 'Post Vacancy' }}
                        </button>
                        <button v-if="editing" type="button" @click="cancelEdit"
                                class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- Vacancies list -->
            <div class="space-y-3">
                <div v-for="vac in vacancies" :key="vac.id"
                     class="card-list-row justify-between">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <h4 class="font-bold text-gray-800">{{ vac.title }}</h4>
                            <span :class="vac.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-400'"
                                  class="text-xs px-2 py-0.5 rounded-full font-medium">
                                {{ vac.is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
                            <span v-if="vac.qualification">Qualification: {{ vac.qualification }}</span>
                            <span v-if="vac.experience">Experience: {{ vac.experience }}</span>
                            <span v-if="vac.last_date" class="text-red-500 font-medium">
                                Deadline: {{ new Date(vac.last_date).toLocaleDateString('en-IN') }}
                            </span>
                            <span v-if="vac.apply_email">Apply: {{ vac.apply_email }}</span>
                        </div>
                        <p v-if="vac.description" class="text-xs text-gray-400 mt-2 line-clamp-2">{{ vac.description }}</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button @click="startEdit(vac)" class="text-xs text-blue-500 hover:underline">Edit</button>
                        <button @click="remove(vac)" class="text-xs text-red-400 hover:underline">Delete</button>
                    </div>
                </div>

                <div v-if="!vacancies.length"
                     class="card card--dashed p-10 text-center text-slate-400">
                    No vacancies posted yet.
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    school:    Object,
    vacancies: { type: Array, default: () => [] },
});

const editing = ref(null);

const form = useForm({
    title:         '',
    description:   '',
    qualification: '',
    experience:    '',
    last_date:     '',
    apply_email:   '',
    is_active:     true,
});

function startEdit(vac) {
    editing.value      = vac.id;
    form.title         = vac.title;
    form.description   = vac.description ?? '';
    form.qualification = vac.qualification ?? '';
    form.experience    = vac.experience ?? '';
    form.last_date     = vac.last_date?.slice(0, 10) ?? '';
    form.apply_email   = vac.apply_email ?? '';
    form.is_active     = vac.is_active ?? true;
}

function cancelEdit() {
    editing.value = null;
    form.reset();
    form.is_active = true;
}

function save() {
    if (editing.value) {
        form.put(`/school-admin/${props.school.id}/job-vacancies/${editing.value}`, {
            onSuccess: () => { editing.value = null; form.reset(); form.is_active = true; },
        });
    } else {
        form.post(`/school-admin/${props.school.id}/job-vacancies`, {
            onSuccess: () => { form.reset(); form.is_active = true; },
        });
    }
}

function remove(vac) {
    if (!confirm(`Delete vacancy "${vac.title}"?`)) return;
    router.delete(`/school-admin/${props.school.id}/job-vacancies/${vac.id}`);
}
</script>
