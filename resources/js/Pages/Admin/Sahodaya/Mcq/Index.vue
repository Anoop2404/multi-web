<template>
    <SahodayaAdminLayout title="MCQ Exams" :sahodaya="sahodaya" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount">
        <form @submit.prevent="createExam" class="bg-white border rounded-xl p-4 mb-4 flex gap-2">
            <input v-model="form.title" class="field flex-1" placeholder="Exam title" required>
            <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Create</button>
        </form>
        <ul class="bg-white border rounded-xl divide-y">
            <li v-for="exam in exams" :key="exam.id" class="p-4 flex justify-between">
                <span>{{ exam.title }} <span class="text-xs text-gray-400">({{ exam.status }})</span></span>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}`" class="text-indigo-600 text-sm">Open</Link>
            </li>
        </ul>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({ sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, exams: Array });
const form = useForm({ title: '' });
function createExam() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams`, { preserveScroll: true, onSuccess: () => form.reset() });
}
</script>
<style scoped>
@reference "../../../../../css/app.css";
.field { @apply border border-gray-200 rounded-lg px-3 py-2 text-sm; }
</style>
