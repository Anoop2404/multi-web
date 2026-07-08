<template>
    <article class="reports-tile reports-tile--static group">
        <span class="reports-tile-icon" aria-hidden="true">{{ report.icon ?? '📊' }}</span>
        <div class="reports-tile-body">
            <p class="reports-tile-title">{{ report.label }}</p>
            <p v-if="report.hint" class="reports-tile-hint">{{ report.hint }}</p>
        </div>
        <div class="flex shrink-0 flex-col gap-1.5 sm:flex-row sm:items-center">
            <Link v-if="report.hasPreview && previewHref"
                  :href="previewHref"
                  class="btn-secondary text-xs px-3 py-2 whitespace-nowrap">
                Preview
            </Link>
            <a v-if="pdfHref"
               :href="pdfHref"
               target="_blank"
               rel="noopener"
               class="btn-primary text-xs px-3 py-2 whitespace-nowrap">
                PDF ↓
            </a>
            <a v-if="dataHref"
               :href="dataHref"
               target="_blank"
               rel="noopener"
               class="btn-secondary text-xs px-3 py-2 whitespace-nowrap">
                {{ dataLabel }} ↓
            </a>
        </div>
    </article>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    report: { type: Object, required: true },
    previewHref: { type: String, default: null },
    pdfHref: { type: String, default: null },
    dataHref: { type: String, default: null },
    dataLabel: { type: String, default: 'Export' },
});
</script>
