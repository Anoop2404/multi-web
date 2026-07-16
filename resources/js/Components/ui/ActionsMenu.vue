<template>
    <div class="relative inline-block" ref="rootEl">
        <button type="button" class="btn-secondary text-sm" @click="open = !open">
            {{ label }}
            <span class="ml-1 text-[10px]" :class="{ 'rotate-180': open }">▾</span>
        </button>
        <div v-if="open"
             class="absolute right-0 z-30 mt-2 w-64 rounded-xl border border-slate-200 bg-white py-1.5 shadow-lg"
             @click="open = false">
            <slot />
        </div>
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

defineProps({
    label: { type: String, default: 'More actions' },
});

const open = ref(false);
const rootEl = ref(null);

function onClickOutside(e) {
    if (rootEl.value && !rootEl.value.contains(e.target)) {
        open.value = false;
    }
}

function onKeydown(e) {
    if (e.key === 'Escape') open.value = false;
}

onMounted(() => {
    document.addEventListener('click', onClickOutside);
    document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    document.removeEventListener('click', onClickOutside);
    document.removeEventListener('keydown', onKeydown);
});
</script>
