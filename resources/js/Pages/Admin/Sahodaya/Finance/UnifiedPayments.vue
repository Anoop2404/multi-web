<template>
    <SahodayaAdminLayout title="All payments" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="All payments" eyebrow="Finance"
                    description="Unified offline payment register across membership, fest, Talent Search, and training.">
            <template #actions>
                <a :href="exportUrl" class="btn-secondary text-sm flex items-center gap-1.5 shadow-xs">
                    <span>Export CSV</span>
                    <span aria-hidden="true">↓</span>
                </a>
            </template>
        </PageHeader>

        <!-- Executive Summary Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            <div class="card !p-4 border border-slate-200/90 bg-gradient-to-br from-slate-900 to-slate-800 text-white shadow-sm hover:shadow transition">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Total Collected</p>
                    <span class="w-7 h-7 rounded-md bg-white/10 flex items-center justify-center text-xs">💳</span>
                </div>
                <p class="text-xl lg:text-2xl font-black mt-2 tabular-nums">₹{{ fmt(summary?.total) }}</p>
                <p class="text-[11px] text-slate-400 mt-0.5">All modules combined</p>
            </div>

            <div class="card !p-4 border border-blue-200/80 bg-gradient-to-br from-blue-50/80 to-indigo-50/40 shadow-sm hover:shadow transition">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-blue-800">Membership</p>
                    <span class="w-7 h-7 rounded-md bg-blue-100/80 flex items-center justify-center text-blue-700 text-xs">🏫</span>
                </div>
                <p class="text-xl lg:text-2xl font-bold text-blue-950 mt-2 tabular-nums">₹{{ fmt(summary?.membership) }}</p>
                <p class="text-[11px] text-blue-700 mt-0.5 font-medium">Annual registration</p>
            </div>

            <div class="card !p-4 border border-purple-200/80 bg-gradient-to-br from-purple-50/80 to-fuchsia-50/40 shadow-sm hover:shadow transition">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-purple-800">Fest &amp; Sports</p>
                    <span class="w-7 h-7 rounded-md bg-purple-100/80 flex items-center justify-center text-purple-700 text-xs">🏆</span>
                </div>
                <p class="text-xl lg:text-2xl font-bold text-purple-950 mt-2 tabular-nums">₹{{ fmt(summary?.fest) }}</p>
                <p class="text-[11px] text-purple-700 mt-0.5 font-medium">Events participation</p>
                <p v-if="summary?.fest_credit > 0" class="text-[11px] text-emerald-700 mt-0.5 font-semibold">
                    ₹{{ fmt(summary.fest_credit) }} credit owed to schools
                </p>
            </div>

            <div class="card !p-4 border border-emerald-200/80 bg-gradient-to-br from-emerald-50/80 to-teal-50/40 shadow-sm hover:shadow transition">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-800">Training</p>
                    <span class="w-7 h-7 rounded-md bg-emerald-100/80 flex items-center justify-center text-emerald-700 text-xs">⏱️</span>
                </div>
                <p class="text-xl lg:text-2xl font-bold text-emerald-950 mt-2 tabular-nums">₹{{ fmt(summary?.training) }}</p>
                <p class="text-[11px] text-emerald-700 mt-0.5 font-medium">CPD teacher hours</p>
            </div>

            <div class="card !p-4 border border-violet-200/80 bg-gradient-to-br from-violet-50/80 to-indigo-50/40 shadow-sm hover:shadow transition">
                <div class="flex items-center justify-between">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-violet-800">Talent Search</p>
                    <span class="w-7 h-7 rounded-md bg-violet-100/80 flex items-center justify-center text-violet-700 text-xs">📝</span>
                </div>
                <p class="text-xl lg:text-2xl font-bold text-violet-950 mt-2 tabular-nums">₹{{ fmt(summary?.mcq) }}</p>
                <p class="text-[11px] text-violet-700 mt-0.5 font-medium">Exam registrations</p>
            </div>
        </div>

        <!-- Filter Bar -->
        <form class="card !p-4 mb-5 flex flex-wrap gap-3 items-end bg-white border border-slate-200 shadow-xs rounded-xl" @submit.prevent="applyFilters">
            <div class="flex-1 min-w-[130px]">
                <label class="form-label text-xs text-slate-600 font-semibold mb-1">Module</label>
                <select v-model="form.type" class="form-input text-sm w-full bg-slate-50 border-slate-200 rounded-lg">
                    <option value="all">All Modules</option>
                    <option value="membership">Membership</option>
                    <option value="fest">Fest &amp; Sports</option>
                    <option value="mcq">Talent Search</option>
                    <option value="training">Training</option>
                </select>
            </div>
            <div class="flex-1 min-w-[200px]">
                <label class="form-label text-xs text-slate-600 font-semibold mb-1">School</label>
                <select v-model="form.school_id" class="form-input text-sm w-full bg-slate-50 border-slate-200 rounded-lg">
                    <option value="">All member schools</option>
                    <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div class="flex-[1.5] min-w-[220px]">
                <label class="form-label text-xs text-slate-600 font-semibold mb-1">Search Payment</label>
                <input v-model="form.search" type="text" class="form-input text-sm w-full bg-slate-50 border-slate-200 rounded-lg" placeholder="Search school name, label, receipt #..." />
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="btn-primary text-sm px-5 !py-2 rounded-lg shadow-sm">Filter</button>
                <button v-if="form.type !== 'all' || form.school_id || form.search" type="button" class="btn-ghost text-xs text-slate-500 hover:text-slate-700" @click="resetFilters">
                    Clear
                </button>
            </div>
        </form>

        <!-- Payment Records Register -->
        <div v-if="!payments?.length" class="card text-center py-16 text-slate-400 bg-slate-50/50 border border-dashed border-slate-200 rounded-xl">
            <span class="text-3xl block mb-2">🔍</span>
            <p class="font-semibold text-slate-700">No payment records match your filters</p>
            <p class="text-xs text-slate-500 mt-1">Try clearing your filters or adjusting search terms.</p>
        </div>

        <div v-else class="space-y-3">
            <div v-for="p in payments" :key="`${p.type}-${p.id}`"
                 class="card !p-4 bg-white border border-slate-200/90 rounded-xl shadow-2xs hover:shadow-sm transition-all flex flex-wrap items-center justify-between gap-4">
                <div class="flex-1 min-w-[280px]">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-0.5 rounded-md border" :class="typeClass(p.type)">
                            {{ typeLabel(p.type) }}
                        </span>
                        <span class="text-sm font-bold text-slate-900">{{ p.label }}</span>
                    </div>
                    <p class="text-xs font-semibold text-slate-700 mt-0.5">{{ p.school_name }}</p>
                    <!-- Fest-only, see docs/FEST_PAYMENT_REGISTRATION_FLOW_GAPS.md §14 -->
                    <p v-if="p.type === 'fest' && p.available_credit > 0" class="text-[11px] text-emerald-700 font-semibold mt-0.5">
                        ₹{{ fmt(p.available_credit) }} credit owed
                    </p>
                    <div class="flex items-center gap-3 text-xs text-slate-500 mt-1.5 flex-wrap">
                        <span v-if="p.payment_date" class="flex items-center gap-1">
                            <span aria-hidden="true">📅</span> {{ formatCalendarDate(p.payment_date) }}
                        </span>
                        <span v-if="p.receipt_number" class="font-mono text-indigo-700 bg-indigo-50 border border-indigo-100 px-1.5 py-0.5 rounded">
                            #{{ p.receipt_number }}
                        </span>
                        <span v-if="p.receipt_email_status" class="inline-flex items-center gap-1 text-[11px]" :class="emailStatusClass(p.receipt_email_status)">
                            <span class="w-1.5 h-1.5 rounded-full" :class="p.receipt_email_status === 'sent' ? 'bg-emerald-500' : p.receipt_email_status === 'failed' ? 'bg-red-500' : 'bg-slate-400'"></span>
                            Email: {{ p.receipt_email_status }}
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <div class="text-right pr-2 border-r border-slate-100">
                        <p class="text-lg font-black text-slate-900 tabular-nums">₹{{ fmt(p.amount) }}</p>
                        <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md inline-block mt-0.5" :class="statusClass(p.status)">
                            {{ p.status }}
                        </span>
                    </div>

                    <div class="flex items-center gap-1.5 flex-wrap">
                        <a v-if="p.receipt_url" :href="p.receipt_url" target="_blank" rel="noopener"
                           class="btn-secondary text-xs !py-1.5 !px-3 font-semibold text-slate-700 border-slate-200 hover:bg-slate-50 shadow-2xs">
                            Receipt
                        </a>
                        <button v-if="canResend(p)" type="button" 
                                class="btn-secondary text-xs !py-1.5 !px-3 font-semibold text-slate-700 border-slate-200 hover:bg-slate-50 shadow-2xs"
                                :disabled="resending === rowKey(p)" @click="resend(p)">
                            {{ resending === rowKey(p) ? 'Sending…' : 'Resend Email' }}
                        </button>
                        <button v-if="canReverse(p)" type="button" 
                                class="btn-secondary text-xs !py-1.5 !px-3 font-semibold text-red-700 border-red-200 bg-red-50/40 hover:bg-red-100/60 shadow-2xs"
                                :disabled="reversing === rowKey(p)" @click="reverseReceipt(p)">
                            {{ reversing === rowKey(p) ? 'Reversing…' : 'Reverse' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { formatCalendarDate } from '@/support/calendarDates.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    payments: Array,
    summary: Object,
    schools: Array,
    filters: Object,
});

const page = usePage();
const resending = ref(null);
const reversing = ref(null);

const form = reactive({
    type: props.filters?.type ?? 'all',
    school_id: props.filters?.school_id ?? '',
    search: props.filters?.search ?? '',
});

const exportUrl = computed(() => {
    const base = `/sahodaya-admin/${props.sahodaya.id}/finance/payments/export`;
    return base;
});

function applyFilters() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/finance/payments`, { ...form }, {
        preserveState: true,
        replace: true,
    });
}

function resetFilters() {
    form.type = 'all';
    form.school_id = '';
    form.search = '';
    applyFilters();
}

function fmt(n) {
    return Number(n ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function typeLabel(type) {
    return {
        membership: 'Membership',
        fest: 'Fest & Sports',
        training: 'Training',
        mcq: 'Talent Search',
    }[type] ?? type;
}

function typeClass(type) {
    return {
        membership: 'bg-blue-50 text-blue-800 border-blue-200',
        fest: 'bg-purple-50 text-purple-800 border-purple-200',
        training: 'bg-emerald-50 text-emerald-800 border-emerald-200',
        mcq: 'bg-violet-50 text-violet-800 border-violet-200',
    }[type] ?? 'bg-slate-50 text-slate-700 border-slate-200';
}

function statusClass(status) {
    if (['verified', 'approved'].includes(status)) return 'bg-green-100 text-green-800';
    if (['rejected', 'reversed'].includes(status)) return 'bg-red-100 text-red-800';
    return 'bg-amber-100 text-amber-800';
}

function emailStatusClass(status) {
    if (status === 'sent') return 'text-green-700';
    if (status === 'failed') return 'text-red-600';
    return 'text-slate-500';
}

function canResend(p) {
    return ['verified', 'approved'].includes(p.status) && p.receipt_url && p.receipt_status === 'approved';
}

function canReverse(p) {
    return !!p.fee_receipt_id && p.receipt_status === 'approved';
}

function rowKey(p) {
    return `${p.type}-${p.id}`;
}

function reverseReceipt(p) {
    if (!p.fee_receipt_id) return;
    const reason = window.prompt('Reason for reversal (optional):');
    if (reason === null) return;
    if (!window.confirm('Reverse this approved receipt and post compensating ledger entries?')) return;

    reversing.value = rowKey(p);
    router.post(`/sahodaya-admin/${props.sahodaya.id}/finance/payments/receipts/${p.fee_receipt_id}/reverse`, {
        reason: reason || null,
    }, {
        preserveScroll: true,
        onFinish: () => { reversing.value = null; },
    });
}

function resend(p) {
    resending.value = rowKey(p);
    router.post(`/sahodaya-admin/${props.sahodaya.id}/finance/payments/resend-receipt`, {
        type: p.type,
        id: String(p.id),
        fee_receipt_id: p.fee_receipt_id ?? null,
    }, {
        preserveScroll: true,
        onFinish: () => { resending.value = null; },
    });
}
</script>
