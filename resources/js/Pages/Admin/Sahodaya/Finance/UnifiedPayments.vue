<template>
    <SahodayaAdminLayout title="All payments" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="All payments" eyebrow="Finance"
                    description="Unified offline payment register across membership, fest, Talent Search, and training.">
            <template #actions>
                <a :href="exportUrl" class="btn-secondary text-sm">Export CSV ↓</a>
            </template>
        </PageHeader>

        <form class="card !p-4 mb-4 flex flex-wrap gap-3 items-end" @submit.prevent="applyFilters">
            <div>
                <label class="form-label text-xs">Module</label>
                <select v-model="form.type" class="form-input text-sm">
                    <option value="all">All</option>
                    <option value="membership">Membership</option>
                    <option value="fest">Fest</option>
                    <option value="mcq">Talent Search</option>
                    <option value="training">Training</option>
                </select>
            </div>
            <div>
                <label class="form-label text-xs">School</label>
                <select v-model="form.school_id" class="form-input text-sm min-w-[180px]">
                    <option value="">All schools</option>
                    <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </div>
            <div>
                <label class="form-label text-xs">Search</label>
                <input v-model="form.search" type="text" class="form-input text-sm" placeholder="School, label, receipt #" />
            </div>
            <button type="submit" class="btn-primary text-sm">Filter</button>
        </form>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
            <div class="card card--muted text-center !py-4">
                <p class="text-xs uppercase font-bold text-slate-500">Total</p>
                <p class="text-xl font-bold mt-1">₹{{ fmt(summary?.total) }}</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-xs uppercase font-bold text-blue-700">Membership</p>
                <p class="text-xl font-bold text-blue-900 mt-1">₹{{ fmt(summary?.membership) }}</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-xs uppercase font-bold text-purple-700">Fest</p>
                <p class="text-xl font-bold text-purple-900 mt-1">₹{{ fmt(summary?.fest) }}</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-xs uppercase font-bold text-emerald-700">Training</p>
                <p class="text-xl font-bold text-emerald-900 mt-1">₹{{ fmt(summary?.training) }}</p>
            </div>
            <div class="card card--muted text-center !py-4">
                <p class="text-xs uppercase font-bold text-violet-700">Talent Search</p>
                <p class="text-xl font-bold text-violet-900 mt-1">₹{{ fmt(summary?.mcq) }}</p>
            </div>
        </div>

        <div v-if="!payments?.length" class="card text-center py-16 text-slate-400">
            <p>No payment records match your filters.</p>
        </div>

        <div v-else class="space-y-3">
            <div v-for="p in payments" :key="`${p.type}-${p.id}`"
                 class="card flex flex-wrap items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <span class="text-xs font-bold uppercase px-2 py-0.5 rounded" :class="typeClass(p.type)">
                            {{ p.type }}
                        </span>
                        <span class="text-sm font-semibold text-gray-900">{{ p.label }}</span>
                    </div>
                    <p class="text-xs text-slate-600">{{ p.school_name }}</p>
                    <div class="text-xs text-gray-500 space-x-3 mt-1">
                        <span v-if="p.payment_date">{{ p.payment_date }}</span>
                        <span v-if="p.receipt_number" class="font-mono text-indigo-700">#{{ p.receipt_number }}</span>
                        <span v-if="p.receipt_email_status" :class="emailStatusClass(p.receipt_email_status)">
                            Email: {{ p.receipt_email_status }}
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="text-right">
                        <p class="font-bold">₹{{ fmt(p.amount) }}</p>
                        <span class="text-xs font-semibold px-2 py-0.5 rounded" :class="statusClass(p.status)">{{ p.status }}</span>
                    </div>
                    <a v-if="p.receipt_url" :href="p.receipt_url" target="_blank" rel="noopener"
                       class="btn-secondary text-xs !py-1.5 !px-3">Receipt</a>
                    <button v-if="canResend(p)" type="button" class="btn-secondary text-xs !py-1.5 !px-3"
                            :disabled="resending === rowKey(p)" @click="resend(p)">
                        {{ resending === rowKey(p) ? 'Sending…' : 'Resend email' }}
                    </button>
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

function fmt(n) {
    return Number(n ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function typeClass(type) {
    return {
        membership: 'bg-blue-100 text-blue-800',
        fest: 'bg-purple-100 text-purple-800',
        training: 'bg-emerald-100 text-emerald-800',
        mcq: 'bg-violet-100 text-violet-800',
    }[type] ?? 'bg-slate-100 text-slate-700';
}

function statusClass(status) {
    if (['verified', 'approved'].includes(status)) return 'bg-green-100 text-green-800';
    if (['rejected'].includes(status)) return 'bg-red-100 text-red-800';
    return 'bg-amber-100 text-amber-800';
}

function emailStatusClass(status) {
    if (status === 'sent') return 'text-green-700';
    if (status === 'failed') return 'text-red-600';
    return 'text-slate-500';
}

function canResend(p) {
    return ['verified', 'approved'].includes(p.status) && p.receipt_url;
}

function rowKey(p) {
    return `${p.type}-${p.id}`;
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
