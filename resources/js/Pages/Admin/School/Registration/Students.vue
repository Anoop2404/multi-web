<template>
    <SchoolAdminLayout title="Submission Students" :school="school">
        <div class="max-w-4xl space-y-4">
            <Link :href="`/school-admin/${school.id}/registration`" class="text-sm text-blue-600">← Registration</Link>
            <p class="text-sm text-gray-500">Status: {{ submission.full_records_status }}</p>
            <p v-if="submission.full_records_rejection_reason" class="text-sm text-red-600">Rejected: {{ submission.full_records_rejection_reason }}</p>

            <div v-if="!classes.length"
                 class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg text-sm">
                No classes are available yet. Contact your Sahodaya admin — classes are configured centrally.
            </div>

            <form v-if="['pending','rejected'].includes(submission.full_records_status) && classes.length"
                  @submit.prevent="add"
                  class="bg-white border rounded-xl p-4 grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <input v-model="form.name" required placeholder="Name *" class="border rounded-lg px-3 py-2 text-sm">
                <select v-model="form.school_class_id" required class="border rounded-lg px-3 py-2 text-sm">
                    <option value="">Class *</option>
                    <optgroup v-for="cat in categories" :key="cat.id" :label="cat.label">
                        <option v-for="c in classesInCategory(cat.id)" :key="c.id" :value="c.id">
                            Class {{ c.name }}
                        </option>
                    </optgroup>
                </select>
                <input v-model="form.section" placeholder="Section" class="border rounded-lg px-3 py-2 text-sm">
                <input v-model="form.guardian_name" placeholder="Guardian name" class="border rounded-lg px-3 py-2 text-sm">
                <button type="submit" class="sm:col-span-2 lg:col-span-4 bg-blue-600 text-white py-2 rounded-lg text-sm font-semibold">
                    Add Student
                </button>
            </form>

            <table class="w-full text-sm bg-white border rounded-xl overflow-hidden">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left">Name</th>
                        <th class="px-4 py-2 text-left">Category</th>
                        <th class="px-4 py-2 text-left">Class</th>
                        <th class="px-4 py-2 text-left">Section</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in students" :key="s.id" class="border-t">
                        <td class="px-4 py-2 font-medium">{{ s.name }}</td>
                        <td class="px-4 py-2 text-gray-500 text-xs">{{ s.school_class?.class_category?.label || '—' }}</td>
                        <td class="px-4 py-2">{{ s.school_class?.name || s.class }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ s.section || '—' }}</td>
                        <td class="px-4 py-2">
                            <button v-if="['pending','rejected'].includes(submission.full_records_status)"
                                    @click="remove(s)" class="text-red-400 text-xs">Remove</button>
                        </td>
                    </tr>
                    <tr v-if="!students.length">
                        <td colspan="5" class="px-4 py-8 text-center text-gray-400">No students added yet.</td>
                    </tr>
                </tbody>
            </table>

            <button v-if="['pending','rejected'].includes(submission.full_records_status)"
                    @click="submit"
                    class="bg-[#0f3d7a] text-white px-4 py-2 rounded-lg text-sm font-semibold">
                Submit for Review
            </button>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
    school:       Object,
    registration: Object,
    submission:   Object,
    categories:   { type: Array, default: () => [] },
    classes:      { type: Array, default: () => [] },
    students:     { type: Array, default: () => [] },
});

const form = useForm({ name: '', school_class_id: '', section: '', guardian_name: '' });

function classesInCategory(categoryId) {
    return props.classes.filter(c => c.class_category_id === categoryId);
}

function add() {
    form.post(`/school-admin/${props.school.id}/registration/students`, {
        onSuccess: () => form.reset('name', 'section', 'guardian_name'),
    });
}

function remove(s) { router.delete(`/school-admin/${props.school.id}/registration/students/${s.id}`); }
function submit() { router.post(`/school-admin/${props.school.id}/registration/submit-track`, { track: 'full_records' }); }
</script>
