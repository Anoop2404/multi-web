<template>
    <SchoolAdminLayout title="Submission Teachers" :school="school">
        <div class="max-w-3xl space-y-4">
            <Link :href="`/school-admin/${school.id}/registration`" class="text-sm text-blue-600">← Registration</Link>
            <p class="text-sm text-gray-500">Status: {{ submission.teacher_status }}</p>

            <form v-if="['pending','rejected'].includes(submission.teacher_status)" @submit.prevent="add" class="bg-white border rounded-xl p-4 flex gap-3">
                <input v-model="form.name" required placeholder="Name" class="flex-1 border rounded-lg px-3 py-2 text-sm">
                <input v-model="form.subject" placeholder="Subject" class="flex-1 border rounded-lg px-3 py-2 text-sm">
                <select v-model="form.teaching_type_id" class="border rounded-lg px-3 py-2 text-sm">
                    <option value="">Type</option>
                    <option v-for="t in teachingTypes" :key="t.id" :value="t.id">{{ t.label }}</option>
                </select>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm">Add</button>
            </form>

            <ul class="bg-white border rounded-xl divide-y text-sm">
                <li v-for="t in teachers" :key="t.id" class="px-4 py-3 flex justify-between">
                    <span>{{ t.name }} — {{ t.subject || '—' }} ({{ t.teaching_type?.label || '—' }})</span>
                    <button @click="remove(t)" class="text-red-400 text-xs">Remove</button>
                </li>
            </ul>

            <button v-if="['pending','rejected'].includes(submission.teacher_status)" @click="submit"
                    class="bg-purple-600 text-white px-4 py-2 rounded-lg text-sm">Submit teachers &amp; continue</button>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({ school: Object, registration: Object, submission: Object, teachers: Array, teachingTypes: Array });
const form = useForm({ name: '', subject: '', teaching_type_id: '' });

function add() { form.post(`/school-admin/${props.school.id}/registration/teachers`, { onSuccess: () => form.reset() }); }
function remove(t) { router.delete(`/school-admin/${props.school.id}/registration/teachers/${t.id}`); }
function submit() { router.post(`/school-admin/${props.school.id}/registration/submit-track`, { track: 'teachers' }); }
</script>
