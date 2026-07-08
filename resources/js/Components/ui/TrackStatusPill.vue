<template>
    <span class="track-status-pill" :class="trackStatusPillClass(status)">
        {{ trackStatusLabel(status) }}
    </span>
</template>

<script setup>
import { trackStatusLabel, trackStatusPillClass } from '@/support/submissionTrackStatus.js';

defineProps({
    status: { type: String, default: '' },
});
</script>
