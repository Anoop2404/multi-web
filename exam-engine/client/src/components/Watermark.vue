<template>
    <div class="watermark" aria-hidden="true" :style="{ backgroundImage: image }" />
</template>

<script setup lang="ts">
import { computed } from 'vue';

/** Faint, repeated "name · hall ticket" across the screen (plan §4.5). Doesn't catch clicks. */
const props = defineProps<{ name: string; hallTicket: string }>();

function xml(text: string): string {
    return text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

const image = computed(() => {
    const label = xml(`${props.name} · ${props.hallTicket}`.slice(0, 80));
    const svg =
        `<svg xmlns="http://www.w3.org/2000/svg" width="340" height="200">` +
        `<text x="20" y="120" transform="rotate(-24 170 100)" fill="#0f172a" fill-opacity="0.07" ` +
        `font-family="system-ui, -apple-system, Segoe UI, Roboto, Noto Sans Malayalam, sans-serif" font-size="17" font-weight="600">${label}</text></svg>`;
    return `url("data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}")`;
});
</script>
