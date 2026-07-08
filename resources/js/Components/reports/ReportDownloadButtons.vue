<template>
    <div class="flex flex-wrap items-center gap-2">
        <a v-if="pdfUrl"
           :href="pdfUrl"
           target="_blank"
           rel="noopener"
           class="btn-primary text-sm">
            Download PDF ↓
        </a>
        <a v-if="csvUrl"
           :href="csvUrl"
           target="_blank"
           rel="noopener"
           class="btn-secondary text-sm">
            Download CSV ↓
        </a>
        <a v-if="xlsUrl"
           :href="xlsUrl"
           target="_blank"
           rel="noopener"
           class="btn-secondary text-sm">
            Download Excel ↓
        </a>
    </div>
</template>

<script setup>
defineProps({
    pdfUrl: { type: String, default: null },
    csvUrl: { type: String, default: null },
    xlsUrl: { type: String, default: null },
});
</script>
