<template>
    <SchoolAdminLayout title="Edit Student" :school="school">
        <div class="max-w-3xl">
            <form @submit.prevent="submit" class="space-y-5">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
                    <h3 class="font-bold text-gray-800">Enrollment</h3>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Class *</label>
                            <select v-model="form.school_class_id" required
                                    class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                                <optgroup v-for="cat in categories" :key="cat.id" :label="cat.label">
                                    <option v-for="c in classesInCategory(cat.id)" :key="c.id" :value="c.id">
                                        Class {{ c.name }}
                                    </option>
                                </optgroup>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Status *</label>
                            <select v-model="form.status" required
                                    class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                                <option value="active">Active</option>
                                <option value="transferred">Transferred</option>
                                <option value="graduated">Graduated</option>
                                <option value="withdrawn">Withdrawn</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Roll Number</label>
                            <input v-model="form.roll_number" type="text"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
                    <h3 class="font-bold text-gray-800">Student Details</h3>

                    <div class="flex items-center gap-4 pb-2 border-b border-gray-100">
                        <div class="w-16 h-16 rounded-full overflow-hidden border border-gray-200 bg-gray-100 shrink-0">
                            <img v-if="photoPreview || student.photo_url" :src="photoPreview || student.photo_url"
                                 :alt="student.name" class="w-full h-full object-cover">
                            <div v-else class="w-full h-full flex items-center justify-center text-sm text-gray-400 font-semibold">
                                {{ student.name?.slice(0, 2).toUpperCase() }}
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                                {{ student.photo_url ? 'Replace photo' : 'Add photo' }}
                            </label>
                            <input type="file" accept="image/*" @change="onPhotoChange"
                                   class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700">
                            <p class="text-xs text-gray-400 mt-1">JPG or PNG, max 2 MB</p>
                        </div>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Full Name *</label>
                            <input v-model="form.name" type="text" required
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Date of Birth</label>
                            <input v-model="form.dob" type="date"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Gender</label>
                            <select v-model="form.gender"
                                    class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                                <option value="">—</option>
                                <option value="male">Male</option>
                                <option value="female">Female</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Blood Group</label>
                            <input v-model="form.blood_group" type="text"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Admission Date</label>
                            <input v-model="form.admission_date" type="date"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Notes</label>
                        <textarea v-model="form.notes" rows="2"
                                  class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 resize-none"></textarea>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
                    <h3 class="font-bold text-gray-800">Parent / Guardian</h3>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Parent Name</label>
                            <input v-model="form.parent_name" type="text"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Phone</label>
                            <input v-model="form.parent_phone" type="tel"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Email</label>
                            <input v-model="form.parent_email" type="email"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Address</label>
                            <textarea v-model="form.address" rows="2"
                                      class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 resize-none"></textarea>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <button type="submit" :disabled="form.processing"
                            class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-semibold text-sm hover:bg-blue-700 transition disabled:opacity-50">
                        Save Changes
                    </button>
                    <Link :href="`/school-admin/${school.id}/students`" class="text-sm text-gray-500 hover:text-gray-700">Cancel</Link>
                </div>
            </form>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    school:     Object,
    student:    Object,
    categories: { type: Array, default: () => [] },
    classes:    { type: Array, default: () => [] },
});

function classesInCategory(categoryId) {
    return props.classes.filter(c => c.class_category_id === categoryId);
}

const s = props.student;
const photoPreview = ref(null);

const form = useForm({
    school_class_id:  s.school_class_id,
    roll_number:       s.roll_number ?? '',
    name:              s.name,
    dob:               s.dob?.slice(0, 10) ?? '',
    gender:            s.gender ?? '',
    blood_group:       s.blood_group ?? '',
    parent_name:       s.parent_name ?? '',
    parent_phone:      s.parent_phone ?? '',
    parent_email:      s.parent_email ?? '',
    address:           s.address ?? '',
    admission_date:    s.admission_date?.slice(0, 10) ?? '',
    status:            s.status,
    notes:             s.notes ?? '',
    photo:             null,
});

function onPhotoChange(event) {
    const file = event.target.files?.[0] ?? null;
    form.photo = file;
    photoPreview.value = file ? URL.createObjectURL(file) : null;
}

function submit() {
    form.put(`/school-admin/${props.school.id}/students/${props.student.id}`, { forceFormData: true });
}
</script>
