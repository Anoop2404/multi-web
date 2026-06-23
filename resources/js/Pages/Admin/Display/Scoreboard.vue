<template>
    <div class="min-h-screen bg-slate-950 text-white p-6">
        <header class="mb-8 text-center">
            <p class="text-amber-400 text-xs uppercase tracking-widest">Live Scoreboard</p>
            <h1 class="text-3xl font-bold mt-1">{{ event?.title ?? screen.title }}</h1>
            <p v-if="lastUpdated" class="text-xs text-white/40 mt-2">Updated {{ lastUpdated }}</p>
        </header>
        <ol class="max-w-xl mx-auto space-y-3">
            <li v-for="row in scoreboard" :key="row.school_id"
                class="flex justify-between items-center bg-white/5 border border-white/10 rounded-xl px-5 py-4">
                <span><span class="text-amber-400 font-bold mr-2">#{{ row.rank }}</span>{{ row.school_name }}</span>
                <span class="font-mono text-lg">{{ row.total_points }}</span>
            </li>
            <li v-if="!scoreboard.length" class="text-center text-white/40 py-8">No results yet</li>
        </ol>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';

defineProps({ screen: Object, event: Object, scoreboard: Array });

const lastUpdated = ref('');
let timer = null;

function refresh() {
    router.reload({
        only: ['scoreboard', 'event'],
        preserveScroll: true,
        onSuccess: () => {
            lastUpdated.value = new Date().toLocaleTimeString();
        },
    });
}

onMounted(() => {
    lastUpdated.value = new Date().toLocaleTimeString();
    timer = setInterval(refresh, 30000);
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
});
</script>
