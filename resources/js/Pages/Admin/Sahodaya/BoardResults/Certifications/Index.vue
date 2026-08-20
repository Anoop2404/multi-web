<template>
    <SahodayaAdminLayout title="School-Certified Result Packages" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="School-Certified Result Packages" eyebrow="Academic Results"
                    description="Packages that have completed Principal Verification — the signed consolidated report submitted by the school.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/verification`" class="btn-secondary text-sm">All Board Results</Link>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/certifications/report`" class="btn-secondary text-sm">Status Report</Link>
            </template>
        </PageHeader>

        <div class="flex flex-wrap gap-2 mb-4">
            <Link v-for="(label, value) in statusOptions" :key="value"
                  :href="statusHref(value)"
                  class="px-3 py-1.5 rounded-lg text-sm font-semibold border"
                  :class="filters.status === value ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'border-slate-200 text-slate-600'">
                {{ label }}
            </Link>
            <Link :href="statusHref('all')"
                  class="px-3 py-1.5 rounded-lg text-sm font-semibold border"
                  :class="filters.status === 'all' ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'border-slate-200 text-slate-600'">
                All
            </Link>
        </div>

        <div class="card !p-0 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-3">School</th>
                        <th class="px-4 py-3">Class / Year</th>
                        <th class="px-4 py-3">Version</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Signed Reports</th>
                        <th class="px-4 py-3">Submitted</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="pkg in packages.data" :key="pkg.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-semibold text-slate-800">{{ schoolNames[pkg.tenant_id] ?? pkg.tenant_id }}</td>
                        <td class="px-4 py-3">Class {{ pkg.class }} · {{ pkg.academic_year }}</td>
                        <td class="px-4 py-3">v{{ pkg.version }}</td>
                        <td class="px-4 py-3">
                            <span class="text-[10px] font-bold uppercase px-2 py-1 rounded-full" :class="statusPillClass(pkg.status)">
                                {{ statusOptions[pkg.status] ?? pkg.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ pkg.signed_count }} of {{ pkg.required_count }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ formatDate(pkg.submitted_at) }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/board-results/certifications/${pkg.id}`" class="text-indigo-600 hover:underline font-semibold">
                                Review →
                            </Link>
                        </td>
                    </tr>
                    <tr v-if="!packages.data.length">
                        <td colspan="7" class="px-4 py-10 text-center text-slate-400">No certified packages match this filter.</td>
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

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    packages: Object,
    schoolNames: Object,
    filters: Object,
    statusOptions: Object,
    selectedClass: { type: Number, default: null },
});

function statusHref(status) {
    return `/sahodaya-admin/${props.sahodaya.id}/board-results/certifications?status=${status}`;
}

function statusPillClass(status) {
    if (['published', 'approved', 'sahodaya_verified'].includes(status)) return 'bg-emerald-100 text-emerald-700';
    if (status === 'submitted_to_sahodaya') return 'bg-indigo-100 text-indigo-700';
    if (status === 'sahodaya_returned') return 'bg-rose-100 text-rose-700';
    return 'bg-amber-100 text-amber-700';
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>
