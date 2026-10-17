<template>
    <section v-if="summary" class="rounded-2xl border border-slate-200 bg-white p-5">
        <p class="mb-4 text-sm text-slate-600">{{ summary.basis }}. Each participant counts once per day and once overall. Group members count individually.</p>
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <label class="text-sm font-semibold">Day / scope
                <select v-model="selected" class="ml-2 rounded-lg border px-3 py-2">
                    <option value="all">All days and overall</option>
                    <option v-for="day in summary.days" :key="day.date" :value="day.date">{{ dateLabel(day.date) }}</option>
                    <option value="event">Whole event, including unscheduled</option>
                    <option value="unscheduled">Awaiting a date</option>
                </select>
            </label>
            <a :href="exportUrl('xlsx')" class="btn-secondary text-sm">Excel report</a>
            <a :href="exportUrl('preview')" target="_blank" rel="noopener" class="btn-secondary text-sm">PDF preview</a>
            <a :href="exportUrl('pdf')" class="btn-secondary text-sm">Download PDF</a>
        </div>
        <p v-if="!summary.days.length" class="mb-3 text-sm text-slate-500">Assign schedule dates to see day-wise counts.</p>
        <p v-for="warning in summary.warnings" :key="warning" class="mb-3 rounded bg-amber-50 p-3 text-sm">{{ warning }}</p>
        <p v-if="summary.event_total.unidentified_participants" class="mb-3 rounded bg-amber-50 p-3 text-sm">{{ summary.event_total.unidentified_participants }} records lack a stable identity; unique counts may overcount those participants.</p>
        <p class="mb-4 text-xs text-slate-500">Unspecified includes missing, unrecognised or conflicting gender records. Overall totals are deduplicated, not the sum of daily counts.</p>
        <div class="overflow-x-auto"><table class="w-full border-collapse text-sm">
            <thead class="bg-slate-100">
                <tr><th rowspan="2" class="border p-3 text-left">School name</th><th v-for="day in columns" :key="day.label" :colspan="genderKeys.length" class="border p-3 text-center">{{ day.label }}</th><th rowspan="2" class="border p-3 text-right">Overall unique</th></tr>
                <tr><template v-for="day in columns" :key="day.label"><th v-for="key in genderKeys" :key="key" class="border p-3 text-right">{{ genderLabels[key] }}</th></template></tr>
            </thead>
            <tbody><tr v-for="school in overall.schools" :key="school.key" class="border-b"><td class="border p-3">{{ school.name }}</td><template v-for="day in columns" :key="day.label"><td v-for="key in genderKeys" :key="key" class="border p-3 text-right">{{ schoolGender(day, school.key, key) }}</td></template><td class="border p-3 text-right font-bold">{{ school.count }}</td></tr>
                <tr v-if="!overall.schools.length"><td :colspan="2 + columns.length * genderKeys.length" class="p-3 text-slate-500">No participants in this selection.</td></tr>
            </tbody>
            <tfoot class="bg-slate-100 font-bold"><tr><td class="border p-3 text-left">All schools</td><template v-for="day in columns" :key="day.label"><td v-for="key in genderKeys" :key="key" class="border p-3 text-right">{{ day.genders[key] }}</td></template><td class="border p-3 text-right">{{ overall.unique_participants }}</td></tr></tfoot>
        </table></div>
    </section>
</template>
<script setup>
import { computed, ref } from 'vue';
import { formatCalendarDate } from '@/support/calendarDates.js';
const props = defineProps({ summary: Object, reportUrl: String });
const selected = ref('all');
const genderLabels = { male: 'Male', female: 'Female', other: 'Other', unspecified: 'Unspecified' };
const dateLabel = (date) => formatCalendarDate(date);
const exportUrl = (format) => props.reportUrl + '?' + new URLSearchParams({ format, scope: selected.value });
const columns = computed(() => {
    const days = props.summary.days.map(day => ({ ...day, label: dateLabel(day.date) }));
    if (selected.value === 'all') return days;
    if (selected.value === 'event') return [{ ...props.summary.event_total, label: 'Whole event — including unscheduled' }];
    if (selected.value === 'unscheduled') return [{ ...props.summary.unscheduled, label: 'Awaiting a date' }];
    return days.filter(day => day.date === selected.value);
});
const overall = computed(() => selected.value === 'all' ? props.summary.overall : columns.value[0]);
const genderKeys = computed(() => ['male', 'female', ...['other', 'unspecified'].filter(key => columns.value.some(day => day.genders[key] > 0))]);
const schoolGender = (day, schoolKey, gender) => day.schools.find(school => school.key === schoolKey)?.genders[gender] ?? 0;
</script>
