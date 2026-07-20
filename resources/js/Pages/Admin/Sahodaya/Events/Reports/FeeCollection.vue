<template>
    <SahodayaEventsLayout :title="`${event.title} — Fee collection`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Fee collection`" eyebrow="Reports"
                    description="School-wise fee due, collected amounts, and payment status.">
            <template #actions>
                <a :href="xlsUrl" class="btn-secondary text-sm">Export pending schools ↓</a>
                <Link :href="feesUrl" class="btn-primary text-sm">Event fees →</Link>
            </template>
        </PageHeader>

        <ReportsSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="fee-collection" />

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">{{ totals.schools }}</p>
                <p class="text-xs text-slate-500 mt-1">Schools billed</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold">₹{{ totals.due }}</p>
                <p class="text-xs text-slate-500 mt-1">Total due</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-emerald-700">₹{{ totals.collected }}</p>
                <p class="text-xs text-slate-500 mt-1">Collected (approved)</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-xl font-bold text-amber-700">{{ totals.pending }}</p>
                <p class="text-xs text-slate-500 mt-1">Awaiting payment</p>
            </div>
        </div>

        <section v-if="byHead?.length" class="mb-8">
            <h3 class="section-title mb-3">{{ event.event_type === 'sports' ? 'Fee catalog by Event Head' : 'Fee catalog by item head' }}</h3>
            <div class="card overflow-hidden p-0">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Head</th>
                            <th>Items</th>
                            <th>Registrations</th>
                            <th>Default fee</th>
                            <th>Extra item fee</th>
                            <th>Catalog total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in byHead" :key="row.head_id">
                            <td class="font-medium">{{ row.head_name }}</td>
                            <td>{{ row.item_count }}</td>
                            <td>{{ row.registrations }}</td>
                            <td>{{ row.default_fee != null ? `₹${row.default_fee}` : '—' }}</td>
                            <td>{{ row.extra_fee != null ? `₹${row.extra_fee}` : '—' }}</td>
                            <td>₹{{ row.catalog_total }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Sl No</th>
                        <th>School</th>
                        <th>Due</th>
                        <th>Paid</th>
                        <th>Status</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, idx) in rows" :key="row.school_id">
                        <td>{{ idx + 1 }}</td>
                        <td class="font-medium">{{ (row.school_name || '').toUpperCase() }}</td>
                        <td>₹{{ row.total_due }}</td>
                        <td>₹{{ row.paid }}</td>
                        <td>
                            <span class="status-pill text-xs" :class="statusClass(row.status)">{{ row.status }}</span>
                        </td>
                        <td class="text-xs font-mono">{{ row.receipt_no ?? '—' }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="6" class="p-6 text-center text-slate-400">No fee records for this event.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportsSubNav from '@/Components/sahodaya/ReportsSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    rows: Array,
    byHead: { type: Array, default: () => [] },
    totals: Object,
    xlsUrl: String,
    feesUrl: String,
    activityLogs: { type: Array, default: () => [] },
});

function statusClass(status) {
    if (status === 'approved') return 'status-pill--completed';
    if (status === 'proof_uploaded') return 'status-pill--open';
    return 'status-pill--pending';
}
</script>
