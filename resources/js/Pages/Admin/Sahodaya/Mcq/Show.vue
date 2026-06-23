<template>
    <SahodayaAdminLayout :title="exam.title" :sahodaya="sahodaya" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount">
        <form @submit.prevent="save" class="bg-white border rounded-xl p-4 mb-4 space-y-2">
            <input v-model="form.title" class="field" required>
            <select v-model="form.status" class="field">
                <option value="draft">Draft</option>
                <option value="published">Published</option>
                <option value="completed">Completed</option>
            </select>
            <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Save</button>
        </form>
        <p class="text-sm text-gray-500 mb-2">{{ registrations.length }} registrations (offline mark entry)</p>
    </SahodayaAdminLayout>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({ sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, exam: Object, registrations: Array });
const form = useForm({ title: props.exam.title, status: props.exam.status });
function save() { form.put(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}`, { preserveScroll: true }); }
</script>
<style scoped>
@reference "../../../../../css/app.css";
.field { @apply w-full border border-gray-200 rounded-lg px-3 py-2 text-sm; }
</style>
