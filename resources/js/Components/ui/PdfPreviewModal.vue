<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-900/70 p-4 sm:p-8" @click.self="close">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-5xl h-full flex flex-col overflow-hidden">
                <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-slate-200 bg-slate-50">
                    <div class="min-w-0">
                        <p class="font-bold text-slate-900 text-sm truncate">{{ title }}</p>
                        <p class="text-xs text-slate-500">PDF preview — nothing is downloaded until you choose to.</p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a :href="pdfUrl" target="_blank" rel="noopener" class="btn-secondary text-xs">↗ Open in new tab</a>
                        <a :href="downloadUrl" class="btn-primary text-xs">📥 Download</a>
                        <button type="button" class="btn-secondary text-xs" @click="close">✕ Close</button>
                    </div>
                </div>
                <div class="flex-1 bg-slate-100">
                    <iframe v-if="pdfUrl" :src="pdfUrl" class="w-full h-full border-0" title="PDF preview"></iframe>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    pdfUrl: { type: String, default: '' },
    title: { type: String, default: 'PDF Preview' },
});

const emit = defineEmits(['close']);

const downloadUrl = computed(() => {
    if (!props.pdfUrl) return '#';
    const sep = props.pdfUrl.includes('?') ? '&' : '?';
    return `${props.pdfUrl}${sep}download=1`;
});

function close() {
    emit('close');
}
</script>
