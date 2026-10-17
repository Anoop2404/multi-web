<template>
    <header class="brand">
        <img v-if="logo && !broken" :src="logo" alt="" @error="broken = true" />
        <div v-else class="mark" aria-hidden="true">{{ initials }}</div>
        <div>
            <div class="name">{{ name || 'Online exam' }}</div>
            <div class="sub">{{ sub || 'Online exam' }}</div>
        </div>
    </header>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';

const props = defineProps<{ name?: string | null; sub?: string | null; logo?: string | null; code?: string | null }>();
const broken = ref(false);

const initials = computed(() => {
    if (props.code) return props.code.slice(0, 3);
    const words = String(props.name ?? 'Exam').split(/\s+/).filter(Boolean);
    return words.slice(0, 2).map((w) => w[0]).join('').toUpperCase() || 'EX';
});
</script>
