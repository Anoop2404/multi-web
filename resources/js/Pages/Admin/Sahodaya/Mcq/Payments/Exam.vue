<template>
    <SahodayaAdminLayout :title="`Payments — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Talent Search exam"
                    description="Verify school batch payments and confirm candidate registrations with hall tickets." />
        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" active="payments"
                       :delivery-mode="exam.delivery_mode" :results-published="!!exam.results_published"
                       :is-model-exam="!!exam.is_model_exam" />

        <!-- Executive Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3.5 mb-6">
            <div class="card card--muted !p-4">
                <div class="flex items-center justify-between text-xs text-slate-500 mb-1.5">
                    <span class="font-medium">Total Collected</span>
                    <span class="text-xs px-1.5 py-0.5 rounded font-semibold bg-emerald-100 text-emerald-800">
                        {{ metrics.percentCollected }}%
                    </span>
                </div>
                <p class="text-2xl font-bold tracking-tight text-slate-900">₹{{ formatMoney(metrics.totalPaid) }}</p>
                <div class="w-full bg-slate-200 rounded-full h-1.5 mt-2 overflow-hidden">
                    <div class="bg-emerald-500 h-1.5 rounded-full transition-all duration-500" :style="{ width: `${metrics.percentCollected}%` }"></div>
                </div>
                <p class="text-[11px] text-slate-500 mt-1.5">of ₹{{ formatMoney(metrics.totalDue) }} total due</p>
            </div>

            <div class="card card--muted !p-4 cursor-pointer hover:border-amber-400 transition"
                 :class="{ '!border-amber-400 ring-2 ring-amber-300/40 bg-amber-50/20': currentTab === 'awaiting_approval' }"
                 @click="currentTab = 'awaiting_approval'">
                <div class="flex items-center justify-between text-xs text-slate-500 mb-1.5">
                    <span class="font-medium">Awaiting Review</span>
                    <span v-if="metrics.pendingReviewCount > 0" class="flex h-2 w-2 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                    </span>
                </div>
                <p class="text-2xl font-bold tracking-tight" :class="metrics.pendingReviewCount > 0 ? 'text-amber-700' : 'text-slate-900'">
                    {{ metrics.pendingReviewCount }}
                </p>
                <p class="text-[11px] text-slate-500 mt-2">
                    <template v-if="metrics.pendingReviewCount > 0">
                        ₹{{ formatMoney(metrics.pendingReviewAmount) }} waiting approval
                    </template>
                    <template v-else>
                        All proofs reviewed
                    </template>
                </p>
            </div>

            <div class="card card--muted !p-4 cursor-pointer hover:border-emerald-400 transition"
                 :class="{ '!border-emerald-400 ring-2 ring-emerald-300/40': currentTab === 'approved' }"
                 @click="currentTab = 'approved'">
                <div class="flex items-center justify-between text-xs text-slate-500 mb-1.5">
                    <span class="font-medium">Schools Settled</span>
                    <span class="text-[11px] font-semibold text-emerald-700">
                        {{ metrics.settledCount }}/{{ schoolFees.length }}
                    </span>
                </div>
                <p class="text-2xl font-bold tracking-tight text-emerald-700">{{ metrics.settledCount }}</p>
                <p class="text-[11px] text-slate-500 mt-2">
                    {{ schoolFees.length - metrics.settledCount }} school(s) pending settlement
                </p>
            </div>

            <div class="card card--muted !p-4">
                <div class="flex items-center justify-between text-xs text-slate-500 mb-1.5">
                    <span class="font-medium">Candidate Registrations</span>
                    <span class="text-xs text-slate-400">Total</span>
                </div>
                <p class="text-2xl font-bold tracking-tight text-slate-900">{{ metrics.totalStudents.toLocaleString() }}</p>
                <p class="text-[11px] text-slate-500 mt-2">
                    <span class="font-semibold text-emerald-700">{{ metrics.paidStudents.toLocaleString() }} confirmed</span>
                    <span v-if="metrics.dueStudents > 0" class="text-amber-700 font-semibold"> · {{ metrics.dueStudents.toLocaleString() }} pending</span>
                </p>
            </div>
        </div>

        <!-- Attention Banner for Awaiting Review -->
        <div v-if="pendingCount > 0" class="rounded-xl border border-amber-300 bg-gradient-to-r from-amber-50 to-orange-50 p-4 shadow-sm mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-500/15 text-amber-700 flex items-center justify-center font-bold text-lg shrink-0">
                    ⚡
                </div>
                <div>
                    <h4 class="text-sm font-semibold text-amber-950">
                        {{ pendingCount }} school batch payment(s) awaiting your review
                    </h4>
                    <p class="text-xs text-amber-900/80 mt-0.5">
                        Verify uploaded proof to confirm student registrations and generate hall tickets.
                    </p>
                </div>
            </div>
            <button v-if="currentTab !== 'awaiting_approval'" @click="currentTab = 'awaiting_approval'"
                    class="btn-primary !bg-amber-600 hover:!bg-amber-700 !text-white text-xs py-2 px-3.5 shadow-sm rounded-lg font-semibold flex items-center gap-1.5">
                Review pending batches ({{ pendingCount }}) →
            </button>
        </div>

        <!-- Filter Bar with Segmented Status Tabs -->
        <div class="space-y-3 mb-4">
            <div class="flex flex-wrap items-center gap-1.5 border-b border-slate-200 pb-2">
                <button v-for="tab in statusTabs" :key="tab.key"
                        type="button"
                        @click="currentTab = tab.key"
                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition flex items-center gap-1.5"
                        :class="currentTab === tab.key
                            ? 'bg-slate-900 text-white shadow-sm'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'">
                    <span>{{ tab.label }}</span>
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold"
                          :class="currentTab === tab.key
                            ? 'bg-slate-700 text-slate-100'
                            : (tab.isAlert ? 'bg-amber-100 text-amber-800' : 'bg-slate-200 text-slate-700')">
                        {{ tab.count }}
                    </span>
                </button>
            </div>

            <div class="flex flex-wrap gap-2.5 items-center">
                <div class="relative flex-1 min-w-[14rem] max-w-sm">
                    <input v-model="search" type="search" class="field !pl-8 text-sm" placeholder="Search school name or UTR ref…" />
                    <svg class="w-4 h-4 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <SearchableSelect v-model="schoolFilter" :options="schoolOptions" :all-option="true" all-label="All schools" placeholder="Filter by school" class="w-56" />
                <button v-if="hasFilters" type="button" @click="clearFilters" class="text-xs text-rose-600 hover:text-rose-800 font-medium hover:underline">
                    Reset filters
                </button>
                <div class="ml-auto text-xs text-slate-500 font-medium">
                    Showing <span class="font-semibold text-slate-800">{{ filteredFees.length }}</span> of {{ schoolFees.length }} schools
                </div>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="card card--flush overflow-hidden shadow-sm border border-slate-200">
            <EmptyState v-if="!schoolFees.length" title="No school fees yet" description="Fees appear when schools register students and upload batch payment proof." icon="💳" class="py-12" />
            <EmptyState v-else-if="!filteredFees.length" title="No matching schools found" description="Try clearing filters or search for another school." icon="🔍" class="py-12">
                <template #action>
                    <button type="button" @click="clearFilters" class="btn-secondary text-xs mt-3">Clear all filters</button>
                </template>
            </EmptyState>
            <div v-else class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th class="w-12 text-center">#</th>
                            <th>School</th>
                            <th class="text-center">Students</th>
                            <th>Paid / Total Due</th>
                            <th>Payment Status</th>
                            <th>Proof & Reference</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(sf, idx) in filteredFees" :key="sf.id">
                        <tr class="transition group"
                            :class="sf.fee_receipt?.status === 'uploaded' ? 'bg-amber-50/40 hover:bg-amber-50/70 border-l-4 border-l-amber-500' : 'hover:bg-slate-50/80'">
                            <td class="text-center font-mono text-xs text-slate-400">{{ idx + 1 }}</td>
                            <td>
                                <div class="font-semibold text-slate-900 group-hover:text-indigo-950 transition">
                                    {{ (sf.school_name || '').toUpperCase() }}
                                </div>
                                <div class="flex items-center gap-2 mt-0.5 text-[11px] text-slate-400">
                                    <span>ID: {{ sf.school_id }}</span>
                                    <span v-if="sf.fee_receipt?.status === 'uploaded'" class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-100 text-amber-800">
                                        Action required
                                    </span>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="font-bold text-slate-900 text-sm">
                                    {{ sf.student_count }}
                                </div>
                                <div v-if="sf.due_student_count > 0 && sf.paid_student_count > 0" class="text-[11px] font-medium text-amber-700 mt-0.5 whitespace-nowrap">
                                    {{ sf.paid_student_count }} paid · <span class="font-bold text-rose-600">{{ sf.due_student_count }} due</span>
                                </div>
                                <div v-else-if="sf.due_student_count > 0" class="text-[11px] font-medium text-rose-600 mt-0.5 whitespace-nowrap">
                                    {{ sf.due_student_count }} due
                                </div>
                                <div v-else class="text-[11px] font-medium text-emerald-600 mt-0.5 whitespace-nowrap">
                                    All paid
                                </div>
                            </td>
                            <td>
                                <div class="text-sm font-bold text-slate-900">
                                    ₹{{ formatMoney(sf.amount_paid) }}
                                    <span class="text-xs font-normal text-slate-400">/ ₹{{ formatMoney(sf.total_due) }}</span>
                                </div>
                                <div v-if="sf.pending_count > 1" class="text-[11px] font-semibold text-amber-700 flex items-center gap-1 mt-0.5">
                                    <span>⏳</span> {{ sf.pending_count }} proofs pending review (₹{{ formatMoney(sf.pending_total) }})
                                </div>
                            </td>
                            <td>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold"
                                      :class="statusBadgeClass(sf)">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="statusDotClass(sf)"></span>
                                    {{ statusLabel(sf) }}
                                </span>
                            </td>
                            <td class="text-xs">
                                <template v-if="sf.fee_receipt">
                                    <div class="font-medium text-slate-700">
                                        {{ formatCalendarDate(sf.fee_receipt.payment_date) || 'Date not recorded' }}
                                    </div>
                                    <div v-if="sf.fee_receipt.transaction_ref" class="mt-0.5">
                                        <span class="font-mono text-[11px] bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded border border-slate-200">
                                            {{ sf.fee_receipt.transaction_ref }}
                                        </span>
                                    </div>
                                </template>
                                <span v-else class="text-slate-400 italic">No receipt submitted</span>
                            </td>
                            <td class="text-xs whitespace-nowrap text-right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <!-- Awaiting review actions -->
                                    <template v-if="sf.fee_receipt?.status === 'uploaded'">
                                        <button type="button" @click="approve(sf.id)"
                                                class="btn-primary !bg-emerald-600 hover:!bg-emerald-700 !text-white text-xs py-1.5 px-3 rounded-lg font-semibold shadow-sm flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                            Approve
                                        </button>
                                        <button type="button" @click="reject(sf.id)"
                                                class="btn-secondary !text-rose-600 hover:!bg-rose-50 text-xs py-1.5 px-2.5 rounded-lg font-medium">
                                            Reject
                                        </button>
                                    </template>

                                    <!-- Proof preview button -->
                                    <button v-if="getReceiptProofUrls(sf.fee_receipt).length" type="button"
                                            @click="openGallery(getReceiptProofUrls(sf.fee_receipt), `${sf.school_name} — Payment Proof`)"
                                            class="btn-secondary text-xs py-1.5 px-2.5 rounded-lg flex items-center gap-1 text-slate-700 hover:text-indigo-600"
                                            title="Preview receipt proof">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                        Proof
                                    </button>

                                    <!-- History toggle -->
                                    <button v-if="sf.receipts_history?.length > 1" type="button"
                                            class="btn-secondary text-xs py-1.5 px-2 rounded-lg font-semibold text-indigo-700 hover:bg-indigo-50"
                                            @click="toggleExpand(sf.id)">
                                        {{ expanded[sf.id] ? 'Hide' : 'History' }} ({{ sf.receipts_history.length }})
                                        <span class="text-[10px] ml-0.5">{{ expanded[sf.id] ? '▲' : '▼' }}</span>
                                    </button>
                                </div>
                            </td>
                        </tr>

                        <!-- Expanded History Timeline -->
                        <tr v-if="expanded[sf.id] && sf.receipts_history">
                            <td colspan="7" class="bg-slate-50/90 !p-3.5">
                                <div class="max-w-4xl mx-auto space-y-2">
                                    <div class="text-xs font-semibold text-slate-500 flex items-center gap-2 mb-2">
                                        <span>Payment installment history for {{ sf.school_name }}</span>
                                        <span class="h-px bg-slate-200 flex-1"></span>
                                    </div>
                                    <div v-for="r in sf.receipts_history" :key="r.id"
                                         class="text-xs text-slate-700 flex flex-wrap items-center justify-between gap-3 bg-white p-3 rounded-xl border border-slate-200/80 shadow-xs">
                                        <div class="flex items-center gap-3">
                                            <span class="font-mono text-xs font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded border border-indigo-100">
                                                {{ r.receipt_number ? `#${r.receipt_number}` : 'No ref' }}
                                            </span>
                                            <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded-full" :class="historyBadgeClass(r.status)">
                                                {{ r.status }}
                                            </span>
                                            <span class="font-bold text-slate-900 text-sm">₹{{ formatMoney(r.amount) }}</span>
                                            <span v-if="r.payment_date" class="text-slate-500 text-[11px]">
                                                paid on {{ formatCalendarDate(r.payment_date) }}
                                            </span>
                                            <span v-if="r.reviewed_by" class="text-slate-400 text-[11px]">
                                                · reviewed by <strong class="text-slate-600">{{ r.reviewed_by }}</strong>
                                            </span>
                                            <div v-if="r.rejection_reason" class="text-rose-600 font-medium text-xs mt-0.5">
                                                Rejection note: {{ r.rejection_reason }}
                                            </div>
                                            <div v-if="r.reversal_reason" class="text-rose-600 font-medium text-xs mt-0.5">
                                                Reversal note: {{ r.reversal_reason }}
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <a v-if="r.receipt_url" :href="r.receipt_url" target="_blank" rel="noopener"
                                               class="btn-secondary text-[11px] py-1 px-2.5 rounded text-indigo-700 font-semibold hover:bg-indigo-50">
                                                Receipt ↗
                                            </a>
                                            <button v-if="getReceiptProofUrls(r).length" type="button"
                                                    @click="openGallery(getReceiptProofUrls(r), `${sf.school_name} — Receipt #${r.receipt_number || r.id}`)"
                                                    class="btn-secondary text-[11px] py-1 px-2.5 rounded font-medium text-slate-700 hover:text-indigo-600 flex items-center gap-1">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                                Proof ({{ getReceiptProofUrls(r).length }})
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>

        <ProofGallery :show="galleryOpen" :images="galleryImages" :title="galleryTitle" @close="galleryOpen = false" />
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import ProofGallery from '@/Components/ui/ProofGallery.vue';
import { formatCalendarDate } from '@/support/calendarDates.js';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    exam: Object,
    schoolFees: { type: Array, default: () => [] },
    pendingCount: { type: Number, default: 0 },
});

const { confirm, prompt } = useConfirm();

function formatMoney(value) {
    const num = Number(value) || 0;
    return num.toLocaleString('en-IN', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

// Executive Metrics
const metrics = computed(() => {
    let totalDue = 0;
    let totalPaid = 0;
    let totalStudents = 0;
    let pendingReviewCount = 0;
    let pendingReviewAmount = 0;
    let settledCount = 0;
    let partialCount = 0;
    let unpaidCount = 0;

    let paidStudents = 0;
    let dueStudents = 0;

    for (const sf of props.schoolFees) {
        const due = Number(sf.total_due) || 0;
        const paid = Number(sf.amount_paid) || 0;
        const students = Number(sf.student_count) || 0;

        totalDue += due;
        totalPaid += paid;
        totalStudents += students;
        paidStudents += Number(sf.paid_student_count) || 0;
        dueStudents += Number(sf.due_student_count) || 0;

        const isUploaded = (sf.fee_receipt?.status === 'uploaded') || (sf.status === 'proof_uploaded') || (sf.pending_count > 0);
        if (isUploaded) {
            pendingReviewCount++;
            pendingReviewAmount += (Number(sf.pending_total) || Number(sf.fee_receipt?.amount) || 0);
        }

        if (sf.status === 'approved' || sf.status === 'waived' || (due > 0 && paid >= due)) {
            settledCount++;
        } else if (sf.status === 'partial') {
            partialCount++;
        } else {
            unpaidCount++;
        }
    }

    const percentCollected = totalDue > 0 ? Math.min(100, Math.round((totalPaid / totalDue) * 100)) : 100;

    return {
        totalDue,
        totalPaid,
        totalStudents,
        paidStudents,
        dueStudents,
        pendingReviewCount,
        pendingReviewAmount,
        settledCount,
        partialCount,
        unpaidCount,
        percentCollected,
    };
});

// Expandable history rows
const expanded = ref({});
function toggleExpand(id) {
    expanded.value = { ...expanded.value, [id]: !expanded.value[id] };
}

// Proof gallery
const galleryOpen = ref(false);
const galleryImages = ref([]);
const galleryTitle = ref('Payment Proof');

function getReceiptProofUrls(receipt) {
    if (!receipt) return [];
    return [receipt.proof_url, ...(receipt.attachments ?? []).map(a => a.url)].filter(Boolean);
}

function openGallery(images, title = 'Payment Proof') {
    if (!images || !images.length) return;
    galleryImages.value = images;
    galleryTitle.value = title;
    galleryOpen.value = true;
}

// Filters & Tabs
const search = ref('');
const schoolFilter = ref(null);
const currentTab = ref('all'); // 'all', 'awaiting_approval', 'approved', 'partial', 'pending'

const statusTabs = computed(() => [
    { key: 'all', label: 'All Schools', count: props.schoolFees.length },
    { key: 'awaiting_approval', label: 'Awaiting Review', count: metrics.value.pendingReviewCount, isAlert: metrics.value.pendingReviewCount > 0 },
    { key: 'approved', label: 'Fully Paid / Approved', count: metrics.value.settledCount },
    { key: 'partial', label: 'Partial Payment', count: metrics.value.partialCount },
    { key: 'pending', label: 'Unpaid / Pending', count: metrics.value.unpaidCount },
]);

const schoolOptions = computed(() => {
    const seen = new Map();
    for (const sf of props.schoolFees) {
        if (!seen.has(sf.school_id)) seen.set(sf.school_id, sf.school_name);
    }
    return [...seen.entries()]
        .map(([value, label]) => ({ value, label: (label || '').toUpperCase() }))
        .sort((a, b) => a.label.localeCompare(b.label));
});

const hasFilters = computed(() => !!(search.value || schoolFilter.value || currentTab.value !== 'all'));

function clearFilters() {
    search.value = '';
    schoolFilter.value = null;
    currentTab.value = 'all';
}

const filteredFees = computed(() => {
    const q = search.value.trim().toLowerCase();

    return props.schoolFees.filter((sf) => {
        // Search filter
        if (q) {
            const nameMatch = (sf.school_name || '').toLowerCase().includes(q);
            const refMatch = (sf.fee_receipt?.transaction_ref || '').toLowerCase().includes(q);
            if (!nameMatch && !refMatch) return false;
        }

        // School dropdown filter
        if (schoolFilter.value && sf.school_id !== schoolFilter.value) {
            return false;
        }

        // Tab filter
        const isUploaded = (sf.fee_receipt?.status === 'uploaded') || (sf.status === 'proof_uploaded') || (sf.pending_count > 0);
        const due = Number(sf.total_due) || 0;
        const paid = Number(sf.amount_paid) || 0;
        const isSettled = sf.status === 'approved' || sf.status === 'waived' || (due > 0 && paid >= due);

        if (currentTab.value === 'awaiting_approval') {
            return isUploaded;
        }
        if (currentTab.value === 'approved') {
            return isSettled;
        }
        if (currentTab.value === 'partial') {
            return sf.status === 'partial';
        }
        if (currentTab.value === 'pending') {
            return !isSettled && !isUploaded;
        }

        return true;
    });
});

// Badges
function statusLabel(sf) {
    if (sf.fee_receipt?.status === 'uploaded' || sf.status === 'proof_uploaded') {
        return 'Awaiting review';
    }
    if (sf.status === 'approved' || sf.status === 'waived') {
        return 'Approved';
    }
    if (sf.status === 'partial') {
        return 'Partial';
    }
    if (sf.status === 'rejected') {
        return 'Rejected';
    }
    return 'Pending';
}

function statusBadgeClass(sf) {
    if (sf.fee_receipt?.status === 'uploaded' || sf.status === 'proof_uploaded') {
        return 'bg-amber-100 text-amber-800 border border-amber-200';
    }
    if (sf.status === 'approved' || sf.status === 'waived') {
        return 'bg-emerald-100 text-emerald-800 border border-emerald-200';
    }
    if (sf.status === 'partial') {
        return 'bg-sky-100 text-sky-800 border border-sky-200';
    }
    if (sf.status === 'rejected') {
        return 'bg-rose-100 text-rose-800 border border-rose-200';
    }
    return 'bg-slate-100 text-slate-700 border border-slate-200';
}

function statusDotClass(sf) {
    if (sf.fee_receipt?.status === 'uploaded' || sf.status === 'proof_uploaded') {
        return 'bg-amber-500 animate-pulse';
    }
    if (sf.status === 'approved' || sf.status === 'waived') {
        return 'bg-emerald-500';
    }
    if (sf.status === 'partial') {
        return 'bg-sky-500';
    }
    if (sf.status === 'rejected') {
        return 'bg-rose-500';
    }
    return 'bg-slate-400';
}

function historyBadgeClass(status) {
    return {
        approved:   'bg-emerald-100 text-emerald-800',
        uploaded:   'bg-amber-100 text-amber-800',
        rejected:   'bg-rose-100 text-rose-800',
        reversed:   'bg-red-100 text-red-800 line-through',
        superseded: 'bg-slate-100 text-slate-500 line-through',
        partial:    'bg-sky-100 text-sky-800',
        pending:    'bg-slate-100 text-slate-600',
    }[status] ?? 'bg-slate-100 text-slate-600';
}

// Action Handlers
async function approve(schoolFeeId) {
    if (!(await confirm({ message: 'Approve fee and issue hall tickets for all pending registrations from this school?', destructive: false }))) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/payments/${schoolFeeId}/approve`, {}, { preserveScroll: true });
}

async function reject(schoolFeeId) {
    const reason = await prompt({ message: 'Rejection reason for the school:', inputMultiline: true });
    if (!reason?.trim()) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/payments/${schoolFeeId}/reject`, {
        rejection_reason: reason.trim(),
    }, { preserveScroll: true });
}
</script>
