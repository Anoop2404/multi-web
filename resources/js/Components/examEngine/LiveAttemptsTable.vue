<template>
    <div class="overflow-x-auto">
        <table class="data-table min-w-[860px] text-sm">
            <thead>
                <tr>
                    <th>Candidate</th>
                    <th v-if="showSchool">School</th>
                    <th>Status</th>
                    <th>Progress</th>
                    <th>Last seen</th>
                    <th>Device</th>
                    <th v-if="hasActions" class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in rows" :key="row.reg_id" :class="row.state === 'locked' ? 'bg-red-50/60' : (row.offline ? 'bg-amber-50/60' : '')">
                    <td>
                        <p class="font-medium text-slate-900">{{ row.name || `Registration #${row.reg_id}` }}</p>
                        <p class="text-xs text-slate-500">
                            <span v-if="row.hall_ticket" class="font-mono">HT {{ row.hall_ticket }}</span>
                            <span v-if="row.hall_ticket && row.class_name"> · </span>
                            <span v-if="row.class_name">{{ row.class_name }}</span>
                        </p>
                    </td>
                    <td v-if="showSchool" class="text-xs text-slate-600">{{ row.school_name || '—' }}</td>
                    <td>
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold" :class="stateClass(row)">
                            {{ stateLabel(row) }}
                        </span>
                        <span v-if="row.offline" class="ml-1 inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800">Offline</span>
                        <p v-if="row.state === 'submitted' && row.submit_mode" class="mt-0.5 text-[11px] text-slate-500">
                            {{ submitModeLabel(row.submit_mode) }} · {{ formatTime(row.submitted_at) }}
                        </p>
                        <p v-else-if="row.checked_in_at" class="mt-0.5 text-[11px] text-slate-500">In at {{ formatTime(row.checked_in_at) }}</p>
                        <p v-else class="mt-0.5 text-[11px] text-slate-400">Not logged in yet</p>
                    </td>
                    <td class="text-xs">
                        <p><span class="font-semibold tabular-nums">{{ row.answered }}</span> answered</p>
                        <p v-if="row.deadline && row.state !== 'submitted'" class="text-slate-500">{{ timeLeft(row.deadline, serverTime) }} · ends {{ formatTime(row.deadline) }}</p>
                        <p v-if="row.extra_minutes" class="text-indigo-700">+{{ row.extra_minutes }} min extra</p>
                        <p v-if="row.flags" class="font-semibold text-amber-700" title="Proctor signals: tab switches, window blur, full-screen exit, offline gaps">⚑ {{ row.flags }} flag{{ row.flags === 1 ? '' : 's' }}</p>
                    </td>
                    <td class="text-xs text-slate-600">{{ row.last_seen_at ? ago(row.last_seen_at, serverTime) : '—' }}</td>
                    <td class="max-w-[12rem] text-xs text-slate-500">
                        <p class="truncate" :title="row.device || ''">{{ row.device || '—' }}</p>
                        <p v-if="row.ip" class="font-mono text-[11px] text-slate-400">{{ row.ip }}</p>
                    </td>
                    <td v-if="hasActions" class="text-right">
                        <div class="flex flex-wrap justify-end gap-1">
                            <button
                                v-for="action in actionsFor(row)"
                                :key="action.key"
                                type="button"
                                class="rounded-md border px-2 py-1 text-xs font-semibold transition"
                                :class="action.danger ? 'border-red-200 text-red-700 hover:bg-red-50' : 'border-slate-200 text-slate-700 hover:bg-slate-50'"
                                :title="action.hint"
                                @click="$emit('act', { action: action.key, row })"
                            >
                                {{ action.label }}
                            </button>
                            <span v-if="!actionsFor(row).length" class="text-xs text-slate-400">—</span>
                        </div>
                    </td>
                </tr>
                <tr v-if="!rows.length">
                    <td :colspan="columnCount" class="py-10 text-center text-sm text-slate-500">
                        No candidates match these filters.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { ATTEMPT_STATE_LABELS, SUBMIT_MODE_LABELS, ago, formatTime, timeLeft } from './liveMonitorApi.js';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    serverTime: { type: String, default: null },
    showSchool: { type: Boolean, default: true },
    allowedActions: { type: Array, default: () => [] },
    armed: { type: Boolean, default: false },
    extendCap: { type: Number, default: null },
});

defineEmits(['act']);

const hasActions = computed(() => props.armed && props.allowedActions.length > 0);
const columnCount = computed(() => 5 + (props.showSchool ? 1 : 0) + (hasActions.value ? 1 : 0));

function stateLabel(row) {
    return ATTEMPT_STATE_LABELS[row.state] ?? 'Not logged in';
}

function submitModeLabel(mode) {
    return SUBMIT_MODE_LABELS[mode] ?? mode;
}

function stateClass(row) {
    return {
        checked_in: 'bg-indigo-100 text-indigo-800',
        waiting: 'bg-sky-100 text-sky-800',
        writing: 'bg-blue-100 text-blue-800',
        submitted: 'bg-emerald-100 text-emerald-800',
        locked: 'bg-red-100 text-red-800',
    }[row.state] ?? 'bg-slate-100 text-slate-600';
}

function actionsFor(row) {
    const can = (key) => props.allowedActions.includes(key);
    const open = row.state !== 'submitted' && row.state !== 'ended';
    const list = [];

    if (can('extend') && open && (props.extendCap === null || (row.extend_left ?? 0) > 0)) {
        list.push({ key: 'extend', label: '+ Time', hint: props.extendCap === null ? 'Give extra minutes' : `Give extra minutes (${row.extend_left ?? 0} of ${props.extendCap} left for this candidate)` });
    }
    if (can('allow_switch') && open) {
        list.push({ key: 'allow_switch', label: 'Allow switch', hint: 'Let the candidate log in once from another device' });
    }
    if (can('unlock') && row.state === 'locked') {
        list.push({ key: 'unlock', label: 'Unlock', hint: 'Let the candidate continue writing' });
    }
    if (can('lock') && row.state === 'writing') {
        list.push({ key: 'lock', label: 'Lock', hint: 'Hold the attempt: no more answers are accepted until unlocked', danger: true });
    }
    if (can('reissue_passcode') && row.state !== 'submitted') {
        list.push({ key: 'reissue_passcode', label: 'New passcode', hint: 'Issue a new exam passcode (the old one stops working)' });
    }
    if (can('force_submit') && (row.state === 'writing' || row.state === 'locked')) {
        list.push({ key: 'force_submit', label: 'Force submit', hint: 'End this attempt now with the answers saved so far', danger: true });
    }

    return list;
}
</script>
