<template>
    <SahodayaAdminLayout title="Accounts & Ledger" :sahodaya="sahodaya" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount">
        <div class="grid md:grid-cols-3 gap-4 mb-6">
            <div class="bg-green-50 border border-green-100 rounded-xl p-4">
                <p class="text-xs text-green-700 uppercase font-bold">Credits</p>
                <p class="text-2xl font-bold text-green-900">₹{{ summary?.credit ?? '0.00' }}</p>
            </div>
            <div class="bg-red-50 border border-red-100 rounded-xl p-4">
                <p class="text-xs text-red-700 uppercase font-bold">Debits</p>
                <p class="text-2xl font-bold text-red-900">₹{{ summary?.debit ?? '0.00' }}</p>
            </div>
        </div>
        <div class="bg-white border rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50"><tr><th class="p-3 text-left">Date</th><th class="p-3 text-left">Head</th><th class="p-3 text-left">Type</th><th class="p-3 text-right">Amount</th></tr></thead>
                <tbody>
                    <tr v-for="t in transactions" :key="t.id" class="border-t">
                        <td class="p-3">{{ t.transaction_date }}</td>
                        <td class="p-3">{{ t.account_head?.name }}</td>
                        <td class="p-3">{{ t.entry_type }}</td>
                        <td class="p-3 text-right font-mono">₹{{ t.amount }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
defineProps({ sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number, heads: Array, transactions: Array, summary: Object });
</script>
