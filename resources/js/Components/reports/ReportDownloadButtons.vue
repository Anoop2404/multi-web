<template>
    <div class="flex flex-wrap items-center gap-2">
        <a v-if="pdfUrl"
           :href="`${pdfUrl}${pdfUrl.includes('?') ? '&' : '?'}inline=1`"
           target="_blank"
           rel="noopener"
           class="btn-secondary text-sm flex items-center gap-1.5 shadow-sm">
            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            Preview PDF
        </a>
        <a v-if="pdfUrl"
           :href="`${pdfUrl}${pdfUrl.includes('?') ? '&' : '?'}download=1`"
           target="_blank"
           rel="noopener"
           class="btn-primary text-sm flex items-center gap-1.5 shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Download PDF
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
