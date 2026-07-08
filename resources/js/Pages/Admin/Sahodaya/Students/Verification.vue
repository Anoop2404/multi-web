<template>
    <SahodayaAdminLayout title="Student verification" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="All students" eyebrow="Membership"
                    description="Browse every active student across member schools. Filter by school or verification status, then open a profile to review details.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/schools`" class="btn-secondary text-sm">
                    Schools list
                </Link>
                <form @submit.prevent="bulkVerifyAll" class="inline">
                    <button type="submit" class="btn-primary text-sm">Verify all on this page</button>
                </form>
            </template>
        </PageHeader>

        <div class="grid sm:grid-cols-3 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold">{{ counts.total }}</p>
                <p class="text-xs text-slate-500 mt-1">Active students</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold text-emerald-700">{{ counts.verified }}</p>
                <p class="text-xs text-slate-500 mt-1">Verified</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold text-amber-700">{{ counts.unverified }}</p>
                <p class="text-xs text-slate-500 mt-1">Pending verification</p>
            </div>
        </div>

        <form class="card !p-4 mb-4 flex flex-wrap gap-3 items-end" @submit.prevent="applyFilters">
            <FormField label="School" class-extra="mb-0 min-w-[12rem]">
                <select v-model="f.school_id" class="field text-sm">
                    <option value="">All schools</option>
                    <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
            </FormField>
            <FormField label="Status" class-extra="mb-0">
                <select v-model="f.verification" class="field text-sm">
                    <option value="all">All students</option>
                    <option value="unverified">Pending verification</option>
                    <option value="verified">Verified</option>
                </select>
            </FormField>
            <FormField label="Search" class-extra="mb-0">
                <input v-model="f.search" type="search" class="field text-sm" placeholder="Name or reg no">
            </FormField>
            <button type="submit" class="btn-secondary text-sm">Apply</button>
        </form>

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>School</th>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in students.data" :key="row.id">
                        <td class="text-sm">{{ row.school_name }}</td>
                        <td>
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/students/${row.id}`"
                                  class="font-medium text-[#0f3d7a] hover:underline">
                                {{ row.name }}
                            </Link>
                            <p class="text-xs font-mono text-slate-500">{{ row.reg_no }}</p>
                        </td>
                        <td class="text-sm">{{ row.class_name ?? '—' }}</td>
                        <td>
                            <span class="status-pill text-xs" :class="row.is_verified ? 'status-pill--completed' : 'status-pill--open'">
                                {{ row.is_verified ? 'Verified' : 'Pending' }}
                            </span>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/students/${row.id}`"
                                  class="text-xs font-semibold text-[#0f3d7a] hover:underline mr-3">
                                Profile
                            </Link>
                            <button v-if="!row.is_verified" type="button" class="btn-primary text-xs"
                                    @click="verifyOne(row)">Verify</button>
                        </td>
                    </tr>
                    <tr v-if="!students.data?.length">
                        <td colspan="5" class="p-8 text-center text-slate-400">No students match the filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    students: Object,
    counts: Object,
    filters: Object,
    schools: Array,
});

const base = `/sahodaya-admin/${props.sahodaya.id}/students/verification`;
const f = reactive({ ...props.filters });

function applyFilters() {
    router.get(base, { ...f }, { preserveState: true, preserveScroll: true });
}

function verifyOne(row) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/students/${row.id}/verify`, {}, { preserveScroll: true });
}

function bulkVerifyAll() {
    const ids = (props.students.data ?? []).filter((r) => !r.is_verified).map((r) => r.id);
    if (!ids.length) return;
    router.post(`${base}/bulk-verify`, { student_ids: ids }, { preserveScroll: true });
}
</script>
