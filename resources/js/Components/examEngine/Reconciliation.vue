<template>
    <div class="space-y-4">
        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div v-for="item in items" :key="item.key" class="rounded-lg border px-3 py-2" :class="item.warn ? 'border-amber-300 bg-amber-50' : 'border-slate-200 bg-white'">
                <dt class="text-xs text-slate-500">{{ item.label }}</dt>
                <dd class="text-xl font-bold tabular-nums" :class="item.warn ? 'text-amber-800' : 'text-slate-900'">{{ item.value ?? '—' }}</dd>
            </div>
        </dl>
        <p v-if="rosterMismatch" class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">
            {{ registered }} approved registrations here but {{ counts.roster }} on the engine roster. Late approvals or cancellations after arming
            explain small differences; check before closing.
        </p>

        <div v-if="bySchool.length > 1 || showSchools" class="max-h-64 overflow-auto rounded-lg border border-slate-200">
            <table class="data-table text-xs">
                <thead>
                    <tr>
                        <th>School</th>
                        <th class="text-right">Roster</th>
                        <th class="text-right">Checked in</th>
                        <th class="text-right">Submitted</th>
                        <th class="text-right">Absent</th>
                        <th class="text-right">Still writing</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in bySchool" :key="row.school_id || row.school_name">
                        <td>{{ row.school_name }}</td>
                        <td class="text-right tabular-nums">{{ row.roster }}</td>
                        <td class="text-right tabular-nums">{{ row.checked_in }}</td>
                        <td class="text-right tabular-nums">{{ row.submitted }}</td>
                        <td class="text-right tabular-nums">{{ Math.max(0, row.roster - row.checked_in) }}</td>
                        <td class="text-right tabular-nums" :class="row.writing ? 'font-semibold text-amber-700' : ''">{{ row.writing }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    summary: { type: Object, default: null },
    registered: { type: Number, default: 0 },
    showSchools: { type: Boolean, default: false },
});

const counts = computed(() => props.summary?.counts ?? {});
const bySchool = computed(() => props.summary?.by_school ?? []);
const unfinished = computed(() => props.summary?.unfinished ?? counts.value.writing ?? 0);
const rosterMismatch = computed(() => props.summary && props.registered !== (counts.value.roster ?? 0));

const items = computed(() => [
    { key: 'registered', label: 'Registered (approved)', value: props.registered },
    { key: 'roster', label: 'On engine roster', value: counts.value.roster, warn: rosterMismatch.value },
    { key: 'checked_in', label: 'Checked in', value: counts.value.checked_in },
    { key: 'submitted', label: 'Submitted', value: counts.value.submitted },
    { key: 'absent', label: 'Absent (never logged in)', value: Math.max(0, (counts.value.roster ?? 0) - (counts.value.checked_in ?? 0)) },
    { key: 'unfinished', label: 'Unfinished', value: unfinished.value, warn: unfinished.value > 0 },
]);
</script>
