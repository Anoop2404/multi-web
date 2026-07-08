<template>
    <component :is="href ? Link : 'div'"
               :href="href"
               class="dash-stat group"
               :class="[`dash-stat--${tone}`, href ? 'dash-stat--link' : '']">
        <div class="dash-stat-icon" aria-hidden="true">{{ icon }}</div>
        <div class="dash-stat-body min-w-0">
            <p class="dash-stat-value">{{ value }}</p>
            <p class="dash-stat-label">{{ label }}</p>
            <p v-if="hint" class="dash-stat-hint">{{ hint }}</p>
        </div>
        <span v-if="href" class="dash-stat-arrow" aria-hidden="true">→</span>
    </component>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    label: { type: String, required: true },
    value: { type: [String, Number], required: true },
    icon: { type: String, default: '📊' },
    hint: { type: String, default: null },
    href: { type: String, default: null },
    tone: { type: String, default: 'navy' },
});
</script>
