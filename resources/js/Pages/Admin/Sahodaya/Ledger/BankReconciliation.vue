<template>
    <SahodayaAdminLayout title="Bank reconciliation" :sahodaya="sahodaya" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Bank reconciliation" eyebrow="Finance" />
        <div class="card overflow-x-auto p-0">
            <table class="data-table text-sm">
                <thead><tr><th>Date</th><th>Account</th><th>Type</th><th>Amount</th><th>Reconciled</th><th></th></tr></thead>
                <tbody>
                    <tr v-for="t in transactions.data" :key="t.id">
                        <td>{{ formatCalendarDate(t.transaction_date) }}</td><td>{{ t.account_head?.name }}</td><td>{{ t.entry_type }}</td><td>{{ t.amount }}</td>
                        <td>{{ t.reconciled_at ? 'Yes' : 'No' }}</td>
                        <td><button class="text-xs text-indigo-700 font-semibold" @click="toggle(t, !t.reconciled_at)">{{ t.reconciled_at ? 'Clear' : 'Mark reconciled' }}</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>
<script setup>
import { router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { formatCalendarDate } from '@/support/calendarDates.js';
import PageHeader from '@/Components/ui/PageHeader.vue';
const props = defineProps({ sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, transactions: Object, bankAccounts: Array });
function toggle(t, reconciled) { router.post(`/sahodaya-admin/${props.sahodaya.id}/finance/bank-reconciliation/${t.id}/reconcile`, { reconciled, bank_account_id: props.bankAccounts[0]?.id }, { preserveScroll: true }); }
</script>
