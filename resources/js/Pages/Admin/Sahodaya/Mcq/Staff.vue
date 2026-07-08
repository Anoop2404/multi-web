<template>
    <SahodayaAdminLayout :title="`Exam Staff — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Talent Search exam" description="Assign hall staff and exam controllers." />
        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" :delivery-mode="exam.delivery_mode || 'offline'" :results-published="!!exam.results_published" active="staff" />

        <form @submit.prevent="assign" class="card mb-4 grid gap-3 sm:grid-cols-2">
            <FormField label="User" class-extra="sm:col-span-2" required>
                <template #default="{ id }">
                    <select :id="id" v-model="form.user_id" class="field" required>
                        <option value="">Select user</option>
                        <option v-for="u in staffPool" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
                    </select>
                </template>
            </FormField>
            <FormField label="Role on this exam">
                <template #default="{ id }">
                    <select :id="id" v-model="form.role" class="field">
                        <option value="controller">Controller</option>
                        <option value="staff">Staff</option>
                    </select>
                </template>
            </FormField>
            <div class="sm:col-span-2">
                <button type="submit" class="btn-primary" :disabled="form.processing">Assign</button>
            </div>
        </form>
        <p v-if="!staffPool.length" class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg p-3 mb-4">
            Create users with role <strong>exam_controller</strong> or <strong>exam_staff</strong> under
            <Link :href="`/sahodaya-admin/${sahodaya.id}/users`" class="font-semibold underline">Portal users</Link> first.
        </p>

        <ul class="card-list">
            <li v-for="a in assignments" :key="a.id" class="p-4 flex justify-between">
                <span>{{ a.user?.name }} <span class="text-xs text-gray-400">({{ a.role }})</span></span>
                <button @click="remove(a)" class="text-xs text-red-600">Remove</button>
            </li>
            <li v-if="!assignments.length" class="p-6 text-center text-gray-400 text-sm">No staff assigned.</li>
        </ul>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, useForm, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';

const props = defineProps({ sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, exam: Object, assignments: Array, staffPool: Array });
const form = useForm({ user_id: '', role: 'staff' });

function assign() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/staff`, { preserveScroll: true, onSuccess: () => form.reset() });
}

function remove(a) {
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/staff/${a.id}`, { preserveScroll: true });
}
</script>

