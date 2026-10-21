<template>
    <section class="card mb-6" aria-labelledby="exam-numbering-title">
        <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
                <h3 id="exam-numbering-title" class="section-title">Class-wise exam registration numbers</h3>
                <p class="section-desc">One sequence per class across all schools. These numbers appear on hall tickets, attendance and reports.</p>
            </div>
            <button type="button" class="btn-secondary text-sm" :disabled="form.processing" @click="loadPreset">Load LKG 10,000 / UKG 11,000 preset</button>
        </div>
        <p class="text-sm text-slate-600 mb-4">Overlapping ranges between classes are allowed. Use class + registration number to identify a candidate. School admission numbers stay unchanged.</p>
        <div v-if="form.ranges.length" class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-slate-500"><th class="p-2">Class</th><th class="p-2">Candidates</th><th class="p-2">Starting number</th><th class="p-2">Ending number</th></tr></thead>
                <tbody>
                    <tr v-for="(row, index) in form.ranges" :key="row.class" class="border-t border-slate-100">
                        <td class="p-2 font-semibold">{{ row.class }}</td><td class="p-2">{{ row.count.toLocaleString() }}</td>
                        <td class="p-2"><input v-model.number="row.start" type="number" min="1" max="999999999" required class="field max-w-44" :aria-label="`Starting registration number for ${row.class}`"><p v-if="form.errors[`ranges.${index}.start`]" class="text-xs text-rose-600">{{ form.errors[`ranges.${index}.start`] }}</p></td>
                        <td class="p-2 font-mono">{{ row.start > 0 ? (Number(row.start) + row.count - 1).toLocaleString() : '—' }}</td>
                    </tr>
                </tbody>
                <tfoot><tr class="border-t"><td class="p-2 font-semibold">Total</td><td class="p-2 font-semibold">{{ total.toLocaleString() }}</td><td colspan="2" /></tr></tfoot>
            </table>
        </div>
        <p v-else class="text-sm text-slate-500">No active registrations yet.</p>
        <p v-for="(message, key) in otherErrors" :key="key" class="text-sm text-rose-600 mt-2">{{ message }}</p>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500">Applies to all active candidates, including pending payments. Sorted by school, student name, then record ID. Previous numbers are backed up; payment and approval status stay unchanged.</p>
            <button type="button" class="btn-primary shrink-0" :disabled="form.processing || !form.ranges.length || invalid" @click="apply">{{ form.processing ? 'Applying…' : 'Reset & apply registration numbers' }}</button>
        </div>
    </section>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useConfirm } from '@/composables/useConfirm';
const props = defineProps({ sahodayaId: String, examId: [String, Number], ranges: { type: Array, default: () => [] } });
const form = useForm({ ranges: props.ranges.map(row => ({ ...row })) });
watch(() => props.ranges, rows => { form.ranges = rows.map(row => ({ ...row })); });
const total = computed(() => form.ranges.reduce((sum, row) => sum + row.count, 0));
const invalid = computed(() => form.ranges.some(row => !Number.isInteger(row.start) || row.start < 1 || row.start > 999999999));
const otherErrors = computed(() => Object.fromEntries(Object.entries(form.errors).filter(([key]) => !key.endsWith('.start'))));
const { confirm } = useConfirm();
function loadPreset() { form.ranges.forEach(row => { if (row.preset !== null) row.start = row.preset; }); }
async function apply() {
    if (invalid.value || form.processing) return;
    if (!(await confirm({ title: 'Reset exam registration numbers?', message: `Replace exam registration numbers for all ${total.value.toLocaleString()} active candidates using the ranges shown? Previously printed hall tickets and attendance sheets will need reprinting. Class + registration number identifies a candidate.`, confirmLabel: 'Reset & apply', destructive: true }))) return;
    form.post(`/sahodaya-admin/${props.sahodayaId}/mcq-exams/${props.examId}/registration-numbers/reset`, { preserveScroll: true });
}
</script>
