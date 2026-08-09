<template>
    <SchoolAdminLayout :title="`Student-wise — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`Student-wise — ${event.title}`"
            :eyebrow="programLabel"
            description="Per-student registrations and scores for this event."
        >
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← Reports</Link>
                <ReportDownloadButtons :pdf-url="pdfUrl" :csv-url="csvUrl" />
            </template>
        </PageHeader>

        <div class="mt-4 space-y-3">
            <div v-for="row in rows" :key="row.student.id" class="card text-base p-4">
                <div class="flex items-center justify-between font-bold text-slate-900 text-base">
                    <span>{{ studentDisplayName(row.student) }}</span>
                    <span v-if="row.student.admission_number" class="text-xs font-semibold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">Adm #{{ row.student.admission_number }}</span>
                </div>
                <div class="mt-2 text-sm text-slate-700 font-medium">
                    <span class="text-slate-500 font-semibold">Registered Items:</span> {{ row.registrations.join(', ') || '—' }}
                </div>
                <div v-if="row.results.length" class="text-sm mt-1 text-slate-700">
                    <span class="text-slate-500 font-semibold">Results:</span>
                    <span v-for="(r, i) in row.results" :key="i" class="ml-1 font-semibold text-indigo-700">{{ r.item }} (#{{ r.position }})</span>
                </div>
                <div class="text-sm font-semibold text-indigo-900 mt-1.5">Total score: {{ row.total_score }}</div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';
import { studentDisplayName } from '@/support/studentDisplay.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    rows: Array,
    pdfUrl: String,
    csvUrl: String,
});
const { programLabel, programBase } = useSchoolProgramContext(props);
</script>
