<template>
    <SahodayaAdminLayout :title="`Fee summary report — ${exam.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="exam.title" eyebrow="Talent Search exam" description="Per-school batch fees and payment status.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/mcq-exams/${exam.id}/reports`" class="btn-secondary text-sm">← Reports</Link>
            </template>
        </PageHeader>
        <McqExamSubNav :sahodaya-id="sahodaya.id" :exam-id="exam.id" active="reports" />

        <div class="flex flex-wrap gap-2 mb-4 items-center">
            <input v-model="filterForm.search" type="search" class="field max-w-xs" placeholder="Search school…">
            <SearchableSelect v-model="filterForm.status" :options="statusOptions" :all-option="true" all-label="All statuses" placeholder="All statuses" />
            <button v-if="hasFilters" type="button" @click="clearFilters" class="text-sm text-slate-400 hover:underline">Clear</button>
            <div class="ml-auto flex flex-wrap gap-2">
                <a :href="exportBase + '/export'" class="btn-secondary text-sm">Export Excel ↓</a>
                <a :href="exportBase + '-pending/export'" class="btn-secondary text-sm">Pending fees ↓</a>
                <a :href="exportBase + '-rejected/export'" class="btn-secondary text-sm">Rejected fees ↓</a>
            </div>
        </div>

        <div class="form-section overflow-hidden !p-0">
            <table class="data-table">
                <thead><tr><th>Sl No</th><th>School</th><th>Students</th><th>Due</th><th>Status</th><th>Payment</th></tr></thead>
                <tbody>
                    <tr v-for="(row, i) in feeSummary" :key="i">
                        <td>{{ i + 1 }}</td>
                        <td class="font-medium">{{ (row.school_name || '').toUpperCase() }}</td>
                        <td>{{ row.student_count }}</td>
                        <td>₹{{ row.total_due }}</td>
                        <td class="text-xs capitalize">{{ row.status?.replace('_', ' ') }}</td>
                        <td class="text-xs">
                            <template v-if="row.payment_date">
                                {{ row.payment_date }}
                                <span v-if="row.transaction_ref" class="text-slate-500"> · {{ row.transaction_ref }}</span>
                            </template>
                            <span v-else class="text-slate-400">Not uploaded</span>
                        </td>
                    </tr>
                    <tr v-if="!feeSummary.length"><td colspan="6" class="p-6 text-center text-slate-400">No matching schools.</td></tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import McqExamSubNav from '@/Components/sahodaya/McqExamSubNav.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    exam: Object,
    feeSummary: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const filterForm = reactive({
    search: props.filters?.search ?? '',
    status: props.filters?.status ?? null,
});

const statusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'proof_uploaded', label: 'Proof uploaded' },
    { value: 'partial', label: 'Partial' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
    { value: 'waived', label: 'Waived' },
];

const hasFilters = computed(() => !!(filterForm.search || filterForm.status));

const pageUrl = `/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/reports/fees`;
const exportBase = `/sahodaya-admin/${props.sahodaya.id}/mcq-exams/${props.exam.id}/reports/fees`;

function applyFilters() {
    router.get(pageUrl, { ...filterForm }, { preserveState: true, preserveScroll: true });
}

useDebouncedInertiaFilters(filterForm, applyFilters, () => props.filters);

function clearFilters() {
    filterForm.search = '';
    filterForm.status = null;
    applyFilters();
}
</script>
