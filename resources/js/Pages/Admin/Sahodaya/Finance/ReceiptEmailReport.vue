<template>
    <SahodayaAdminLayout title="Receipt email delivery" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Receipt email delivery" eyebrow="Finance"
                    description="Track whether approved payment receipts were emailed to schools.">
            <template #actions>
                <Link :href="paymentsUrl" class="btn-secondary text-sm">All payments</Link>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
            <div class="card card--muted text-center !py-3">
                <p class="text-xs uppercase text-green-700 font-semibold">Sent</p>
                <p class="text-xl font-bold">{{ counts.sent ?? 0 }}</p>
            </div>
            <div class="card card--muted text-center !py-3">
                <p class="text-xs uppercase text-red-700 font-semibold">Failed</p>
                <p class="text-xl font-bold">{{ counts.failed ?? 0 }}</p>
            </div>
            <div class="card card--muted text-center !py-3">
                <p class="text-xs uppercase text-amber-700 font-semibold">Skipped</p>
                <p class="text-xl font-bold">{{ counts.skipped ?? 0 }}</p>
            </div>
            <div class="card card--muted text-center !py-3">
                <p class="text-xs uppercase text-slate-600 font-semibold">Pending</p>
                <p class="text-xl font-bold">{{ counts.pending ?? 0 }}</p>
            </div>
        </div>

        <form class="mb-4 flex gap-2 items-end" @submit.prevent="applyFilter">
            <div>
                <label class="form-label text-xs">Email status</label>
                <select v-model="status" class="form-input text-sm">
                    <option value="all">All</option>
                    <option value="sent">Sent</option>
                    <option value="failed">Failed</option>
                    <option value="skipped">Skipped</option>
                    <option value="pending">Pending</option>
                </select>
            </div>
            <button type="submit" class="btn-primary text-sm">Filter</button>
        </form>

        <div class="card overflow-x-auto">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Receipt #</th>
                        <th>Amount</th>
                        <th>Verified</th>
                        <th>Email status</th>
                        <th>Emailed at</th>
                        <th>Resends</th>
                        <th>Error</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in receipts" :key="r.id">
                        <td class="font-mono text-sm">{{ r.receipt_number || '—' }}</td>
                        <td>₹{{ fmt(r.amount) }}</td>
                        <td class="text-xs">{{ formatDateTime(r.reviewed_at) }}</td>
                        <td>
                            <span class="text-xs font-semibold px-2 py-0.5 rounded" :class="statusClass(r.receipt_email_status)">
                                {{ r.receipt_email_status }}
                            </span>
                        </td>
                        <td class="text-xs">{{ formatDateTime(r.receipt_emailed_at) }}</td>
                        <td>{{ r.resend_count }}</td>
                        <td class="text-xs text-red-600 max-w-xs truncate">{{ r.receipt_email_error || '—' }}</td>
                        <td>
                            <button v-if="r.receipt_email_status === 'failed' || r.receipt_email_status === 'skipped'"
                                    type="button" class="text-xs font-semibold text-[#0f3d7a] hover:underline"
                                    @click="resendReceipt(r.id)">
                                Resend
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatDateTime } from '@/support/calendarDates.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    receipts: Array,
    counts: Object,
    filters: Object,
});

const status = ref(props.filters?.status ?? 'all');

const paymentsUrl = computed(() => `/sahodaya-admin/${props.sahodaya.id}/finance/payments`);

function applyFilter() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/finance/receipt-emails`, { status: status.value }, {
        preserveState: true,
        replace: true,
    });
}

function fmt(n) {
    return Number(n ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
}

function statusClass(s) {
    if (s === 'sent') return 'bg-green-100 text-green-800';
    if (s === 'failed') return 'bg-red-100 text-red-800';
    if (s === 'skipped') return 'bg-amber-100 text-amber-800';
    return 'bg-slate-100 text-slate-600';
}

function resendReceipt(id) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/finance/receipt-emails/${id}/resend`, {}, { preserveScroll: true });
}
</script>
