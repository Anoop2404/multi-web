<template>
    <SahodayaEventsLayout :title="`${event.title} — Finance Invoices`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — School invoices`" eyebrow="Event finance"
                    description="Formal invoices for fest registration fees (EVENT-FEE ledger). Separate from annual membership.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/fees`" class="btn-secondary text-xs">Registration fees</Link>
                <button type="button" @click="issueAll" class="btn-primary text-xs">Issue all invoices</button>
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 gap-3 mb-4 max-w-md">
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold">{{ summary.count }}</p>
                <p class="text-xs text-slate-500 mt-1">Invoices</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold">₹{{ summary.total }}</p>
                <p class="text-xs text-slate-500 mt-1">Total invoiced</p>
            </div>
        </div>

        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="p-3">Invoice #</th>
                        <th class="p-3">School</th>
                        <th class="p-3">Items</th>
                        <th class="p-3">Amount</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="inv in invoices" :key="inv.id" class="border-t">
                        <td class="p-3 font-mono text-xs">{{ inv.invoice_number }}</td>
                        <td class="p-3">{{ inv.school }}</td>
                        <td class="p-3">{{ inv.participation_item_count }}</td>
                        <td class="p-3 font-semibold">₹{{ inv.total_amount }}</td>
                        <td class="p-3"><span class="text-xs px-2 py-0.5 rounded bg-gray-100">{{ inv.status }}</span></td>
                        <td class="p-3 text-right text-xs space-y-1">
                            <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/finance/invoices/${inv.id}/pdf?preview=1`"
                               class="block text-indigo-600 font-semibold" target="_blank">Preview invoice ↗</a>
                            <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/finance/invoices/${inv.id}/pdf`"
                               class="block text-indigo-600 font-semibold" target="_blank">Download invoice ↓</a>
                            <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/finance/invoices/${inv.id}/demand-pdf`"
                               class="block text-violet-600 font-semibold" target="_blank">Payment demand ↗</a>
                        </td>
                    </tr>
                    <tr v-if="!invoices.length">
                        <td colspan="6" class="p-8 text-center text-gray-400">No invoices yet. Click “Issue all invoices”.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, invoices: Array, summary: Object,
    activityLogs: { type: Array, default: () => [] },
});

function issueAll() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/finance/issue-all`, {}, { preserveScroll: true });
}
</script>
