<template>
    <SahodayaEventsLayout :title="`${event.title} — Student report by school`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Student report by school`" eyebrow="Reports"
                    description="Every participating school's students and their registered items. Download one school on its own, or all schools in one PDF with each school starting on a new page.">
            <template #actions>
                <a :href="`${base}/reports/student-wise`" class="btn-secondary text-sm">← Student-wise browser</a>
            </template>
        </PageHeader>

        <div class="card mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h4 class="section-title">All schools — one PDF</h4>
                <p class="section-desc">{{ schools.length }} school(s), {{ studentTotal }} student(s). Each school starts on its own page.</p>
            </div>
            <ReportDownloadButtons :pdf-url="bulkPdfUrl" />
        </div>

        <div class="card">
            <input v-model="search" type="search" class="field text-sm w-full mb-3" placeholder="Search school…">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="py-2 pr-3 w-10">Sl</th>
                            <th class="py-2 pr-3">School</th>
                            <th class="py-2 pr-3 w-24">Code</th>
                            <th class="py-2 pr-3 w-24 text-right">Students</th>
                            <th class="py-2 pl-3 text-right">PDF</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(s, i) in filtered" :key="s.school_id">
                            <td class="py-2 pr-3 text-slate-500">{{ i + 1 }}</td>
                            <td class="py-2 pr-3 font-semibold text-slate-800">{{ s.school_name }}</td>
                            <td class="py-2 pr-3 text-slate-500">{{ s.school_code }}</td>
                            <td class="py-2 pr-3 text-right">{{ s.student_count }}</td>
                            <td class="py-2 pl-3 text-right">
                                <ReportDownloadButtons :pdf-url="s.pdf_url" class="justify-end" />
                            </td>
                        </tr>
                        <tr v-if="filtered.length === 0">
                            <td colspan="5" class="py-6 text-center text-slate-400">No schools found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import ReportDownloadButtons from '@/Components/reports/ReportDownloadButtons.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    schools: { type: Array, default: () => [] },
    bulkPdfUrl: String,
    studentTotal: { type: Number, default: 0 },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const search = ref('');

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase();

    return q ? props.schools.filter((s) => s.school_name?.toLowerCase().includes(q)) : props.schools;
});
</script>
