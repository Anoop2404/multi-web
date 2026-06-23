<template>
    <SahodayaAdminLayout :title="`${event.title} — Appeals`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="bg-white border rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="p-3">Participant</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Reason</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in appeals" :key="a.id" class="border-t">
                        <td class="p-3">{{ a.participant?.student?.name ?? '—' }}</td>
                        <td class="p-3">{{ a.participant?.registration?.item?.title }}</td>
                        <td class="p-3 text-xs max-w-xs truncate">{{ a.reason }}</td>
                        <td class="p-3">{{ a.status }}</td>
                        <td class="p-3 text-right space-x-2" v-if="a.status === 'pending'">
                            <button @click="resolve(a.id, 'approved')" class="text-green-600 text-xs">Approve</button>
                            <button @click="resolve(a.id, 'rejected')" class="text-red-600 text-xs">Reject</button>
                        </td>
                    </tr>
                    <tr v-if="!appeals.length"><td colspan="5" class="p-6 text-center text-gray-400">No appeals</td></tr>
                </tbody>
            </table>
        </div>

        <div v-if="disqualified?.length" class="mt-6 bg-white border rounded-xl p-4">
            <h3 class="font-semibold text-sm mb-2">Disqualified participants</h3>
            <ul class="text-sm divide-y">
                <li v-for="p in disqualified" :key="p.id" class="py-2 flex justify-between">
                    <span>{{ p.student?.name }} — {{ p.disqualification_reason }}</span>
                    <button @click="reinstate(p.id)" class="text-indigo-600 text-xs">Reinstate</button>
                </li>
            </ul>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, appeals: Array, disqualified: Array,
});

function resolve(id, status) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/appeals/${id}/resolve`, { status }, { preserveScroll: true });
}

function reinstate(participantId) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/participants/${participantId}/reinstate`, {}, { preserveScroll: true });
}
</script>
