<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-[#041525]/50" @click="close"></div>
        <div class="relative card w-full max-w-4xl max-h-[85vh] overflow-hidden flex flex-col shadow-xl !p-0">
            <div class="px-5 py-4 border-b border-slate-100 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <h3 class="font-bold text-slate-900 truncate">{{ itemTitle || 'Points breakdown' }}</h3>
                    <p v-if="participants.length" class="text-xs text-slate-500 mt-1">{{ participants.length }} result(s)</p>
                </div>
                <button type="button" class="text-slate-400 hover:text-slate-600 text-xl leading-none shrink-0" @click="close">×</button>
            </div>

            <div class="px-5 py-3 overflow-y-auto flex-1">
                <p v-if="loading" class="text-sm text-slate-500 py-8 text-center">Loading…</p>
                <p v-else-if="error" class="text-sm text-red-600 py-4">{{ error }}</p>
                <p v-else-if="!participants.length" class="text-sm text-slate-500 py-8 text-center">
                    No marks entered for this item yet.
                </p>
                <div v-else class="overflow-x-auto">
                    <table class="data-table text-sm">
                        <thead>
                            <tr class="text-xs uppercase font-bold tracking-wider text-slate-700 bg-slate-50">
                                <th class="pl-4 py-3">Participant</th>
                                <th class="py-3">School</th>
                                <th class="py-3 text-center">Rank</th>
                                <th class="py-3 text-right">Rank Pts</th>
                                <th class="py-3 text-center">Grade</th>
                                <th class="py-3 text-right">Grade Pts</th>
                                <th class="py-3 pr-4 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(p, idx) in participants" :key="idx" class="text-sm">
                                <td class="pl-4 py-3 font-medium text-slate-900">{{ p.participant ?? '—' }}</td>
                                <td class="py-3 text-slate-600">{{ p.school ?? '—' }}</td>
                                <td class="py-3 text-center font-mono">{{ p.position ?? '—' }}</td>
                                <td class="py-3 text-right font-mono tabular-nums">{{ p.rank_points ?? '—' }}</td>
                                <td class="py-3 text-center font-semibold">{{ p.grade ?? '—' }}</td>
                                <td class="py-3 text-right font-mono tabular-nums">{{ p.grade_points ?? '—' }}</td>
                                <td class="py-3 pr-4 text-right font-mono font-bold tabular-nums">
                                    {{ p.total }}
                                    <span v-if="p.rank_points === null || p.grade_points === null"
                                          class="block text-[10px] font-normal text-slate-400 normal-case">custom rule</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="px-5 py-3 border-t border-slate-100 flex items-center justify-end bg-slate-50/80">
                <button type="button" class="btn-secondary text-sm" @click="close">Close</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    fetchUrl: { type: String, default: null },
    itemTitle: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const loading = ref(false);
const error = ref('');
const participants = ref([]);

function close() {
    emit('close');
}

async function load() {
    if (!props.fetchUrl) return;
    loading.value = true;
    error.value = '';
    participants.value = [];
    try {
        const res = await fetch(props.fetchUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error('Could not load this item’s breakdown.');
        const data = await res.json();
        participants.value = data.participants ?? [];
    } catch (e) {
        error.value = e.message || 'Failed to load.';
    } finally {
        loading.value = false;
    }
}

watch(() => [props.open, props.fetchUrl], ([isOpen]) => {
    if (isOpen) load();
}, { immediate: true });
</script>
