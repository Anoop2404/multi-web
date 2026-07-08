<template>
    <SahodayaEventsLayout title="School rankings" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :program="program"
                         :program-events="programEvents" :show-header-title="false">
        <PageHeader
            title="School rankings"
            eyebrow="Sports Meet"
            description="Medal table from published sports results (5 pts gold · 3 silver · 1 bronze)."
        />

        <section class="card card--flush overflow-hidden">
            <div class="overflow-x-auto">
                <table v-if="rankings.length" class="data-table">
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>School</th>
                            <th>Gold</th>
                            <th>Silver</th>
                            <th>Bronze</th>
                            <th>Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rankings" :key="row.school_id">
                            <td class="font-semibold">{{ row.rank }}</td>
                            <td class="font-medium">{{ row.school_name }}</td>
                            <td>{{ row.gold }}</td>
                            <td>{{ row.silver }}</td>
                            <td>{{ row.bronze }}</td>
                            <td class="font-semibold text-[#0f3d7a]">{{ row.points }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-if="!rankings.length" class="p-6 text-sm text-slate-500">No ranking data yet — publish sports results first.</p>
        </section>
    </SahodayaEventsLayout>
</template>

<script setup>
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    programEvents: { type: Array, default: () => [] },
    events: { type: Array, default: () => [] },
    rankings: { type: Array, default: () => [] },
});
</script>
