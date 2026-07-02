<template>
    <SahodayaEventsLayout :title="`${event.title} — Individual Championship`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Individual Championship`" eyebrow="Scoring"
                    description="Individual championship points leaderboard." />
        <div class="flex justify-between items-center mb-4">
            <p class="text-sm text-gray-600">IC points leaderboard from published marks.</p>
            <button @click="recalculate" class="btn-primary">Recalculate from marks</button>
        </div>
        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50"><tr>
                    <th class="p-3 text-left">Rank</th><th class="p-3 text-left">Student</th>
                    <th class="p-3 text-left">School</th><th class="p-3 text-left">Category</th><th class="p-3 text-right">Points</th>
                </tr></thead>
                <tbody>
                    <tr v-for="row in leaderboard" :key="row.student.id" class="border-t">
                        <td class="p-3 font-bold">#{{ row.rank }}</td>
                        <td class="p-3">{{ row.student.name }} <span class="text-xs text-gray-400 font-mono">{{ row.student.reg_no }}</span></td>
                        <td class="p-3">{{ row.school }}</td>
                        <td class="p-3 uppercase text-xs">{{ row.category }} · {{ row.gender }}</td>
                        <td class="p-3 text-right font-mono font-semibold">{{ row.points }}</td>
                    </tr>
                    <tr v-if="!leaderboard.length"><td colspan="5" class="p-4 text-gray-400 text-center">No championship points yet</td></tr>
                </tbody>
            </table>
        </div>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, leaderboard: Array,
    activityLogs: { type: Array, default: () => [] },
});

function recalculate() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/championship/recalculate`, {}, { preserveScroll: true });
}
</script>
