<template>
    <div class="min-h-screen bg-slate-950 text-white p-6">
        <header class="mb-8 text-center">
            <p class="text-amber-400 text-xs uppercase tracking-widest">Live Scoreboard</p>
            <h1 class="text-3xl font-bold mt-1">{{ event?.title ?? screen.title }}</h1>
            <p v-if="lastUpdated" class="text-xs text-white/40 mt-2">Updated {{ lastUpdated }}</p>
        </header>

        <div v-if="nowPerforming" class="max-w-xl mx-auto mb-8 p-4 bg-white/10 rounded-xl text-center">
            <p class="text-xs text-amber-300 uppercase">Now performing</p>
            <p class="font-semibold mt-1">{{ nowPerforming.item_title }}</p>
            <p v-if="nowPerforming.reference && nowPerforming.reference !== '—'" class="font-mono text-lg mt-1">#{{ nowPerforming.reference }}</p>
            <p v-if="nowPerforming.show_name && nowPerforming.name" class="text-sm text-white/80 mt-1">{{ nowPerforming.name }}</p>
        </div>

        <div v-if="nextUp?.length" class="max-w-xl mx-auto mb-8">
            <p class="text-xs text-white/50 uppercase mb-2">Up next</p>
            <ul class="space-y-2 text-sm">
                <li v-for="(row, i) in nextUp" :key="i" class="flex justify-between bg-white/5 rounded-lg px-4 py-2">
                    <span>{{ row.order ? `#${row.order}` : '' }} {{ row.item_title }}</span>
                    <span class="font-mono text-amber-300">{{ row.reference && row.reference !== '—' ? `#${row.reference}` : '' }}</span>
                </li>
            </ul>
        </div>

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

const props = defineProps({ screen: Object, event: Object, scoreboard: Array, nowPerforming: Object, nextUp: Array });

const scoreboard = ref(props.scoreboard ?? []);
const nowPerforming = ref(props.nowPerforming ?? null);
const nextUp = ref(props.nextUp ?? []);
const lastUpdated = ref('');
let timer = null;

function refresh() {
    router.reload({
        only: ['scoreboard', 'event', 'nowPerforming', 'nextUp'],
        preserveScroll: true,
        onSuccess: (page) => {
            scoreboard.value = page.props.scoreboard ?? [];
            nowPerforming.value = page.props.nowPerforming ?? null;
            nextUp.value = page.props.nextUp ?? [];
            lastUpdated.value = new Date().toLocaleTimeString();
        },
    });
}

onMounted(() => {
    lastUpdated.value = new Date().toLocaleTimeString();
    timer = setInterval(refresh, 30000);

    if (window.Echo && props.event?.id) {
        const tenantId = window.location.pathname.split('/')[2];
        window.Echo.channel(`fest-scoreboard.${tenantId}.${props.event.id}`)
            .listen('.scoreboard.updated', (payload) => {
                if (payload?.scoreboard) {
                    scoreboard.value = payload.scoreboard;
                    lastUpdated.value = new Date().toLocaleTimeString();
                }
            });
    }
});

onUnmounted(() => {
    if (timer) clearInterval(timer);
    if (props.event?.id && window.Echo) {
        const tenantId = window.location.pathname.split('/')[2];
        window.Echo.leave(`fest-scoreboard.${tenantId}.${props.event.id}`);
    }
});
</script>
