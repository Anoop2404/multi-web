<template>
    <SahodayaAdminLayout title="Talent Search payments" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Talent Search payments queue" eyebrow="Talent Search exams"
                    description="Approve school batch fee proofs across all exams without opening each exam workspace." />

        <div class="flex flex-wrap gap-2 mb-6">
            <Link v-for="tab in statusTabs" :key="tab.key"
                  :href="`/sahodaya-admin/${sahodaya.id}/mcq/payments?status=${tab.key}`"
                  :class="activeStatus === tab.key ? 'subnav-link subnav-link--active' : 'subnav-link'">
                {{ tab.label }} ({{ statusCounts[tab.key] ?? 0 }})
            </Link>
        </div>

        <div class="card card--flush overflow-hidden">
            <EmptyState v-if="!fees.data?.length" title="No payments in this queue"
                        :description="activeStatus === 'pending' ? 'All caught up — no batch fees awaiting approval.' : 'No records for this filter.'" icon="💳" class="py-10" />
            <div v-else class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Exam</th>
                            <th>School</th>
                            <th>Students</th>
                            <th>Amount</th>
                            <th>Uploaded</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="fee in fees.data" :key="fee.id">
                            <td>
                                <Link :href="fee.payments_url" class="link-brand font-medium">{{ fee.exam_title }}</Link>
                                <p v-if="fee.exam_level > 1" class="text-[10px] text-indigo-700">Level {{ fee.exam_level }}</p>
                            </td>
                            <td>{{ fee.school_name }}</td>
                            <td>{{ fee.student_count }}</td>
                            <td class="font-semibold">₹{{ fee.total_due }}</td>
                            <td class="text-xs whitespace-nowrap">{{ formatDateTime(fee.updated_at) }}</td>
                            <td class="text-xs whitespace-nowrap text-right space-x-2">
                                <a v-if="fee.fee_receipt?.proof_url" :href="fee.fee_receipt.proof_url" target="_blank" rel="noopener" class="link-brand">Proof</a>
                                <button v-if="fee.fee_receipt?.status === 'uploaded'" type="button" @click="approve(fee.id)" class="text-green-700 font-semibold">Approve</button>
                                <button v-if="fee.fee_receipt?.status === 'uploaded'" type="button" @click="reject(fee.id)" class="text-red-700 font-semibold">Reject</button>
                                <span v-else-if="fee.fee_receipt?.status === 'rejected'" class="text-red-700" :title="fee.fee_receipt?.rejection_reason">Rejected</span>
                                <span v-else-if="fee.status === 'approved'" class="text-green-700 font-semibold">Approved</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="fees.links?.length > 3" class="mt-4 flex justify-center gap-1">
            <Link v-for="link in fees.links" :key="link.label" :href="link.url || '#'" v-html="link.label"
                  :class="['px-3 py-1 text-sm rounded', link.active ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-100', !link.url ? 'opacity-40 pointer-events-none' : '']" />
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { formatDateTime } from '@/support/calendarDates.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    fees: Object,
    activeStatus: { type: String, default: 'pending' },
    statusCounts: { type: Object, default: () => ({}) },
});

const statusTabs = [
    { key: 'pending', label: 'Pending' },
    { key: 'approved', label: 'Approved' },
    { key: 'rejected', label: 'Rejected' },
    { key: 'all', label: 'All' },
];

function approve(schoolFeeId) {
    if (!confirm('Approve this batch fee and issue hall tickets for all registered students from this school?')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq/payments/${schoolFeeId}/approve`, {}, { preserveScroll: true });
}

function reject(schoolFeeId) {
    const reason = prompt('Rejection reason for the school:');
    if (!reason?.trim()) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq/payments/${schoolFeeId}/reject`, {
        rejection_reason: reason.trim(),
    }, { preserveScroll: true });
}
</script>
