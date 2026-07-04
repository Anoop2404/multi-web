<template>
    <div v-show="active" class="page-progress fixed top-0 left-0 right-0 z-[200] h-0.5 bg-[#fbbf24]/30">
        <div class="page-progress-bar h-full bg-[#0f3d7a] transition-[width] duration-200 ease-out" :style="{ width: `${progress}%` }" />
    </div>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const active = ref(false);
const progress = ref(0);
let timer = null;

function start() {
    active.value = true;
    progress.value = 12;
    clearInterval(timer);
    timer = setInterval(() => {
        if (progress.value < 88) {
            progress.value += Math.random() * 8;
        }
    }, 180);
}

function finish() {
    clearInterval(timer);
    progress.value = 100;
    setTimeout(() => {
        active.value = false;
        progress.value = 0;
    }, 220);
}

onMounted(() => {
    router.on('start', start);
    router.on('finish', finish);
    router.on('error', finish);
});

onUnmounted(() => {
    clearInterval(timer);
});
</script>
