<template>
    <span class="status-pill" :class="billStatusPillClass(status)">{{ billStatusLabel(status) }}</span>
</template>

<script setup>
import { billStatusLabel, billStatusPillClass } from '@/support/foodBillStatus.js';

defineProps({
    status: { type: String, required: true },
});
</script>
