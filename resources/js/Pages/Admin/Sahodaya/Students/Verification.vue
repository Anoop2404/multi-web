<template>
    <SahodayaAdminLayout title="Student verification" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="selectedSchool ? selectedSchool.name : 'Student verification'"
                    eyebrow="Membership"
                    :description="selectedSchool
                        ? 'Review and verify students at this school. Use filters to show pending or verified only.'
                        : 'Start with schools — pick a school to review students. Filter by verification status across all member schools.'">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/schools`" class="btn-secondary text-sm">
                    Schools list
                </Link>
                <button v-if="selectedSchool && schoolPendingCount > 0" type="button"
                        class="btn-primary text-sm" @click="bulkVerifySchool">
                    Verify all pending ({{ schoolPendingCount }})
                </button>
                <button v-else-if="selectedSchool && students?.data?.length" type="button"
                        class="btn-primary text-sm" @click="bulkVerifyPage">
                    Verify all on this page
                </button>
            </template>
        </PageHeader>

        <div class="grid sm:grid-cols-3 gap-4 mb-6">
            <button type="button" class="card card--muted !py-4 text-center hover:ring-2 hover:ring-[#0f3d7a]/20 transition"
                    @click="setVerification('all')">
                <p class="text-2xl font-bold">{{ counts.total.toLocaleString('en-IN') }}</p>
                <p class="text-xs text-slate-500 mt-1">Active students</p>
            </button>
            <button type="button" class="card card--muted !py-4 text-center hover:ring-2 hover:ring-emerald-500/20 transition"
                    @click="setVerification('verified')">
                <p class="text-2xl font-bold text-emerald-700">{{ counts.verified.toLocaleString('en-IN') }}</p>
                <p class="text-xs text-slate-500 mt-1">Verified</p>
            </button>
            <button type="button" class="card card--muted !py-4 text-center hover:ring-2 hover:ring-amber-500/20 transition"
                    @click="setVerification('unverified')">
                <p class="text-2xl font-bold text-amber-700">{{ counts.unverified.toLocaleString('en-IN') }}</p>
                <p class="text-xs text-slate-500 mt-1">Pending verification</p>
            </button>
        </div>

        <form class="card !p-4 mb-4 flex flex-wrap gap-3 items-end" @submit.prevent="applyFilters">
            <FormField v-if="selectedSchool" label="School" class-extra="mb-0 min-w-[12rem]">
                <div class="field text-sm flex items-center justify-between gap-2 bg-gray-50">
                    <span class="truncate font-medium text-gray-800">{{ selectedSchool.name }}</span>
                    <button type="button" class="text-xs font-semibold text-[#0f3d7a] hover:underline shrink-0"
                            @click="clearSchool">
                        Change
                    </button>
                </div>
            </FormField>
            <FormField v-else label="School" class-extra="mb-0 min-w-[12rem]">
                <select v-model="f.school_id" class="field text-sm">
                    <option value="">All schools (summary)</option>
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
                <input v-model="f.search" type="search" class="field text-sm"
                       :placeholder="selectedSchool ? 'Name or reg no' : 'School name'">
            </FormField>
            <button type="submit" class="btn-secondary text-sm">Apply</button>
        </form>

        <!-- Schools summary -->
        <div v-if="!selectedSchool" class="card overflow-hidden p-0">
            <div class="px-5 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="font-bold text-gray-900">Schools</h3>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ schoolSummaries.length }} school{{ schoolSummaries.length === 1 ? '' : 's' }}
                        <template v-if="f.verification === 'unverified'">with pending students</template>
                        <template v-else-if="f.verification === 'verified'">fully verified</template>
                        · click a row to open students
                    </p>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>School</th>
                        <th class="text-right">Active</th>
                        <th class="text-right">Verified</th>
                        <th class="text-right">Pending</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in schoolSummaries" :key="row.id" class="hover:bg-gray-50/80">
                        <td>
                            <button type="button" class="font-medium text-[#0f3d7a] hover:underline text-left"
                                    @click="openSchool(row)">
                                {{ row.name }}
                            </button>
                        </td>
                        <td class="text-right text-sm tabular-nums">{{ row.total.toLocaleString('en-IN') }}</td>
                        <td class="text-right text-sm tabular-nums text-emerald-700">{{ row.verified.toLocaleString('en-IN') }}</td>
                        <td class="text-right text-sm tabular-nums" :class="row.unverified ? 'text-amber-700 font-semibold' : 'text-gray-400'">
                            {{ row.unverified.toLocaleString('en-IN') }}
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <button type="button" class="text-xs font-semibold text-[#0f3d7a] hover:underline mr-3"
                                    @click="openSchool(row)">
                                Open
                            </button>
                            <button v-if="row.unverified > 0" type="button"
                                    class="btn-primary text-xs py-1.5 px-2.5"
                                    @click="bulkVerifySchoolRow(row)">
                                Verify all ({{ row.unverified }})
                            </button>
                        </td>
                    </tr>
                    <tr v-if="!schoolSummaries.length">
                        <td colspan="5" class="p-8 text-center text-slate-400">No schools match the filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Students at selected school -->
        <div v-else class="card overflow-hidden p-0">
            <div class="px-5 py-3 border-b border-gray-100 flex flex-wrap items-center gap-2 text-xs text-gray-500">
                <button type="button" class="font-semibold text-[#0f3d7a] hover:underline" @click="clearSchool">
                    ← All schools
                </button>
                <span>/</span>
                <span class="font-medium text-gray-800">{{ selectedSchool.name }}</span>
                <span v-if="students?.total" class="ml-auto">
                    {{ students.total.toLocaleString('en-IN') }} student{{ students.total === 1 ? '' : 's' }}
                </span>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Class</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in students?.data ?? []" :key="row.id">
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
                    <tr v-if="!(students?.data?.length)">
                        <td colspan="4" class="p-8 text-center text-slate-400">No students match the filters.</td>
                    </tr>
                </tbody>
            </table>
            <div v-if="students?.links?.length > 3" class="px-4 py-3 border-t border-gray-100 flex justify-center gap-1">
                <Link v-for="link in students.links" :key="link.label"
                      :href="link.url || '#'"
                      class="px-3 py-1 rounded text-xs font-medium"
                      :class="link.active ? 'bg-[#0f3d7a] text-white' : (link.url ? 'text-[#0f3d7a] hover:bg-gray-100' : 'text-gray-300 pointer-events-none')"
                      v-html="link.label" />
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    students: { type: Object, default: null },
    schoolSummaries: { type: Array, default: () => [] },
    selectedSchool: { type: Object, default: null },
    counts: Object,
    filters: Object,
    schools: Array,
});

const base = `/sahodaya-admin/${props.sahodaya.id}/students/verification`;
const f = reactive({ ...props.filters });

const schoolPendingCount = computed(() => props.selectedSchool?.unverified ?? 0);

function applyFilters() {
    router.get(base, { ...f }, { preserveState: true, preserveScroll: true });
}

function setVerification(value) {
    f.verification = value;
    applyFilters();
}

function openSchool(row) {
    f.school_id = row.id;
    f.search = '';
    applyFilters();
}

function clearSchool() {
    f.school_id = '';
    applyFilters();
}

function verifyOne(row) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/students/${row.id}/verify`, {}, { preserveScroll: true });
}

function bulkVerifyPage() {
    const ids = (props.students?.data ?? []).filter((r) => !r.is_verified).map((r) => r.id);
    if (!ids.length) return;
    router.post(`${base}/bulk-verify`, { student_ids: ids }, { preserveScroll: true });
}

function bulkVerifySchool() {
    if (!props.selectedSchool || !schoolPendingCount.value) return;
    if (!confirm(`Verify all ${schoolPendingCount.value} pending student(s) at ${props.selectedSchool.name}?`)) return;
    router.post(`${base}/bulk-verify`, {
        verify_all_unverified: true,
        school_id: props.selectedSchool.id,
    }, { preserveScroll: true });
}

function bulkVerifySchoolRow(row) {
    if (!row.unverified) return;
    if (!confirm(`Verify all ${row.unverified} pending student(s) at ${row.name}?`)) return;
    router.post(`${base}/bulk-verify`, {
        verify_all_unverified: true,
        school_id: row.id,
    }, { preserveScroll: true });
}
</script>
