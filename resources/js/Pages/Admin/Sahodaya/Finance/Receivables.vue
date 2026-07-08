<template>
    <SahodayaAdminLayout title="Accounts receivable" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Accounts receivable" eyebrow="Finance"
                    description="Outstanding school dues not yet fully verified or collected.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/finance`" class="btn-secondary text-sm">← Finance hub</Link>
            </template>
        </PageHeader>

        <div class="grid sm:grid-cols-2 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold">{{ totals.count }}</p>
                <p class="text-xs text-slate-500 mt-1">Open accounts</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold">₹{{ Number(totals.amount).toLocaleString('en-IN') }}</p>
                <p class="text-xs text-slate-500 mt-1">Total outstanding</p>
            </div>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th>School</th>
                        <th>Program</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Updated</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in rows" :key="`${row.source}-${row.school_id}-${i}`">
                        <td class="capitalize text-xs">{{ row.source }}</td>
                        <td>{{ row.school }}</td>
                        <td>{{ row.program }}</td>
                        <td>₹{{ Number(row.amount).toLocaleString('en-IN') }}</td>
                        <td><span class="status-pill text-xs status-pill--open capitalize">{{ row.status }}</span></td>
                        <td class="text-xs text-slate-500">{{ row.updated_at }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="6" class="p-8 text-center text-slate-400">No outstanding receivables.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    rows: Array,
    totals: Object,
});
</script>
