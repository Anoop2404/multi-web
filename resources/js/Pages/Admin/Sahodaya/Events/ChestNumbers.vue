<template>
    <SahodayaEventsLayout :title="`${event.title} — Chest Numbers`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Chest Numbers`" eyebrow="Operations"
                    description="Generate, reveal, and print chest numbers." />
        <div class="flex flex-wrap gap-2 mb-4">
            <button @click="generate" class="btn-primary">Assign missing</button>
            <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/chest-numbers/print`" target="_blank"
               class="px-4 py-2 border rounded-lg text-sm">Print list</a>
            <button type="button" @click="showGreen = !showGreen"
                    class="px-4 py-2 border rounded-lg text-sm" :class="showGreen ? 'border-emerald-400 bg-emerald-50' : ''">
                Green room ({{ greenRoom.length }})
            </button>
        </div>

        <div v-if="showGreen" class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 mb-4">
            <h3 class="font-semibold text-sm mb-2 text-emerald-900">Green room — awaiting stage entry</h3>
            <table class="w-full text-sm bg-white border rounded-lg overflow-hidden">
                <thead class="bg-gray-50"><tr>
                    <th class="p-2 text-left">Chest</th><th class="p-2 text-left">Name</th><th class="p-2 text-left">Item</th><th class="p-2"></th>
                </tr></thead>
                <tbody>
                    <tr v-for="p in greenRoom" :key="p.id" class="border-t">
                        <td class="p-2 font-mono">{{ p.chest_no ?? '—' }}</td>
                        <td class="p-2">{{ p.name }}</td>
                        <td class="p-2">{{ p.item }}</td>
                        <td class="p-2 text-right">
                            <button @click="reveal(p.id)" class="text-indigo-600 text-xs font-semibold">Reveal</button>
                        </td>
                    </tr>
                    <tr v-if="!greenRoom.length"><td colspan="4" class="p-3 text-gray-400 text-center">No participants in green room</td></tr>
                </tbody>
            </table>
        </div>

        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50"><tr>
                    <th class="p-3 text-left">Chest</th><th class="p-3 text-left">Name</th>
                    <th class="p-3 text-left">School</th><th class="p-3 text-left">Item</th><th class="p-3 text-left">Team</th><th class="p-3"></th>
                </tr></thead>
                <tbody>
                    <tr v-for="p in participants" :key="p.id" class="border-t">
                        <td class="p-3 font-mono font-bold">{{ p.chest_no ?? '—' }}</td>
                        <td class="p-3">{{ p.name }}</td>
                        <td class="p-3">{{ p.school }}</td>
                        <td class="p-3">{{ p.item }}</td>
                        <td class="p-3">{{ p.group ?? '—' }}</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <button v-if="p.chest_no" @click="clearChest(p.id)" class="text-red-600 text-xs mr-2">Clear</button>
                            <button v-if="event.chest_reveal_mode === 'stage_entry' && !p.chest_revealed_at" @click="reveal(p.id)"
                                    class="text-indigo-600 text-xs">Reveal</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, participants: Array, greenRoom: { type: Array, default: () => [] },
    view: String,
    activityLogs: { type: Array, default: () => [] },
});

const showGreen = ref(props.view === 'green-room');

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/chest-numbers`;

function generate() {
    router.post(`${base}/generate`, {}, { preserveScroll: true });
}
function clearChest(id) {
    if (!confirm('Clear chest number for this participant?')) return;
    router.post(`${base}/${id}/clear`, {}, { preserveScroll: true });
}
function reveal(id) {
    router.post(`${base}/${id}/reveal`, {}, { preserveScroll: true });
}
</script>
