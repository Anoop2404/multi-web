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

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
            <div class="card card--muted text-center !py-4">
                <p class="text-xs uppercase font-bold text-slate-500 tracking-wide">Total recorded</p>
                <p class="text-2xl font-bold text-slate-900 mt-1">₹{{ fmt(summary?.total) }}</p>
            </div>
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
                <p class="text-xs uppercase font-bold text-violet-700 tracking-wide">MCQ exams</p>
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
                        <span v-if="p.payment_date">{{ p.payment_date }}</span>
                        <span v-if="p.transaction_ref">Ref: {{ p.transaction_ref }}</span>
                        <span v-if="p.receipt_number" class="font-mono text-indigo-700">#{{ p.receipt_number }}</span>
                    </div>
                    <p v-if="p.rejection_reason" class="text-xs text-red-600 mt-1">
                        Rejected: {{ p.rejection_reason }}
                    </p>
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
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({
    school: Object,
    payments: { type: Array, default: () => [] },
    summary: { type: Object, default: () => ({}) },
});

const activeTab = ref('all');

const tabs = computed(() => [
    { key: 'all', label: 'All', count: props.payments.length },
    { key: 'membership', label: 'Membership', count: props.payments.filter(p => p.type === 'membership').length },
    { key: 'fest', label: 'Events', count: props.payments.filter(p => p.type === 'fest').length },
    { key: 'training', label: 'Training', count: props.payments.filter(p => p.type === 'training').length },
    { key: 'mcq', label: 'MCQ Exams', count: props.payments.filter(p => p.type === 'mcq').length },
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
    return { membership: 'Membership', fest: 'Event fee', training: 'Training', mcq: 'MCQ Exam' }[type] ?? type;
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
        verified:  'bg-green-50 text-green-700',
        approved:  'bg-green-50 text-green-700',
        submitted: 'bg-yellow-50 text-yellow-700',
        uploaded:  'bg-yellow-50 text-yellow-700',
        rejected:  'bg-red-50 text-red-600',
        pending:   'bg-gray-100 text-gray-600',
    }[status] ?? 'bg-gray-100 text-gray-600';
}
</script>
