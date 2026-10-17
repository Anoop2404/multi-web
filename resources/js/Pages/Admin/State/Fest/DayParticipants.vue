<template>
    <StateEventWorkspace :event="event" :events="events" :sahodayas="sahodayas" :permissions="permissions">
        <h1 class="mb-4 text-xl font-bold">Day-wise participants</h1>
        <DayParticipationSummary :summary="dayParticipation" :report-url="dayParticipationReportUrl" />
    </StateEventWorkspace>
</template>
<script setup>
import StateEventWorkspace from '@/Components/state/fest/StateEventWorkspace.vue';
import DayParticipationSummary from '@/Components/events/DayParticipationSummary.vue';
defineProps({ event: Object, sahodaya: Object, events: Array, sahodayas: Array, permissions: Array, dayParticipation: Object, dayParticipationReportUrl: String });
</script>
