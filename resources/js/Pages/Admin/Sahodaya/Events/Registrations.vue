<template>
    <SahodayaAdminLayout :title="`${event.title} — Registrations`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="bg-white border rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="p-3">School</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Fee</th>
                        <th class="p-3">Participants</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="reg in registrations" :key="reg.id" class="border-t">
                        <td class="p-3">{{ schools[reg.school_id] ?? reg.school_id }}</td>
                        <td class="p-3">{{ reg.item?.title ?? '—' }}</td>
                        <td class="p-3">{{ reg.status }}</td>
                        <td class="p-3 text-xs">
                            <template v-if="reg.fee_required">₹{{ reg.fee_due }}
                                <span v-if="reg.fee_receipt" class="text-gray-500">({{ reg.fee_receipt.status }})</span>
                                <span v-else class="text-amber-600">pending upload</span>
                            </template>
                            <span v-else>—</span>
                        </td>
                        <td class="p-3">{{ reg.participants?.length ?? 0 }}</td>
                        <td class="p-3 text-right space-x-2" v-if="reg.status === 'submitted'">
                            <button @click="approve(reg.id)" class="text-green-600 text-xs font-medium">Approve</button>
                            <button @click="reject(reg.id)" class="text-red-600 text-xs font-medium">Reject</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, registrations: Array, schools: Object,
});

function approve(id) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/${id}/approve`, {}, { preserveScroll: true });
}
function reject(id) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/registrations/${id}/reject`, {}, { preserveScroll: true });
}
</script>
