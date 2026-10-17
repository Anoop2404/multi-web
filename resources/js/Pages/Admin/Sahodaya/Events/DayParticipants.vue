<template>
    <SahodayaEventsLayout :title="`${event.title} — Day-wise participants`" :sahodaya="sahodaya" :event="event" :show-header-title="false">
        <h1 class="mb-4 text-xl font-bold">Day-wise participants</h1>
        <DayParticipationSummary :summary="dayParticipation" :report-url="dayParticipationReportUrl" />
    </SahodayaEventsLayout>
</template>
<script setup>
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import DayParticipationSummary from '@/Components/events/DayParticipationSummary.vue';
defineProps({ event: Object, sahodaya: Object, events: Array, sahodayas: Array, permissions: Array, dayParticipation: Object, dayParticipationReportUrl: String });
</script>
