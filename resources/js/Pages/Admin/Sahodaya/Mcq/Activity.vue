<template>
    <SahodayaAdminLayout :title="`${exam.title} — Activity`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${exam.title} — Activity log`" eyebrow="Audit trail"
                    description="All actions on this MCQ exam, newest first." />

        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" :delivery-mode="exam.delivery_mode || 'offline'" :results-published="!!exam.results_published" active="activity" />

        <div class="form-section overflow-hidden !p-0">
            <EmptyState v-if="!activityLogs.length" title="No activity yet"
                        description="Exam actions will appear here." icon="📋" class="p-8" />
            <div v-else class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-36">When</th>
                            <th>Action</th>
                            <th class="w-32">User</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="log in activityLogs" :key="log.id">
                            <td class="text-xs text-slate-500">{{ formatTime(log.created_at) }}</td>
                            <td class="text-sm text-slate-800">{{ log.description }}</td>
                            <td class="text-xs text-slate-500">{{ log.user?.name ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    exam: Object,
    activityLogs: { type: Array, default: () => [] },
});

function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso.replace(' ', 'T'));
    return d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>
