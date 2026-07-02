<template>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
         :class="iconClass" v-html="path" />
</template>

<script setup>
import { computed } from 'vue';
import { SAHODAYA_NAV_ICONS } from './SahodayaNavIcons.js';

const props = defineProps({
    name: { type: String, required: true },
    class: { type: String, default: 'w-4 h-4' },
});

const path = computed(() => SAHODAYA_NAV_ICONS[props.name] ?? '');
const iconClass = computed(() => props.class || 'w-4 h-4');
</script>
