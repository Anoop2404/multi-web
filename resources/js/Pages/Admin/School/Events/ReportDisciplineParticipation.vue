<template>
    <SchoolAdminLayout :title="`Discipline participation — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Discipline participation — ${event.title}`" :eyebrow="programLabel"
                    description="Your school's registrations grouped by sport discipline.">
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← Reports</Link>
                <ReportDownloadButtons :pdf-url="pdfUrl" :xls-url="xlsUrl" />
            </template>
        </PageHeader>

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Discipline</th>
                        <th>Items</th>
                        <th>Approved</th>
                        <th>Pending</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows" :key="row.discipline">
                        <td class="font-medium">{{ row.discipline_label }}</td>
                        <td>{{ row.item_count }}</td>
                        <td>{{ row.approved }}</td>
                        <td>{{ row.pending }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="4" class="p-6 text-center text-slate-400">No discipline data yet.</td>
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
    rows: Array,
    pdfUrl: String,
    xlsUrl: String,
});
const { programLabel, programBase } = useSchoolProgramContext(props);
</script>
