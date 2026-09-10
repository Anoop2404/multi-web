<template>
    <Modal :show="show" title="Share with the public" subtitle="Anyone with this link or QR code can follow the event live." size="md" @close="$emit('close')">
        <div class="space-y-5">
            <div class="flex flex-col items-center gap-3 py-2">
                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-3 shadow-sm">
                    <img :src="qrImageUrl" alt="QR code for the public event page" class="w-48 h-48 rounded-lg" width="192" height="192">
                </div>
                <p class="text-xs text-slate-500 text-center max-w-xs">
                    Print this on posters or notice boards — visitors scan it to open the event's public page instantly.
                </p>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">Public link</label>
                <div class="flex items-stretch gap-2">
                    <input type="text" readonly :value="publicUrl"
                           class="flex-1 min-w-0 text-sm font-mono text-slate-700 bg-slate-50 border border-slate-200 rounded-lg px-3 py-2 truncate"
                           @focus="$event.target.select()">
                    <button type="button" class="btn-secondary text-xs shrink-0 whitespace-nowrap" @click="copyLink">
                        {{ copied ? 'Copied ✓' : 'Copy link' }}
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold uppercase tracking-wide text-slate-500 mb-1.5">Download</label>
                <div class="grid grid-cols-2 gap-2">
                    <a :href="qrImageUrl" download class="btn-secondary text-xs text-center">
                        🖼️ Image (PNG)
                    </a>
                    <a :href="qrPdfUrl" download class="btn-secondary text-xs text-center">
                        📄 PDF poster
                    </a>
                </div>
            </div>
        </div>

        <template #footer>
            <a :href="publicUrl" target="_blank" rel="noopener" class="btn-secondary text-xs">
                Open public page ↗
            </a>
            <button type="button" class="btn-primary text-xs" @click="$emit('close')">Done</button>
        </template>
    </Modal>
</template>

<script setup>
import { ref } from 'vue';
import Modal from '@/Components/ui/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    publicUrl: { type: String, required: true },
    qrImageUrl: { type: String, required: true },
    qrPdfUrl: { type: String, required: true },
});

defineEmits(['close']);

const copied = ref(false);
function copyLink() {
    navigator.clipboard.writeText(props.publicUrl).then(() => {
        copied.value = true;
        setTimeout(() => { copied.value = false; }, 2000);
    });
}
</script>
