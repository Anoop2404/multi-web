<template>
    <Modal :show="show" size="xl" :title="title" @close="$emit('close')">
        <div class="space-y-3">
            <div class="relative bg-slate-100 rounded-lg overflow-hidden" style="height: 65vh;">
                <iframe v-if="currentUrl" :src="currentUrl" class="w-full h-full border-0" />
                <button v-if="images.length > 1 && index > 0" type="button" @click="prev"
                        class="absolute left-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/90 shadow flex items-center justify-center text-slate-700 hover:bg-white">
                    ←
                </button>
                <button v-if="images.length > 1 && index < images.length - 1" type="button" @click="next"
                        class="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-white/90 shadow flex items-center justify-center text-slate-700 hover:bg-white">
                    →
                </button>
            </div>
            <div v-if="images.length > 1" class="flex items-center justify-center gap-3">
                <span class="text-xs font-semibold text-slate-500">{{ index + 1 }} of {{ images.length }}</span>
                <div class="flex gap-1.5">
                    <button v-for="(img, i) in images" :key="i" type="button" @click="index = i"
                            class="w-2 h-2 rounded-full transition"
                            :class="i === index ? 'bg-indigo-600' : 'bg-slate-300 hover:bg-slate-400'"
                            :aria-label="`Go to image ${i + 1}`" />
                </div>
            </div>
        </div>
    </Modal>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import Modal from '@/Components/ui/Modal.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    images: { type: Array, default: () => [] }, // list of URL strings
    title: { type: String, default: 'Payment proof' },
});
defineEmits(['close']);

const index = ref(0);
// Every open should start on the first image, not wherever a prior receipt's gallery left off.
watch(() => props.show, (visible) => { if (visible) index.value = 0; });

const currentUrl = computed(() => props.images[index.value] ?? null);

function prev() {
    if (index.value > 0) index.value -= 1;
}
function next() {
    if (index.value < props.images.length - 1) index.value += 1;
}
</script>
