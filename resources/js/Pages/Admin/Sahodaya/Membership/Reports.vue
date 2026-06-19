<template>
    <SahodayaAdminLayout title="Membership Reports" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :approvedSchoolsCount="approvedSchoolsCount"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="space-y-5">
            <!-- Summary -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <SummaryCard label="Pending Approval Fees"
                             :value="`₹${Number(summary.pending_amount || 0).toLocaleString('en-IN')}`"
                             :hint="`${summary.payments_pending} payments awaiting verification`" color="amber" />
                <SummaryCard label="Approved Fees"
                             :value="`₹${Number(summary.approved_amount || summary.total_collected || 0).toLocaleString('en-IN')}`"
                             :hint="`${summary.payments_verified} verified payments`" color="green" />
                <SummaryCard label="Payment Not Done"
                             :value="`₹${Number(summary.payment_due_amount || 0).toLocaleString('en-IN')}`"
                             :hint="`${summary.payment_due ?? 0} schools not paid`" color="navy" />
                <SummaryCard label="Total Registered" :value="summary.total_registered" color="navy" />
                <SummaryCard label="Approved Schools" :value="summary.total_schools" color="green" />
                <SummaryCard label="Pending Schools" :value="summary.pending_schools" color="amber" />
            </div>

            <!-- Report tabs -->
            <div class="flex flex-wrap gap-2">
                <button v-for="t in reportTabs" :key="t.key"
                        @click="switchTab(t.key)"
                        :class="['px-4 py-2 rounded-xl text-sm font-semibold border transition',
                                 tab === t.key
                                     ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]'
                                     : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300']">
                    {{ t.label }}
                </button>
            </div>

            <!-- Search + date + export -->
            <div class="flex flex-wrap items-end gap-3">
                <input v-model="searchForm.search" type="search"
                       :placeholder="searchPlaceholder"
                       @keyup.enter="applySearch"
                       class="border border-gray-200 rounded-xl px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#0f3d7a]/20 w-full max-w-xs">
                <input v-model="searchForm.date_from" type="date"
                       class="border border-gray-200 rounded-xl px-3 py-2 text-sm">
                <input v-model="searchForm.date_to" type="date"
                       class="border border-gray-200 rounded-xl px-3 py-2 text-sm">
                <button @click="applySearch"
                        class="bg-[#0f3d7a] text-white px-4 py-2 rounded-xl text-sm font-semibold">Apply</button>
                <a :href="exportUrl()" class="export-btn ml-auto">Download Excel ↓</a>
            </div>

            <!-- Schools list -->
            <div v-if="tab === 'schools'" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-bold text-gray-900">Member Schools</h3>
                    <p class="text-xs text-gray-400 mt-0.5">All schools with membership status and student counts.</p>
                </div>
                <div v-if="schools?.data?.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="th">School</th>
                                <th class="th">Status</th>
                                <th class="th">Prefix</th>
                                <th class="th text-right">Students</th>
                                <th class="th text-right">Classes</th>
                                <th class="th">Joined</th>
                                <th class="th"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="s in schools.data" :key="s.id" class="hover:bg-gray-50/50">
                                <td class="td font-medium text-gray-800">{{ s.name }}</td>
                                <td class="td"><StatusPill :status="s.membership_status" /></td>
                                <td class="td font-mono text-xs text-gray-500">{{ s.school_prefix || '—' }}</td>
                                <td class="td text-right font-semibold text-[#0f3d7a]">{{ s.student_count.toLocaleString('en-IN') }}</td>
                                <td class="td text-right text-gray-600">{{ s.classes_count }}</td>
                                <td class="td text-gray-500 text-xs">{{ formatDate(s.created_at) }}</td>
                                <td class="td text-right">
                                    <Link :href="`/sahodaya-admin/${sahodaya.id}/schools/${s.id}`"
                                          class="text-xs font-semibold text-[#0f3d7a] hover:underline">Details →</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-gray-400 text-center py-10">No schools found.</p>
                <PaginationLinks v-if="schools" :links="schools.links" />
            </div>

            <!-- Payment not done -->
            <div v-else-if="tab === 'payment-due'" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="font-bold text-gray-900">Payment Not Done</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Schools registered for {{ academicYear }} but have not uploaded payment proof yet.</p>
                    </div>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/membership/payments?status=payment-due`"
                          class="text-xs font-semibold text-[#0f3d7a] hover:underline shrink-0">
                        Open in Payments →
                    </Link>
                </div>
                <div v-if="paymentDue?.data?.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="th">School</th>
                                <th class="th">Code</th>
                                <th class="th">Reg No</th>
                                <th class="th text-right">Fee Due</th>
                                <th class="th">Status</th>
                                <th class="th">Updated</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="r in paymentDue.data" :key="r.id" class="hover:bg-gray-50/50">
                                <td class="td font-medium text-gray-800">{{ r.school?.name ?? '—' }}</td>
                                <td class="td font-mono text-xs text-gray-500">{{ r.school?.school_prefix || '—' }}</td>
                                <td class="td font-mono text-xs text-gray-600">{{ r.reg_no || '—' }}</td>
                                <td class="td text-right font-bold text-gray-800">
                                    {{ r.membership_fee_amount ? `₹${Number(r.membership_fee_amount).toLocaleString('en-IN')}` : '—' }}
                                </td>
                                <td class="td"><StatusPill :status="r.registration_status" /></td>
                                <td class="td text-xs text-gray-500">{{ formatDate(r.updated_at) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-else class="text-sm text-gray-400 text-center py-10">No schools awaiting payment.</p>
                <PaginationLinks v-if="paymentDue" :links="paymentDue.links" />
            </div>

            <!-- Payments pending -->
            <div v-else-if="tab === 'payments-pending'" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-4">
                    <div>
                        <h3 class="font-bold text-gray-900">Payments Pending Verification</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Uploaded payment proofs awaiting Sahodaya review.</p>
                    </div>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/membership/payments`"
                          class="text-xs font-semibold text-[#0f3d7a] hover:underline shrink-0">
                        Verify payments →
                    </Link>
                </div>
                <PaymentReportTable :rows="paymentsPending?.data" empty="No pending payments." />
                <PaginationLinks v-if="paymentsPending" :links="paymentsPending.links" />
            </div>

            <!-- Payments done -->
            <div v-else-if="tab === 'payments-done'" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="font-bold text-gray-900">Verified Payments</h3>
                    <p class="text-xs text-gray-400 mt-0.5">Completed membership payments with uploaded proof on file.</p>
                </div>
                <PaymentReportTable :rows="paymentsDone?.data" empty="No verified payments yet." showVerifiedAt />
                <PaginationLinks v-if="paymentsDone" :links="paymentsDone.links" />
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
    academicYear: String, tab: String, search: String,
    dateFrom: String, dateTo: String,
    summary: Object, schools: Object,
    paymentDue: Object,
    paymentsPending: Object, paymentsDone: Object,
});

const searchForm = reactive({
    search: props.search ?? '',
    date_from: props.dateFrom ?? '',
    date_to: props.dateTo ?? '',
});

const reportTabs = [
    { key: 'schools',           label: 'Schools List' },
    { key: 'payment-due',       label: 'Payment Not Done' },
    { key: 'payments-pending',  label: 'Payments Pending' },
    { key: 'payments-done',     label: 'Payments Done' },
];

const searchPlaceholder = computed(() => ({
    schools:           'Search schools…',
    'payment-due':     'Search by school name…',
    'payments-pending': 'Search by school name…',
    'payments-done':    'Search by school name…',
}[props.tab] ?? 'Search…'));

function reportParams(overrides = {}) {
    return {
        tab: props.tab,
        search: props.search,
        date_from: props.dateFrom ?? '',
        date_to: props.dateTo ?? '',
        ...overrides,
    };
}

function switchTab(tab) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/membership/reports`, reportParams({ tab, search: '', date_from: '', date_to: '' }), {
        preserveState: true, replace: true,
    });
}

function applySearch() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/membership/reports`, reportParams({
        search: searchForm.search,
        date_from: searchForm.date_from,
        date_to: searchForm.date_to,
    }), { preserveState: true, replace: true });
}

function exportUrl() {
    const type = {
        schools:           'schools',
        'payment-due':     'payment-due',
        'payments-pending': 'payments-pending',
        'payments-done':    'payments-done',
    }[props.tab] ?? 'schools';

    const params = new URLSearchParams();
    const p = {
        search: searchForm.search || props.search || '',
        date_from: searchForm.date_from || props.dateFrom || '',
        date_to: searchForm.date_to || props.dateTo || '',
    };
    Object.entries(p).forEach(([key, value]) => {
        if (value) params.set(key, value);
    });

    const qs = params.toString();
    return `/sahodaya-admin/${props.sahodaya.id}/membership/reports/export/${type}${qs ? `?${qs}` : ''}`;
}

function formatDate(d) {
    if (!d) return '—';
    return new Date(d).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

const statusColors = {
    approved:  'bg-green-100 text-green-700',
    pending:   'bg-amber-100 text-amber-700',
    rejected:  'bg-red-100 text-red-700',
    submitted: 'bg-amber-100 text-amber-700',
    verified:  'bg-green-100 text-green-700',
};

const StatusPill = defineComponent({
    props: { status: String },
    setup(p) {
        return () => h('span', {
            class: ['inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold capitalize',
                    statusColors[p.status] || 'bg-gray-100 text-gray-600'],
        }, (p.status || '—').replace(/_/g, ' '));
    },
});

const SummaryCard = defineComponent({
    props: { label: String, value: [String, Number], hint: String, color: String },
    setup(p) {
        const borders = { navy: 'border-[#dbeafe]', amber: 'border-amber-100', green: 'border-green-100' };
        const texts   = { navy: 'text-[#0f3d7a]', amber: 'text-amber-700', green: 'text-green-700' };
        return () => h('div', { class: `bg-white border ${borders[p.color]} rounded-2xl p-4` }, [
            h('p', { class: 'text-xs text-gray-500 font-medium' }, p.label),
            h('p', { class: `text-2xl font-extrabold mt-1 ${texts[p.color]}` }, p.value),
            p.hint ? h('p', { class: 'text-[11px] text-gray-400 mt-0.5' }, p.hint) : null,
        ]);
    },
});

const PaginationLinks = defineComponent({
    props: { links: Array },
    setup(p) {
        return () => p.links?.length > 3
            ? h('div', { class: 'flex justify-center gap-1 px-4 py-4 border-t border-gray-100' },
                p.links.map((link, i) => h(Link, {
                    key: i,
                    href: link.url || '#',
                    class: ['px-3 py-1 rounded-lg text-sm', link.active ? 'bg-[#0f3d7a] text-white' : 'text-gray-600 hover:bg-gray-100'],
                    innerHTML: link.label,
                })))
            : null;
    },
});

const PaymentReportTable = defineComponent({
    props: { rows: Array, empty: String, showVerifiedAt: Boolean },
    setup(p) {
        return () => {
            if (!p.rows?.length) {
                return h('p', { class: 'text-sm text-gray-400 text-center py-10' }, p.empty);
            }
            return h('div', { class: 'overflow-x-auto' }, [
                h('table', { class: 'w-full text-sm' }, [
                    h('thead', { class: 'bg-gray-50' }, h('tr', [
                        h('th', { class: 'th' }, 'School'),
                        h('th', { class: 'th' }, 'Year'),
                        h('th', { class: 'th text-right' }, 'Amount'),
                        h('th', { class: 'th' }, 'Method'),
                        h('th', { class: 'th' }, 'Reference'),
                        h('th', { class: 'th' }, 'Proof'),
                        p.showVerifiedAt ? h('th', { class: 'th' }, 'Verified') : null,
                    ])),
                    h('tbody', { class: 'divide-y divide-gray-50' },
                        p.rows.map((row) => h('tr', { key: row.id, class: 'hover:bg-gray-50/50' }, [
                            h('td', { class: 'td font-medium text-gray-800' }, row.school?.name ?? '—'),
                            h('td', { class: 'td text-gray-500' }, row.academic_year),
                            h('td', { class: 'td text-right font-bold text-gray-800' }, `₹${Number(row.amount).toLocaleString('en-IN')}`),
                            h('td', { class: 'td text-gray-500 capitalize text-xs' }, row.payment_method?.replace('_', ' ') ?? '—'),
                            h('td', { class: 'td font-mono text-xs text-gray-600' }, row.transaction_ref ?? '—'),
                            h('td', { class: 'td' }, row.proof_url
                                ? h('a', {
                                    href: row.proof_url,
                                    target: '_blank',
                                    rel: 'noopener',
                                    class: 'inline-flex items-center gap-1 text-xs font-semibold text-[#0f3d7a] hover:underline',
                                }, '📎 View upload ↗')
                                : h('span', { class: 'text-xs text-gray-400' }, '—')),
                            p.showVerifiedAt
                                ? h('td', { class: 'td text-xs text-gray-500' },
                                    row.verified_at ? new Date(row.verified_at).toLocaleDateString('en-IN') : '—')
                                : null,
                        ]))),
                ]),
            ]);
        };
    },
});
</script>

<style scoped>
@reference "../../../../../css/app.css";
.th { @apply text-left px-4 py-2.5 font-semibold text-gray-500 text-xs; }
.td { @apply px-4 py-3; }
.export-btn {
    @apply inline-flex items-center px-4 py-2 rounded-xl bg-[#eff6ff] hover:bg-[#dbeafe]
           text-[#0f3d7a] border border-[#bfdbfe] text-sm font-semibold transition;
}
</style>
