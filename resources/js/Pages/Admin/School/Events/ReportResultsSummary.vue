<template>
    <SchoolAdminLayout :title="`Results summary — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Results summary — ${event.title}`" :eyebrow="programLabel"
                    description="Medals and scored results for your school in this event.">
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← All reports</Link>
                <ReportDownloadButtons v-if="pdfUrl" :pdf-url="pdfUrl" />
            </template>
        </PageHeader>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card text-center">
                <p class="text-2xl font-bold text-amber-600">{{ results.gold }}</p>
                <p class="text-xs text-gray-500 mt-1">Gold</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-slate-500">{{ results.silver }}</p>
                <p class="text-xs text-gray-500 mt-1">Silver</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold text-orange-700">{{ results.bronze }}</p>
                <p class="text-xs text-gray-500 mt-1">Bronze</p>
            </div>
            <div class="card text-center">
                <p class="text-2xl font-bold">{{ results.total_score }}</p>
                <p class="text-xs text-gray-500 mt-1">Total score</p>
            </div>
        </div>

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Participant</th>
                        <th>Position</th>
                        <th>Grade</th>
                        <th>Score</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, i) in results.items" :key="i">
                        <td>{{ row.item }}</td>
                        <td class="font-medium">{{ row.participant }}</td>
                        <td>{{ row.position ?? '—' }}</td>
                        <td>{{ row.grade ?? '—' }}</td>
                        <td>{{ row.score ?? '—' }}</td>
                    </tr>
                    <tr v-if="!results.items?.length">
                        <td colspan="5" class="p-6 text-center text-slate-400">No published results yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    results: Object,
    pdfUrl: String,
});
const { programLabel, programBase } = useSchoolProgramContext(props);
</script>
