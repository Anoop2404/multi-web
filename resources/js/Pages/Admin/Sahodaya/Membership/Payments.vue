<template>
    <SahodayaAdminLayout title="Payment Verification" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :approvedSchoolsCount="approvedSchoolsCount"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="space-y-5">
            <p class="text-sm text-gray-600 bg-blue-50 border border-blue-100 rounded-xl px-4 py-3">
                Verifying a payment completes annual registration. For new schools, it also
                <strong>approves membership</strong> automatically.
            </p>

            <!-- Fee totals -->
            <div class="grid sm:grid-cols-3 gap-3">
                <SummaryCard label="Pending Approval Fees"
                             :value="`₹${Number(summary.pending_amount || 0).toLocaleString('en-IN')}`"
                             :hint="`${summary.pending ?? 0} payment${(summary.pending ?? 0) === 1 ? '' : 's'} awaiting verification`"
                             color="amber" />
                <SummaryCard label="Approved Fees"
                             :value="`₹${Number(summary.approved_amount || summary.collected || 0).toLocaleString('en-IN')}`"
                             :hint="`${summary.verified ?? 0} verified payment${(summary.verified ?? 0) === 1 ? '' : 's'}`"
                             color="green" />
                <SummaryCard label="Payment Not Done"
                             :value="`₹${Number(summary.payment_due_amount || 0).toLocaleString('en-IN')}`"
                             :hint="`${summary.payment_due ?? 0} school${(summary.payment_due ?? 0) === 1 ? '' : 's'} not paid yet`"
                             color="navy" />
            </div>

            <!-- Counts -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <SummaryCard label="Pending" :value="summary.pending" color="amber" />
                <SummaryCard label="Verified" :value="summary.verified" color="green" />
                <SummaryCard label="Rejected" :value="summary.rejected" color="red" />
                <SummaryCard label="Payment Due" :value="summary.payment_due ?? 0" color="navy" />
            </div>

            <!-- Status tabs -->
            <div class="flex flex-wrap gap-2">
                <button v-for="tab in statusTabs" :key="tab.key"
                        @click="switchStatus(tab.key)"
                        :class="['px-4 py-2 rounded-xl text-sm font-semibold border transition',
                                 activeStatus === tab.key
                                     ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]'
                                     : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300']">
                    {{ tab.label }}
                    <span v-if="statusCounts[tab.key] > 0"
                          :class="['ml-1.5 text-xs px-1.5 py-0.5 rounded-full',
                                   activeStatus === tab.key ? 'bg-white/20' : 'bg-gray-100']">
                        {{ statusCounts[tab.key] }}
                    </span>
                </button>
            </div>

            <!-- Search & date -->
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[200px] max-w-md">
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">Search school</label>
                    <input v-model="filterForm.search" type="search" placeholder="School name or prefix…"
                           @keyup.enter="applyFilters"
                           class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#0f3d7a]/20">
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">From</label>
                    <input v-model="filterForm.date_from" type="date"
                           class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-500 mb-1 block">To</label>
                    <input v-model="filterForm.date_to" type="date"
                           class="border border-gray-200 rounded-xl px-3 py-2.5 text-sm">
                </div>
                <button @click="applyFilters"
                        class="bg-[#0f3d7a] hover:bg-[#1a4f8c] text-white px-5 py-2.5 rounded-xl text-sm font-semibold">
                    Apply
                </button>
                <a :href="exportUrl()"
                   class="ml-auto inline-flex items-center px-4 py-2.5 rounded-xl bg-[#eff6ff] hover:bg-[#dbeafe] text-[#0f3d7a] border border-[#bfdbfe] text-sm font-semibold transition">
                    Download Excel ↓
                </a>
            </div>

            <!-- Payments list -->
            <div v-if="activeStatus === 'payment-due' && paymentDue?.data?.length" class="space-y-4">
                <div v-for="r in paymentDue.data" :key="r.id"
                     class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 flex items-start justify-between gap-4">
                        <div class="flex items-center gap-4 min-w-0">
                            <div class="w-11 h-11 rounded-xl bg-amber-50 flex items-center justify-center text-xl font-bold text-amber-700 shrink-0">
                                {{ r.school?.name?.charAt(0) }}
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-gray-900">{{ r.school?.name }}</p>
                                <div class="flex flex-wrap items-center gap-2 mt-0.5 text-xs text-gray-500">
                                    <span>{{ r.academic_year }}</span>
                                    <span v-if="r.reg_no" class="text-gray-300">·</span>
                                    <span v-if="r.reg_no" class="font-mono">{{ r.reg_no }}</span>
                                    <span v-if="r.school?.school_prefix" class="text-gray-300">·</span>
                                    <span v-if="r.school?.school_prefix">{{ r.school.school_prefix }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <p v-if="r.membership_fee_amount" class="text-xl font-extrabold text-gray-900">
                                ₹{{ Number(r.membership_fee_amount).toLocaleString('en-IN') }}
                            </p>
                            <StatusBadge :status="r.registration_status" />
                        </div>
                    </div>
                    <div class="px-6 py-3 bg-amber-50/60 border-t border-amber-100 text-xs text-amber-800">
                        Registered for {{ r.academic_year }} — awaiting payment upload from school.
                    </div>
                </div>
            </div>

            <div v-else-if="payments.data?.length" class="space-y-4">
                <div v-for="p in payments.data" :key="p.id"
                     class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 flex items-start justify-between gap-4 border-b border-gray-100">
                        <div class="flex items-center gap-4 min-w-0">
                            <div class="w-11 h-11 rounded-xl bg-green-50 flex items-center justify-center text-xl font-bold text-green-700 shrink-0">
                                {{ p.school?.name?.charAt(0) }}
                            </div>
                            <div class="min-w-0">
                                <p class="font-bold text-gray-900">{{ p.school?.name }}</p>
                                <div class="flex flex-wrap items-center gap-2 mt-0.5 text-xs text-gray-500">
                                    <span>{{ p.academic_year }}</span>
                                    <span class="text-gray-300">·</span>
                                    <span>{{ formatDate(p.created_at) }}</span>
                                    <span v-if="p.payment_method" class="text-gray-300">·</span>
                                    <span v-if="p.payment_method" class="capitalize">{{ p.payment_method.replace('_', ' ') }}</span>
                                    <span v-if="p.transaction_ref" class="text-gray-300">·</span>
                                    <span v-if="p.transaction_ref" class="font-mono text-gray-600">{{ p.transaction_ref }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="text-right shrink-0">
                            <p class="text-xl font-extrabold text-gray-900">₹{{ Number(p.amount).toLocaleString('en-IN') }}</p>
                            <StatusBadge :status="p.status" />
                        </div>
                    </div>

                    <div class="px-6 py-4 flex flex-col sm:flex-row items-start gap-6">
                        <div v-if="p.proof_url" class="shrink-0">
                            <p class="text-xs font-semibold text-gray-500 mb-2">Payment Proof</p>
                            <a :href="p.proof_url" target="_blank" rel="noopener"
                               class="flex items-center gap-2 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-[#0f3d7a] font-semibold hover:bg-blue-50 transition">
                                📎 View upload ↗
                            </a>
                        </div>

                        <div v-if="p.status === 'submitted'" class="flex-1 flex flex-wrap items-center gap-2 w-full">
                            <button type="button" @click="verifyPayment(p)"
                                    class="px-5 py-2 rounded-xl bg-green-600 hover:bg-green-700 text-white text-sm font-bold transition">
                                Verify
                            </button>
                            <button type="button" @click="rejectPayment(p)"
                                    class="px-5 py-2 rounded-xl bg-red-600 hover:bg-red-700 text-white text-sm font-bold transition">
                                Reject
                            </button>
                        </div>

                        <div v-else-if="p.rejection_reason" class="flex-1 text-sm text-red-600 bg-red-50 border border-red-100 rounded-xl px-4 py-3">
                            <span class="font-semibold">Rejected:</span> {{ p.rejection_reason }}
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="bg-white rounded-2xl border border-dashed border-gray-200 p-16 text-center">
                <div class="text-5xl mb-3">💳</div>
                <p class="text-gray-600 font-semibold">{{ emptyMessage }}</p>
            </div>

            <div v-if="payments.links?.length > 3 && activeStatus !== 'payment-due'" class="flex justify-center gap-1">
                <Link v-for="link in payments.links" :key="link.label"
                      :href="link.url || '#'"
                      class="px-3 py-1 rounded-lg text-sm"
                      :class="link.active ? 'bg-[#0f3d7a] text-white' : 'text-gray-600 hover:bg-gray-100'"
                      v-html="link.label" />
            </div>
            <div v-if="paymentDue?.links?.length > 3 && activeStatus === 'payment-due'" class="flex justify-center gap-1">
                <Link v-for="link in paymentDue.links" :key="link.label"
                      :href="link.url || '#'"
                      class="px-3 py-1 rounded-lg text-sm"
                      :class="link.active ? 'bg-[#0f3d7a] text-white' : 'text-gray-600 hover:bg-gray-100'"
                      v-html="link.label" />
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { reactive, computed, defineComponent, h } from 'vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String,
    approvedSchoolsCount: Number, pendingSchoolsCount: Number,
    pendingSubmissionsCount: Number, pendingPaymentsCount: Number,
    payments: { type: Object, default: () => ({ data: [] }) },
    paymentDue: { type: Object, default: null },
    activeStatus: { type: String, default: 'submitted' },
    statusCounts: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
});

const filterForm = reactive({
    search:    props.filters?.search ?? '',
    date_from: props.filters?.dateFrom ?? props.filters?.date_from ?? '',
    date_to:   props.filters?.dateTo ?? props.filters?.date_to ?? '',
});

const statusTabs = [
    { key: 'payment-due', label: 'Payment Due' },
    { key: 'submitted', label: 'Pending' },
    { key: 'verified',  label: 'Verified' },
    { key: 'rejected',  label: 'Rejected' },
    { key: 'all',       label: 'All' },
];

const emptyMessage = computed(() => ({
    'payment-due': 'No schools awaiting payment. All registered schools have submitted payment or completed registration.',
    submitted: 'No payments awaiting verification.',
    verified:  'No verified payments yet.',
    rejected:  'No rejected payments.',
    all:       'No payments recorded.',
}[props.activeStatus] || 'No payments found.'));

function listParams(overrides = {}) {
    return {
        status:    props.activeStatus,
        search:    props.filters?.search ?? '',
        date_from: props.filters?.dateFrom ?? props.filters?.date_from ?? '',
        date_to:   props.filters?.dateTo ?? props.filters?.date_to ?? '',
        ...overrides,
    };
}

function switchStatus(status) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/membership/payments`, listParams({ status }), {
        preserveState: true, replace: true,
    });
}

function applyFilters() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/membership/payments`, listParams({
        search: filterForm.search,
        date_from: filterForm.date_from,
        date_to: filterForm.date_to,
    }), { preserveState: true, replace: true });
}

function exportUrl() {
    const params = new URLSearchParams();
    const p = listParams({
        search: filterForm.search,
        date_from: filterForm.date_from,
        date_to: filterForm.date_to,
    });
    Object.entries(p).forEach(([key, value]) => {
        if (value) params.set(key, value);
    });
    const qs = params.toString();
    return `/sahodaya-admin/${props.sahodaya.id}/membership/payments/export${qs ? `?${qs}` : ''}`;
}

function formatDate(d) {
    if (!d) return '';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function verifyPayment(payment) {
    if (! confirm(`Verify payment of ₹${Number(payment.amount).toLocaleString('en-IN')} from ${payment.school?.name}?`)) {
        return;
    }

    router.post(`/sahodaya-admin/${props.sahodaya.id}/membership/payments/${payment.id}/verify`, {
        action: 'verify',
    });
}

function rejectPayment(payment) {
    const reason = window.prompt(`Reject payment from ${payment.school?.name}? Enter a reason:`);
    if (reason === null) {
        return;
    }
    if (! reason.trim()) {
        alert('Please enter a rejection reason.');
        return;
    }

    router.post(`/sahodaya-admin/${props.sahodaya.id}/membership/payments/${payment.id}/verify`, {
        action: 'reject',
        reason: reason.trim(),
    });
}

const statusColors = {
    payment_pending: 'bg-amber-100 text-amber-700',
    payment_rejected: 'bg-red-100 text-red-700',
    submitted: 'bg-amber-100 text-amber-700',
    verified:  'bg-green-100 text-green-700',
    rejected:  'bg-red-100 text-red-700',
};

const StatusBadge = defineComponent({
    props: { status: String },
    setup(p) {
        return () => h('span', {
            class: ['inline-flex mt-1 px-2 py-0.5 rounded-full text-[10px] font-bold capitalize',
                    statusColors[p.status] || 'bg-gray-100 text-gray-600'],
        }, p.status?.replace(/_/g, ' ') ?? p.status);
    },
});

const SummaryCard = defineComponent({
    props: { label: String, value: [String, Number], color: String },
    setup(p) {
        const borders = { navy: 'border-[#dbeafe]', amber: 'border-amber-100', green: 'border-green-100', red: 'border-red-100' };
        const texts   = { navy: 'text-[#0f3d7a]', amber: 'text-amber-700', green: 'text-green-700', red: 'text-red-700' };
        return () => h('div', { class: `bg-white border ${borders[p.color]} rounded-2xl p-4 text-center` }, [
            h('p', { class: 'text-xs text-gray-500 font-medium' }, p.label),
            h('p', { class: `text-xl font-extrabold mt-1 ${texts[p.color]}` }, p.value),
        ]);
    },
});
</script>
