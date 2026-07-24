<template>
    <SchoolAdminLayout title="Payments & Receipts" :school="school" :show-header-title="false">
        <PageHeader
            title="Payments & Receipts"
            eyebrow="Membership"
            description="Membership, event registration fees, and training payments — grouped by type."
        >
            <template #actions>
                <a :href="exportUrl" class="btn-secondary text-sm">Export CSV ↓</a>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-3">
            <div class="card card--muted text-center !py-4">
                <p class="text-xs uppercase font-bold text-emerald-700 tracking-wide">Total paid</p>
                <p class="text-2xl font-bold text-emerald-900 mt-1">₹{{ fmt(summary?.total_paid) }}</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-xs uppercase font-bold text-amber-700 tracking-wide">Outstanding</p>
                <p class="text-2xl font-bold text-amber-900 mt-1">₹{{ fmt(summary?.outstanding) }}</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-xs uppercase font-bold text-slate-500 tracking-wide" title="Sum of every listed amount, including totals still owed and rejected items — not the same as Total paid.">Total recorded</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">₹{{ fmt(summary?.total) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <div class="card card--muted text-center !py-4">
                <p class="text-xs uppercase font-bold text-blue-700 tracking-wide">Membership</p>
                <p class="text-2xl font-bold text-blue-900 mt-1">₹{{ fmt(summary?.membership) }}</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-xs uppercase font-bold text-purple-700 tracking-wide">Events</p>
                <p class="text-2xl font-bold text-purple-900 mt-1">₹{{ fmt(summary?.fest) }}</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-xs uppercase font-bold text-emerald-700 tracking-wide">Training</p>
                <p class="text-2xl font-bold text-emerald-900 mt-1">₹{{ fmt(summary?.training) }}</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-xs uppercase font-bold text-violet-700 tracking-wide">Talent Search exams</p>
                <p class="text-2xl font-bold text-violet-900 mt-1">₹{{ fmt(summary?.mcq) }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 mb-4">
            <button v-for="tab in tabs" :key="tab.key" type="button"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition"
                    :class="activeTab === tab.key ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'bg-white text-slate-600 border-slate-200 hover:border-slate-300'"
                    @click="activeTab = tab.key">
                {{ tab.label }}
                <span class="ml-1 opacity-70">({{ tab.count }})</span>
            </button>
        </div>

        <div v-if="!filteredPayments.length" class="card text-center py-16 text-slate-400">
            <p class="text-3xl mb-2">💳</p>
            <p>No payment records in this category.</p>
        </div>

        <div v-else class="space-y-3">
            <div v-for="p in filteredPayments" :key="`${p.type}-${p.id}`"
                 class="card flex flex-wrap items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span class="text-xs font-bold uppercase px-2 py-0.5 rounded"
                              :class="typeClass(p.type)">
                            {{ typeLabel(p.type) }}
                        </span>
                        <span class="text-sm font-semibold text-gray-900">{{ p.label }}</span>
                        <span v-if="p.level_label" class="text-xs text-indigo-600">({{ p.level_label }})</span>
                    </div>
                    <div class="text-xs text-gray-500 space-x-3">
                        <span v-if="p.payment_date">{{ formatCalendarDate(p.payment_date) }}</span>
                        <span v-if="p.transaction_ref">Ref: {{ p.transaction_ref }}</span>
                        <span v-if="p.receipt_number" class="font-mono text-indigo-700">#{{ p.receipt_number }}</span>
                    </div>
                    <p v-if="p.rejection_reason" class="text-xs text-red-600 mt-1">
                        Rejected: {{ p.rejection_reason }}
                    </p>
                    <!-- Fest-only, see docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §14 —
                         money owed back after a paid item was rejected/cancelled. -->
                    <p v-if="p.available_credit > 0" class="text-xs text-emerald-700 font-semibold mt-1">
                        ₹{{ fmt(p.available_credit) }} credit owed to you
                    </p>

                    <button v-if="p.receipts_history && p.receipts_history.length > 1"
                            type="button"
                            class="text-xs text-indigo-600 hover:text-indigo-800 font-semibold mt-2 flex items-center gap-1"
                            @click="toggleExpand(p.id)">
                        <span>{{ expanded[p.id] ? 'Hide' : 'Show' }} payment history ({{ p.receipts_history.length }} attempts)</span>
                        <span class="text-[10px]">{{ expanded[p.id] ? '▲' : '▼' }}</span>
                    </button>

                    <div v-if="expanded[p.id] && p.receipts_history" class="mt-3 pl-3 border-l-2 border-slate-200 space-y-2 w-full">
                        <div v-for="r in p.receipts_history" :key="r.id" class="text-xs text-slate-600 flex flex-wrap items-center justify-between gap-2 bg-slate-50 p-2 rounded">
                            <div>
                                <span class="font-mono text-indigo-700 mr-2" v-if="r.receipt_number">#{{ r.receipt_number }}</span>
                                <span class="text-[10px] uppercase font-semibold px-1.5 py-0.5 rounded mr-2" :class="statusClass(r.status)">{{ r.status === 'credit' ? 'credit issued' : r.status }}</span>
                                <span class="font-semibold">₹{{ fmt(r.amount) }}</span>
                                <span v-if="r.payment_date" class="text-slate-400 ml-2">({{ formatCalendarDate(r.payment_date) }})</span>
                                <div v-if="r.rejection_reason" class="text-red-600 mt-0.5 font-medium">Rejected: {{ r.rejection_reason }}</div>
                                <div v-if="r.reversal_reason" class="text-red-600 mt-0.5 font-medium">Reversed: {{ r.reversal_reason }}</div>
                                <!-- Money owed BACK after a rejected/cancelled paid item — not a
                                     receipt. See docs/FLOW_GAP_FIX_PLAN.md Phase 3b.2. -->
                                <template v-if="r.status === 'credit'">
                                    <div class="text-amber-700 mt-0.5 font-medium">{{ r.credit_reason || 'Fee credit issued' }}</div>
                                    <div class="text-slate-400 mt-0.5">
                                        {{ r.applied_at ? 'Applied ' + formatCalendarDate(r.applied_at) : 'Outstanding — applies to a future fee' }}
                                    </div>
                                </template>
                            </div>
                            <div class="flex items-center gap-2">
                                <a v-if="r.receipt_url" :href="r.receipt_url" target="_blank" rel="noopener" class="text-indigo-600 font-semibold hover:underline">Receipt ↗</a>
                                <a v-if="r.proof_url" :href="r.proof_url" target="_blank" rel="noopener" class="text-slate-600 font-semibold hover:underline">Proof ↗</a>
                                <a v-if="r.credit_note_url" :href="r.credit_note_url" target="_blank" rel="noopener" class="text-amber-700 font-semibold hover:underline">Credit note ↗</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="text-right">
                        <p class="font-bold text-gray-900">₹{{ fmt(p.amount) }}</p>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded" :class="statusClass(p.status)">
                            {{ p.status }}
                        </span>
                    </div>
                    <a v-if="p.receipt_url" :href="p.receipt_url" target="_blank" rel="noopener"
                       class="btn-primary text-xs !py-1.5 !px-3">
                        Receipt ↗
                    </a>
                    <button v-if="p.proof_url" type="button"
                            class="btn-secondary text-xs !py-1.5 !px-3"
                            @click="openProofPreview(p)">
                        Proof
                    </button>
                </div>
            </div>
        </div>

        <div v-if="proofPreview" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-[#041525]/70" @click="closeProofPreview"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-5xl h-[85vh] overflow-hidden flex flex-col">
                <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Payment proof</p>
                        <h3 class="font-bold text-slate-900 truncate">{{ proofPreview.label }}</h3>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a :href="proofPreview.proof_url" target="_blank" rel="noopener" class="btn-secondary text-xs">
                            Open in new tab
                        </a>
                        <button type="button" class="btn-ghost text-sm" @click="closeProofPreview">Close</button>
                    </div>
                </div>
                <iframe :src="proofPreviewUrl"
                        class="w-full flex-1 bg-slate-50"
                        title="Payment proof preview"></iframe>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { formatCalendarDate } from '@/support/calendarDates.js';

const props = defineProps({
    school: Object,
    payments: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
});

const activeTab = ref('all');
const expanded = ref({});
const proofPreview = ref(null);
const proofPreviewUrl = computed(() => proofPreview.value?.proof_url ? withPreview(proofPreview.value.proof_url) : null);

function toggleExpand(id) {
    expanded.value = { ...expanded.value, [id]: !expanded.value[id] };
}

const tabs = computed(() => [
    { key: 'all', label: 'All', count: props.payments.length },
    { key: 'membership', label: 'Membership', count: props.payments.filter(p => p.type === 'membership').length },
    { key: 'fest', label: 'Events', count: props.payments.filter(p => p.type === 'fest').length },
    { key: 'training', label: 'Training', count: props.payments.filter(p => p.type === 'training').length },
    { key: 'mcq', label: 'Talent Search Exams', count: props.payments.filter(p => p.type === 'mcq').length },
]);

const filteredPayments = computed(() => {
    if (activeTab.value === 'all') return props.payments;
    return props.payments.filter(p => p.type === activeTab.value);
});

const exportUrl = computed(() => `/school-admin/${props.school.id}/payments/export`);

function fmt(v) {
    return Number(v ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function typeLabel(type) {
    return { membership: 'Membership', fest: 'Event fee', training: 'Training', mcq: 'Talent Search Exam' }[type] ?? type;
}

function typeClass(type) {
    return {
        membership: 'bg-blue-50 text-blue-700',
        fest: 'bg-purple-50 text-purple-700',
        training: 'bg-emerald-50 text-emerald-700',
        mcq: 'bg-violet-50 text-violet-700',
    }[type] ?? 'bg-gray-100 text-gray-600';
}

function statusClass(status) {
    return {
        verified:   'bg-green-50 text-green-700 font-semibold',
        approved:   'bg-green-50 text-green-700 font-semibold',
        submitted:  'bg-amber-50 text-amber-700 font-semibold',
        uploaded:   'bg-amber-50 text-amber-700 font-semibold',
        rejected:   'bg-rose-50 text-rose-700 font-semibold',
        reversed:   'bg-red-100 text-red-800 line-through font-semibold',
        superseded: 'bg-slate-100 text-slate-500 line-through',
        partial:    'bg-amber-100 text-amber-800 font-semibold',
        waived:     'bg-sky-50 text-sky-700 font-semibold',
        credit:     'bg-emerald-50 text-emerald-700 font-semibold',
        cancelled:  'bg-slate-100 text-slate-600 font-semibold',
        pending:    'bg-slate-100 text-slate-600',
    }[status] ?? 'bg-slate-100 text-slate-600';
}

function openProofPreview(payment) {
    proofPreview.value = payment;
}

function closeProofPreview() {
    proofPreview.value = null;
}

function withPreview(url) {
    return `${url}${url.includes('?') ? '&' : '?'}preview=1`;
}
</script>
