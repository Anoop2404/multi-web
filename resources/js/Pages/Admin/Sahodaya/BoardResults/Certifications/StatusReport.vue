<template>
    <SahodayaAdminLayout title="Certification Status Report" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="School Certification Status Report" eyebrow="Academic Results"
                    description="One row per school/class/year — entry completeness, signed-report counts, and submission status.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/certifications`" class="btn-secondary text-sm">← Back to queue</Link>
            </template>
        </PageHeader>

        <div class="card !p-0 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3">School</th>
                        <th class="px-4 py-3">Class / Exam</th>
                        <th class="px-4 py-3">Year</th>
                        <th class="px-4 py-3">Package Status</th>
                        <th class="px-4 py-3">Signed Reports</th>
                        <th class="px-4 py-3">Consolidated Signed</th>
                        <th class="px-4 py-3">Submitted</th>
                        <th class="px-4 py-3">Signer</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="(row, i) in rows" :key="i">
                        <td class="px-4 py-3 font-semibold text-slate-800">{{ row.school }}</td>
                        <td class="px-4 py-3">Class {{ row.class }} ({{ row.examination_type }})</td>
                        <td class="px-4 py-3">{{ row.academic_year }}</td>
                        <td class="px-4 py-3">{{ row.package_status.replace(/_/g, ' ') }}</td>
                        <td class="px-4 py-3">{{ row.signed_reports }} of {{ row.required_reports }}</td>
                        <td class="px-4 py-3">{{ row.consolidated_signed ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ row.submitted_at ? new Date(row.submitted_at).toLocaleDateString() : '—' }}</td>
                        <td class="px-4 py-3">{{ row.signer ?? '—' }}</td>
                    </tr>
                    <tr v-if="!rows.length">
                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">No results found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    rows: { type: Array, default: () => [] },
    academicYear: { type: String, default: null },
});
</script>
