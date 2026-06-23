<template>
    <SahodayaAdminLayout :title="`${event.title} — Event Fees`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
            <div class="bg-white border rounded-xl p-4 text-center">
                <p class="text-2xl font-bold">₹{{ summary.total_due }}</p>
                <p class="text-xs text-gray-500">Total due</p>
            </div>
            <div class="bg-white border rounded-xl p-4 text-center">
                <p class="text-2xl font-bold text-green-700">₹{{ summary.total_paid }}</p>
                <p class="text-xs text-gray-500">Collected</p>
            </div>
            <div class="bg-white border rounded-xl p-4 text-center">
                <p class="text-2xl font-bold text-amber-600">{{ summary.pending }}</p>
                <p class="text-xs text-gray-500">Not uploaded</p>
            </div>
            <div class="bg-white border rounded-xl p-4 text-center">
                <p class="text-2xl font-bold text-indigo-600">{{ summary.awaiting }}</p>
                <p class="text-xs text-gray-500">Awaiting approval</p>
            </div>
        </div>

        <div class="bg-white border rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="p-3">School</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Due</th>
                        <th class="p-3">Paid</th>
                        <th class="p-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.id" class="border-t">
                        <td class="p-3">{{ row.school }}</td>
                        <td class="p-3">{{ row.item }}</td>
                        <td class="p-3">₹{{ row.due }}</td>
                        <td class="p-3">{{ row.fee_receipt ? `₹${row.fee_receipt.amount}` : '—' }}</td>
                        <td class="p-3">{{ row.fee_receipt?.status ?? (row.due > 0 ? 'pending' : 'n/a') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p class="text-xs text-gray-500 mt-3">Approved fees are auto-posted to Accounts under EVENT-FEE.</p>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, rows: Array, summary: Object,
});
</script>
