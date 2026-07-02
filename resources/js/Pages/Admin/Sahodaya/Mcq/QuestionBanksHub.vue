<template>
    <SahodayaAdminLayout title="Question banks" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Question banks" eyebrow="MCQ exams"
                    description="All question banks created by teachers across member schools. Attach banks to online exams from each exam workspace.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams`" class="btn-secondary text-sm">All exams</Link>
            </template>
        </PageHeader>

        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold">{{ stats.banks }}</p>
                <p class="text-xs text-slate-500 mt-1">Banks</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold">{{ stats.questions }}</p>
                <p class="text-xs text-slate-500 mt-1">Questions</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold text-indigo-700">{{ stats.linked }}</p>
                <p class="text-xs text-slate-500 mt-1">Linked to exams</p>
            </div>
        </div>

        <div class="card card--flush overflow-hidden">
            <EmptyState v-if="!banks.length" title="No question banks yet"
                        description="Teachers create banks from the teacher portal. Banks appear here for Sahodaya oversight." icon="📚" class="py-10" />
            <table v-else class="data-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Subject</th>
                        <th>School / teacher</th>
                        <th>Class</th>
                        <th>Questions</th>
                        <th>Exams</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="bank in banks" :key="bank.id">
                        <td class="font-medium">{{ bank.title }}</td>
                        <td>{{ bank.subject }}</td>
                        <td class="text-xs">
                            <p>{{ bank.school_name }}</p>
                            <p class="text-slate-500">{{ bank.teacher_name }}</p>
                        </td>
                        <td class="text-xs">{{ bank.class_group_label || '—' }}</td>
                        <td>{{ bank.questions_count }}</td>
                        <td>{{ bank.exams_count }}</td>
                        <td class="text-xs whitespace-nowrap">{{ bank.updated_at }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="text-xs text-slate-500 mt-4">
            Teachers manage bank content in the
            <a href="/portal/login" target="_blank" rel="noopener" class="link-brand">teacher portal</a>.
            Sahodaya attaches approved banks under each online exam’s Question banks tab.
        </p>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    banks: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({}) },
});
</script>
