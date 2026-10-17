<template>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-8">
        <component
            :is="tile.state || tile.flag ? 'button' : 'div'"
            v-for="tile in tiles"
            :key="tile.key"
            :type="tile.state || tile.flag ? 'button' : undefined"
            class="card card--muted !py-4 text-center transition"
            :class="[
                tile.state || tile.flag ? 'cursor-pointer hover:ring-2 hover:ring-slate-300' : '',
                (activeState && tile.state === activeState) || (tile.flag && flaggedActive) ? 'ring-2 ring-[color:var(--brand-navy)]' : '',
            ]"
            :title="tile.hint"
            @click="onClick(tile)"
        >
            <p class="text-3xl font-bold tabular-nums" :class="tile.tone">{{ tile.value ?? '—' }}</p>
            <p class="mt-1 text-xs font-medium text-slate-500">{{ tile.label }}</p>
        </component>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    counts: { type: Object, default: () => ({}) },
    activeState: { type: String, default: '' },
    flaggedActive: { type: Boolean, default: false },
});

const emit = defineEmits(['filter-state', 'filter-flagged']);

function onClick(tile) {
    if (tile.state) {
        emit('filter-state', tile.state === props.activeState ? '' : tile.state);
    } else if (tile.flag) {
        emit('filter-flagged', !props.flaggedActive);
    }
}

const tiles = computed(() => [
    { key: 'roster', label: 'On roster', value: props.counts.roster, tone: 'text-slate-900', hint: 'Candidates who can log in to this exam' },
    { key: 'checked_in', label: 'Checked in', value: props.counts.checked_in, tone: 'text-indigo-700', hint: 'Logged in at least once' },
    { key: 'waiting', label: 'Waiting', value: props.counts.waiting, tone: 'text-sky-700', state: 'waiting', hint: 'In the waiting room' },
    { key: 'writing', label: 'Writing', value: props.counts.writing, tone: 'text-blue-700', state: 'writing', hint: 'Started and not yet submitted' },
    { key: 'submitted', label: 'Submitted', value: props.counts.submitted, tone: 'text-emerald-700', state: 'submitted', hint: 'Finished (by the student, at time-up, or forced)' },
    { key: 'offline', label: 'Offline', value: props.counts.offline, tone: (props.counts.offline ?? 0) > 0 ? 'text-amber-600' : 'text-slate-400', hint: 'Writing but not seen for over 60 seconds' },
    { key: 'locked', label: 'Locked', value: props.counts.locked, tone: (props.counts.locked ?? 0) > 0 ? 'text-red-700' : 'text-slate-400', state: 'locked', hint: 'Held by the exam team' },
    { key: 'flagged', label: 'Flagged', value: props.counts.flagged, tone: (props.counts.flagged ?? 0) > 0 ? 'text-amber-700' : 'text-slate-400', flag: true, hint: 'Candidates with proctor signals (tab switches, offline gaps…)' },
].filter((tile) => tile.value !== null && tile.value !== undefined));
</script>
