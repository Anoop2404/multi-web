<template>
    <SahodayaAdminLayout :title="`${event.title} — Chest Numbers`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="flex flex-wrap gap-2 mb-4">
            <button @click="generate" class="px-4 py-2 bg-indigo-600 text-white rounded-lg text-sm">Assign missing</button>
            <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/chest-numbers/print`" target="_blank"
               class="px-4 py-2 border rounded-lg text-sm">Print list</a>
        </div>
        <div class="bg-white border rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50"><tr>
                    <th class="p-3 text-left">Chest</th><th class="p-3 text-left">Name</th>
                    <th class="p-3 text-left">School</th><th class="p-3 text-left">Item</th><th class="p-3 text-left">Team</th>
                </tr></thead>
                <tbody>
                    <tr v-for="p in participants" :key="p.id" class="border-t">
                        <td class="p-3 font-mono font-bold">{{ p.chest_no ?? '—' }}</td>
                        <td class="p-3">{{ p.name }}</td>
                        <td class="p-3">{{ p.school }}</td>
                        <td class="p-3">{{ p.item }}</td>
                        <td class="p-3">{{ p.group ?? '—' }}</td>
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
    event: Object, participants: Array,
});

function generate() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/chest-numbers/generate`, {}, { preserveScroll: true });
}
</script>
