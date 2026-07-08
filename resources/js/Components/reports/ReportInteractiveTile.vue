<template>
    <Link :href="report.href" class="reports-tile group block">
        <span class="reports-tile-icon" aria-hidden="true">{{ report.icon ?? '📊' }}</span>
        <div class="reports-tile-body">
            <p class="reports-tile-title">{{ report.label }}</p>
            <p v-if="report.hint" class="reports-tile-hint">{{ report.hint }}</p>
        </div>
        <span class="reports-tile-arrow" aria-hidden="true">→</span>
    </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    report: { type: Object, required: true },
});
</script>
