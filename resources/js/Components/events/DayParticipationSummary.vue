<template>
    <section v-if="summary" class="mb-6 rounded-2xl border border-slate-200 bg-white p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-bold text-slate-900">Day-wise participants</h2>
                <p class="mt-1 text-xs text-slate-500">{{ summary.basis }} · Group members count individually. A person counts once per day and once in the overall total.</p>
            </div>
            <div class="flex flex-wrap gap-2 text-xs">
                <a v-if="reportUrl" :href="exportUrl('xlsx')" class="rounded-lg border px-3 py-2 font-semibold">Excel report</a>
                <a v-if="reportUrl" :href="exportUrl('preview')" target="_blank" rel="noopener" class="rounded-lg border px-3 py-2 font-semibold">PDF preview</a>
                <a v-if="reportUrl" :href="exportUrl('pdf')" class="rounded-lg border px-3 py-2 font-semibold">Download PDF</a>
                <button type="button" class="rounded-lg border px-3 py-2 font-semibold" @click="downloadCounts">Download counts CSV</button>
                <button type="button" class="rounded-lg border px-3 py-2 font-semibold" @click="downloadStudents">Download selected student list</button>
            </div>
        </div>
        <p v-for="warning in summary.warnings || []" :key="warning" class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-900">{{ warning }}</p>
        <p v-if="summary.event_total.unidentified_participants" class="mt-3 rounded-lg bg-amber-50 p-3 text-xs text-amber-900">
            {{ summary.event_total.unidentified_participants }} participant record(s) do not have an unambiguous student identity.
            Totals marked * may count the same person more than once. Correct those records to obtain verified unique totals; names alone are not merged.
        </p>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="text-xs text-slate-500"><tr><th class="p-2">Day</th><th class="p-2">Unique participants</th><th class="p-2">Participant entries</th><th class="p-2">Items with participants</th><th class="p-2">Schools</th></tr></thead>
                <tbody>
                    <tr v-for="day in summary.days" :key="day.date" class="border-t border-slate-100">
                        <td class="p-2"><button type="button" class="font-semibold text-indigo-700 underline" @click="selected = day.date">{{ dateLabel(day.date) }}</button></td>
                        <td class="p-2">{{ day.unique_participants }}{{ day.unidentified_participants ? '*' : '' }}</td><td class="p-2">{{ day.participant_entries }}</td><td class="p-2">{{ day.items }}</td><td class="p-2">{{ day.school_count }}</td>
                    </tr>
                    <tr class="border-t bg-slate-50 font-bold">
                        <td class="p-2"><button type="button" class="underline" @click="selected = 'all'">Overall — all scheduled days</button></td>
                        <td class="p-2">{{ summary.overall.unique_participants }}{{ summary.overall.unidentified_participants ? '*' : '' }}</td><td class="p-2">{{ summary.overall.participant_entries }}</td><td class="p-2">{{ summary.overall.items }}</td><td class="p-2">{{ summary.overall.school_count }}</td>
                    </tr>
                    <tr class="border-t font-semibold">
                        <td class="p-2"><button type="button" class="underline" @click="selected = 'event'">Event total — including unscheduled</button></td>
                        <td class="p-2">{{ summary.event_total.unique_participants }}{{ summary.event_total.unidentified_participants ? '*' : '' }}</td><td class="p-2">{{ summary.event_total.participant_entries }}</td><td class="p-2">{{ summary.event_total.items }}</td><td class="p-2">{{ summary.event_total.school_count }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-if="!summary.days.length" class="mt-3 text-xs text-slate-500">Assign schedule dates to see day-wise counts.</p>
        <p class="mt-2 text-xs text-slate-500">{{ summary.unscheduled.unique_participants }} participant(s) have entries awaiting a date. They may also have other entries already scheduled.</p>
        <div class="mt-4 border-t pt-4">
            <label class="text-xs font-semibold">Student names by school
                <select v-model="selected" class="ml-2 rounded-lg border border-slate-300 px-3 py-2 text-xs">
                    <option value="all">All scheduled days — unique overall</option>
                    <option v-for="day in summary.days" :key="day.date" :value="day.date">{{ dateLabel(day.date) }}</option>
                    <option value="event">Whole event — including unscheduled</option>
                    <option value="unscheduled">Entries awaiting a date</option>
                </select>
            </label>
            <p class="mt-2 text-xs text-slate-500">{{ scope.unique_participants }} participant(s) · {{ scope.school_count }} school(s). Open a school to view names.</p>
            <details v-for="school in scope.schools" :key="selected + school.key" class="mt-2 rounded-lg border border-slate-200 p-3">
                <summary class="cursor-pointer text-sm font-semibold">{{ school.name }} — {{ school.count }} participant(s)</summary>
                <ol class="mt-2 list-inside list-decimal space-y-1 text-xs text-slate-700">
                    <li v-for="student in school.students" :key="student.key">{{ student.name }}<span v-if="student.identifier" class="text-slate-500"> · {{ student.identifier }}</span><span v-if="student.class_name" class="text-slate-500"> · {{ student.class_name }}</span><span v-if="!student.identified" class="text-amber-700"> · identity unverified</span></li>
                </ol>
            </details>
        </div>
    </section>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { formatCalendarDate } from '@/support/calendarDates.js';
const props = defineProps({ summary: Object, reportUrl: String });
const selected = ref('all');
const exportUrl = (format) => props.reportUrl + '?' + new URLSearchParams({ format, scope: selected.value }).toString();
watch(() => props.summary, (summary) => {
    if (!['all', 'event', 'unscheduled'].includes(selected.value) && !summary?.days.some((day) => day.date === selected.value)) selected.value = 'all';
});
const scope = computed(() => selected.value === 'event' ? props.summary.event_total : selected.value === 'unscheduled' ? props.summary.unscheduled
    : props.summary.days.find((day) => day.date === selected.value) ?? props.summary.overall);
const dateLabel = (date) => formatCalendarDate(date);
function csvDownload(name, rows) {
    const cell = (value) => {
        let text = String(value ?? '');
        if (/^[\s]*[=+@\-\t\r]/.test(text)) text = "'" + text;
        return '"' + text.replaceAll('"', '""') + '"';
    };
    const url = URL.createObjectURL(new Blob(['\uFEFF' + rows.map((row) => row.map(cell).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8;' }));
    const anchor = document.createElement('a');
    anchor.href = url; anchor.download = name; anchor.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}
function downloadCounts() {
    const rows = [['Day', 'Unique participants', 'Participant entries', 'Items', 'Schools', 'Records without stable identity']];
    for (const day of props.summary.days) rows.push([day.date, day.unique_participants, day.participant_entries, day.items, day.school_count, day.unidentified_participants]);
    const total = props.summary.overall;
    rows.push(['Overall (deduplicated)', total.unique_participants, total.participant_entries, total.items, total.school_count, total.unidentified_participants]);
    const event = props.summary.event_total;
    rows.push(['Event total (including unscheduled)', event.unique_participants, event.participant_entries, event.items, event.school_count, event.unidentified_participants]);
    csvDownload('day-wise-participant-counts.csv', rows);
}
function downloadStudents() {
    const rows = [['Day / scope', 'School', 'Student / participant', 'Class', 'Stable ID available']];
    for (const school of scope.value.schools) for (const student of school.students)
        rows.push([selected.value, school.name, student.name, student.class_name, student.identified ? 'Yes' : 'No']);
    csvDownload('school-wise-participants-' + selected.value + '.csv', rows);
}
</script>
