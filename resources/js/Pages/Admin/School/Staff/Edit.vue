<template>
    <SchoolAdminLayout :title="`Edit: ${staff.name}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Edit: ${staff.name}`" eyebrow="Website"
            description="Update staff profile shown on the public school website." />


        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="space-y-5">
                <div class="card space-y-5">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label mb-1.5">Full Name *</label>
                            <input v-model="form.name" type="text" required
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Designation *</label>
                            <input v-model="form.designation" type="text" required
                                   class="field">
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label mb-1.5">Department</label>
                            <input v-model="form.department" type="text" placeholder="Science, Arts, Admin..."
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Qualification</label>
                            <input v-model="form.qualification" type="text" placeholder="M.Sc., B.Ed."
                                   class="field">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-2">Staff Type *</label>
                        <div class="flex gap-3">
                            <label v-for="type in ['teaching','non-teaching','admin']" :key="type"
                                   class="flex items-center gap-2 px-4 py-2 rounded-lg border-2 cursor-pointer transition"
                                   :class="form.type === type ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-200 text-gray-600'">
                                <input type="radio" v-model="form.type" :value="type" class="sr-only">
                                <span class="text-sm font-medium capitalize">{{ type }}</span>
                            </label>
                        </div>
                    </div>

                    <div v-if="staff.photo" class="flex items-center gap-3 text-sm text-gray-500">
                        <img :src="staff.photo" class="h-16 w-16 object-cover rounded-full border border-gray-100">
                        <span>Current photo</span>
                    </div>

                    <div>
                        <label class="form-label mb-1.5">{{ staff.photo ? 'Replace Photo' : 'Photo' }}</label>
                        <input type="file" accept="image/*" @change="form.photo = $event.target.files[0]"
                               class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700">
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="is_active" v-model="form.is_active" class="rounded">
                        <label for="is_active" class="text-sm text-gray-700">Show on website</label>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <button type="submit" :disabled="form.processing"
                            class="btn-primary disabled:opacity-50">
                        Save Changes
                    </button>
                    <Link :href="`/school-admin/${school.id}/staff`" class="text-sm text-gray-500 hover:text-gray-700">Cancel</Link>
                </div>
            </form>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ school: Object, staff: Object });

const form = useForm({
    name:          props.staff.name,
    designation:   props.staff.designation,
    department:    props.staff.department ?? '',
    qualification: props.staff.qualification ?? '',
    type:          props.staff.type ?? 'teaching',
    is_active:     props.staff.is_active ?? true,
    photo:         null,
    _method:       'PUT',
});

function submit() {
    form.post(`/school-admin/${props.school.id}/staff/${props.staff.id}`, {
        forceFormData: true,
    });
}
</script>
