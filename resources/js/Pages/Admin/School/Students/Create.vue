<template>
    <SchoolAdminLayout title="Register Student" :school="school">
        <div class="max-w-md space-y-4">
            <div v-if="!categories.length"
                 class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg text-sm">
                No classes are available yet. Contact your Sahodaya admin — classes are configured centrally.
            </div>

            <form v-else @submit.prevent="submit" class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 space-y-5">
                <p class="text-sm text-gray-500">
                    Class and full name only. A Sahodaya registration number
                    (<span class="font-mono text-xs">SAHODAYA/SCHOOL/YEAR/####</span>) is assigned automatically when you register each student.
                </p>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Class *</label>
                    <select v-model="form.school_class_id" required
                            class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        <option value="">Select class</option>
                        <optgroup v-for="cat in categories" :key="cat.id" :label="cat.label">
                            <option v-for="c in classesInCategory(cat.id)" :key="c.id" :value="c.id">
                                Class {{ c.name }}
                            </option>
                        </optgroup>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Full name *</label>
                    <input v-model="form.name" type="text" required placeholder="Rahul Kumar"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                </div>

                <div class="flex items-center gap-4 pt-2">
                    <button type="submit" :disabled="form.processing"
                            class="bg-blue-600 text-white px-6 py-2.5 rounded-lg font-semibold text-sm hover:bg-blue-700 transition disabled:opacity-50">
                        Register Student
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

const props = defineProps({
    school:     Object,
    categories: { type: Array, default: () => [] },
    classes:    { type: Array, default: () => [] },
});

function classesInCategory(categoryId) {
    return props.classes.filter(c => c.class_category_id === categoryId);
}

const form = useForm({
    school_class_id: '',
    name:            '',
});

function submit() {
    form.post(`/school-admin/${props.school.id}/students`);
}
</script>
