<template>
    <SahodayaEventsLayout :title="`${event.title} — Leaderboard Hub`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Leaderboard Hub`" eyebrow="Scoring"
                    description="School, house, and championship scoreboards." />
        <div class="flex flex-wrap gap-2 mb-6">
            <Link v-for="l in links" :key="l.href" :href="l.href" :target="l.external ? '_blank' : undefined"
                  class="px-3 py-2 bg-white border rounded-lg text-sm hover:border-indigo-300 font-medium">
                {{ l.label }}{{ l.external ? ' ↗' : '' }}
            </Link>
        </div>

        <div class="grid lg:grid-cols-2 gap-4">
            <section class="card">
                <h3 class="font-semibold text-sm mb-3">School standings (top 10)</h3>
                <ol class="text-sm divide-y">
                    <li v-for="row in schoolBoard" :key="row.school_id" class="py-2 flex justify-between">
                        <span>#{{ row.rank }} {{ row.school_name }}</span>
                        <span class="font-mono font-semibold">{{ row.total_points }}</span>
                    </li>
                    <li v-if="!schoolBoard.length" class="py-4 text-gray-400 text-center">No scores yet</li>
                </ol>
            </section>

            <section class="card">
                <h3 class="font-semibold text-sm mb-3">Cluster house standings</h3>
                <ol class="text-sm divide-y">
                    <li v-for="row in houseBoard" :key="row.house_id" class="py-2 flex justify-between">
                        <span>#{{ row.rank }} {{ row.house_name }}</span>
                        <span class="font-mono font-semibold">{{ row.total_points }}</span>
                    </li>
                    <li v-if="!houseBoard.length" class="py-4 text-gray-400 text-center">No house data</li>
                </ol>
            </section>

            <section class="card">
                <h3 class="font-semibold text-sm mb-3">Individual championship</h3>
                <ol class="text-sm divide-y">
                    <li v-for="row in championship" :key="row.rank" class="py-2">
                        <span class="font-medium">#{{ row.rank }} {{ row.name }}</span>
                        <span class="text-gray-500 text-xs block">{{ row.school }} · {{ row.points }} pts</span>
                    </li>
                    <li v-if="!championship.length" class="py-4 text-gray-400 text-center">Recalculate from championship page</li>
                </ol>
            </section>

            <section class="card">
                <h3 class="font-semibold text-sm mb-3">Athletic record breaks</h3>
                <ul class="text-sm space-y-2">
                    <li v-for="b in recordBreaks" :key="b.id" class="bg-amber-50 border border-amber-100 rounded-lg p-2">
                        <p class="font-medium">{{ b.item?.title }}</p>
                        <p class="text-xs text-gray-600">{{ b.participant?.student?.name }} · {{ b.new_value }} {{ b.record_unit }}</p>
                        <p class="text-xs text-amber-800">{{ b.prize_label }}</p>
                    </li>
                    <li v-if="!recordBreaks.length" class="py-4 text-gray-400 text-center">No record breaks</li>
                </ul>
            </section>
        </div>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, schoolBoard: Array, houseBoard: Array, championship: Array,
    recordBreaks: Array, records: Array, links: Array,
    activityLogs: { type: Array, default: () => [] },
});
</script>
