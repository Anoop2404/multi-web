<template>
    <SahodayaAdminLayout title="Region Assignment Report" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :approvedSchoolsCount="approvedSchoolsCount"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="space-y-6">
            <PageHeader
                title="Region Assignment Report"
                eyebrow="Membership · Regions"
                :description="`Which schools are assigned to a region, and which aren't. Not tied to any event — academic year ${academicYear}.`"
            >
                <template #actions>
                    <a :href="`${base}/report/export`" class="btn-secondary text-sm">Export CSV</a>
                    <Link :href="`${base}`" class="btn-ghost text-sm">Manage regions</Link>
                </template>
            </PageHeader>

            <div class="grid gap-3 sm:grid-cols-4">
                <div class="card text-center">
                    <p class="text-2xl font-bold text-[#0f3d7a]">{{ totals.schools }}</p>
                    <p class="text-xs text-slate-500">Total schools</p>
                </div>
                <div class="card text-center">
                    <p class="text-2xl font-bold text-[#0f3d7a]">{{ totals.regions }}</p>
                    <p class="text-xs text-slate-500">Regions</p>
                </div>
                <div class="card text-center">
                    <p class="text-2xl font-bold text-green-700">{{ totals.assigned }}</p>
                    <p class="text-xs text-slate-500">Assigned</p>
                </div>
                <div class="card text-center">
                    <p class="text-2xl font-bold" :class="totals.unassigned > 0 ? 'text-amber-600' : 'text-green-700'">{{ totals.unassigned }}</p>
                    <p class="text-xs text-slate-500">Unassigned</p>
                </div>
            </div>

            <!-- Regions with their schools -->
            <div>
                <h3 class="text-sm font-semibold text-slate-700 mb-2">Schools by region</h3>
                <div v-if="regions.length === 0" class="card text-sm text-slate-500">
                    No regions have been created yet.
                </div>
                <div v-else class="space-y-3">
                    <div v-for="region in regions" :key="region.id" class="rounded-xl border border-gray-200 overflow-hidden">
                        <div class="bg-gray-50 px-3 py-2 flex items-center justify-between">
                            <div>
                                <span class="font-semibold text-[#0f3d7a]">{{ region.name }}</span>
                                <span class="ml-2 font-mono text-xs text-slate-500">{{ region.code }}</span>
                                <span v-if="!region.is_active" class="ml-2 text-xs text-gray-400">(inactive)</span>
                            </div>
                            <span class="text-xs text-slate-500">{{ region.count }} school(s)</span>
                        </div>
                        <div v-if="region.schools.length === 0" class="p-3 text-sm text-slate-400">
                            No schools assigned to this region.
                        </div>
                        <table v-else class="w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="(school, idx) in region.schools" :key="school.id" class="bg-white">
                                    <td class="p-2 pl-3 text-xs text-slate-400 w-10">{{ idx + 1 }}</td>
                                    <td class="p-2 font-medium text-slate-700">{{ (school.name || '').toUpperCase() }}</td>
                                    <td class="p-2 font-mono text-xs text-slate-500 text-right pr-3">{{ school.school_prefix || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Unassigned schools -->
            <div>
                <h3 class="text-sm font-semibold text-slate-700 mb-2">
                    Schools with no region assigned ({{ unassigned.length }})
                </h3>
                <div v-if="unassigned.length === 0" class="card text-sm text-green-700">
                    Every approved school has a region assigned.
                </div>
                <div v-else class="rounded-xl border border-amber-200 overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-amber-50 text-left text-xs uppercase text-amber-700">
                            <tr>
                                <th class="p-3 w-10">Sl No</th>
                                <th class="p-3">School</th>
                                <th class="p-3">Code</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="(school, idx) in unassigned" :key="school.id" class="bg-white">
                                <td class="p-3 text-xs text-slate-400">{{ idx + 1 }}</td>
                                <td class="p-3 font-medium text-slate-700">{{ (school.name || '').toUpperCase() }}</td>
                                <td class="p-3 font-mono text-xs text-slate-500">{{ school.school_prefix || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    approvedSchoolsCount: Number,
    pendingSchoolsCount: Number,
    pendingSubmissionsCount: Number,
    pendingPaymentsCount: Number,
    regions: { type: Array, default: () => [] },
    unassigned: { type: Array, default: () => [] },
    totals: { type: Object, default: () => ({ schools: 0, regions: 0, assigned: 0, unassigned: 0 }) },
    academicYear: String,
});

const base = `/sahodaya-admin/${props.sahodaya.id}/regions`;
</script>
