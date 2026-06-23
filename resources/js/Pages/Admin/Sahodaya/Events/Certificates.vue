<template>
    <SahodayaAdminLayout :title="`${event.title} — Certificates`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="mb-4">
            <button @click="generate" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Generate for top 3</button>
        </div>
        <ul class="bg-white border rounded-xl divide-y">
            <li v-for="c in certificates" :key="c.id" class="p-4 flex justify-between items-center text-sm">
                <div>
                    <p class="font-medium">{{ c.student?.name ?? 'Participant' }}</p>
                    <p class="text-gray-500 text-xs">{{ c.item?.title }} · Position {{ c.mark?.position ?? '—' }}</p>
                </div>
                <a :href="`/certificates/verify/${c.uuid}`" target="_blank" class="text-indigo-600 text-xs font-medium mr-3">Verify ↗</a>
                <a :href="`/certificates/print/${c.uuid}`" target="_blank" class="text-gray-600 text-xs font-medium">Print ↗</a>
            </li>
            <li v-if="!certificates.length" class="p-4 text-gray-400 text-sm">No certificates yet. Publish results or click Generate.</li>
        </ul>
    </SahodayaAdminLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, certificates: Array,
});

function generate() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/certificates/generate`, {}, { preserveScroll: true });
}
</script>
