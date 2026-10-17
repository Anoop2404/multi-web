<template>
    <div class="card card--flush overflow-hidden">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-4 py-3">
            <h3 class="text-sm font-semibold text-slate-900">By school</h3>
            <button v-if="activeSchoolId" type="button" class="text-xs font-semibold text-slate-500 hover:text-slate-800" @click="$emit('select', '')">
                Show all schools
            </button>
        </div>
        <div class="max-h-[26rem] overflow-auto">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th>School</th>
                        <th class="text-right">Roster</th>
                        <th class="text-right">In</th>
                        <th class="text-right">Writing</th>
                        <th class="text-right">Done</th>
                        <th class="text-right">Offline</th>
                        <th class="text-right">Flagged</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row.school_id || row.school_name"
                        class="cursor-pointer"
                        :class="row.school_id === activeSchoolId ? 'bg-indigo-50' : 'hover:bg-slate-50'"
                        :title="selectable ? 'Show only this school\'s candidates' : ''"
                        @click="selectable && $emit('select', row.school_id === activeSchoolId ? '' : row.school_id)"
                    >
                        <td class="font-medium text-slate-800">{{ row.school_name }}</td>
                        <td class="text-right tabular-nums">{{ row.roster }}</td>
                        <td class="text-right tabular-nums">
                            {{ row.checked_in }}
                            <span v-if="row.roster" class="text-[11px] text-slate-400">({{ percent(row.checked_in, row.roster) }})</span>
                        </td>
                        <td class="text-right tabular-nums text-blue-700">{{ row.writing }}</td>
                        <td class="text-right tabular-nums text-emerald-700">{{ row.submitted }}</td>
                        <td class="text-right tabular-nums" :class="row.offline ? 'font-semibold text-amber-600' : 'text-slate-400'">{{ row.offline }}</td>
                        <td class="text-right tabular-nums" :class="row.flagged ? 'font-semibold text-amber-700' : 'text-slate-400'">{{ row.flagged }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="7" class="py-6 text-center text-sm text-slate-500">No schools on the roster yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<script setup>
defineProps({
    rows: { type: Array, default: () => [] },
    activeSchoolId: { type: String, default: '' },
    selectable: { type: Boolean, default: true },
});

defineEmits(['select']);

function percent(part, whole) {
    return whole ? `${Math.round((part * 100) / whole)}%` : '—';
}
</script>
