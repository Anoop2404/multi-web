<template>
    <SahodayaAdminLayout :title="`QR reports · ${program.title}`" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="program.title" eyebrow="QR registration reports"
                    description="QR registrations, teacher creation, pending schools, designation mix">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}`" class="btn-secondary text-sm">Back to program</Link>
                <a :href="`/sahodaya-admin/${sahodaya.id}/training/${program.id}/qr-reports/export`" class="btn-primary text-sm">Export Excel</a>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-4 mb-6">
            <div class="card"><p class="text-xs text-gray-500">QR registrations</p><p class="text-2xl font-semibold">{{ report.qr_registrations }}</p></div>
            <div class="card"><p class="text-xs text-gray-500">Teachers created</p><p class="text-2xl font-semibold">{{ report.teachers_created }}</p></div>
            <div class="card"><p class="text-xs text-gray-500">Pending schools</p><p class="text-2xl font-semibold">{{ report.pending_schools }}</p></div>
            <div class="card"><p class="text-xs text-gray-500">All registrations</p><p class="text-2xl font-semibold">{{ report.total_registrations }}</p></div>
        </div>

        <div class="card mb-6">
            <h3 class="section-title mb-3">Designation-wise (QR)</h3>
            <ul v-if="Object.keys(report.by_designation || {}).length" class="text-sm divide-y">
                <li v-for="(count, label) in report.by_designation" :key="label" class="py-2 flex justify-between">
                    <span>{{ label }}</span>
                    <span class="font-semibold">{{ count }}</span>
                </li>
            </ul>
            <p v-else class="text-sm text-gray-400">No QR registrations yet.</p>
        </div>

        <div class="card mb-6">
            <h3 class="section-title mb-3">Pending schools</h3>
            <table v-if="report.pending_school_rows?.length" class="w-full text-sm">
                <thead class="text-left text-xs text-gray-500">
                    <tr>
                        <th class="pb-2">School</th>
                        <th class="pb-2">Code</th>
                        <th class="pb-2">Contact</th>
                        <th class="pb-2">Status</th>
                        <th class="pb-2 text-right">Resolve</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="row in report.pending_school_rows" :key="row.id">
                        <td class="py-2">{{ row.school_name }}</td>
                        <td class="py-2">{{ row.school_code || '—' }}</td>
                        <td class="py-2">{{ row.contact_name }} · {{ row.contact_email || row.contact_phone || '—' }}</td>
                        <td class="py-2 capitalize">{{ row.status }}</td>
                        <td class="py-2 text-right">
                            <div v-if="row.status === 'pending'" class="flex flex-wrap items-center justify-end gap-2">
                                <select v-model="linkSchoolId[row.id]" class="field !py-1 !text-xs max-w-[180px]">
                                    <option value="">Link to school…</option>
                                    <option v-for="s in schools" :key="s.id" :value="s.id">
                                        {{ s.name }}{{ s.school_prefix ? ` (${s.school_prefix})` : '' }}
                                    </option>
                                </select>
                                <button type="button" class="btn-primary text-xs !min-h-0"
                                        :disabled="!linkSchoolId[row.id] || linkingId === row.id"
                                        @click="linkSchool(row)">
                                    Link
                                </button>
                                <button type="button" class="btn-secondary text-xs !min-h-0 text-red-600"
                                        :disabled="rejectingId === row.id"
                                        @click="rejectSchool(row)">
                                    Reject
                                </button>
                            </div>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-sm text-gray-400">No pending school requests.</p>
        </div>

        <div class="card">
            <h3 class="section-title mb-3">QR registrations</h3>
            <table v-if="report.registrations?.length" class="w-full text-sm">
                <thead class="text-left text-xs text-gray-500">
                    <tr>
                        <th class="pb-2">Teacher</th>
                        <th class="pb-2">Designation</th>
                        <th class="pb-2">School</th>
                        <th class="pb-2">Flags</th>
                        <th class="pb-2">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="row in report.registrations" :key="row.id">
                        <td class="py-2">
                            <div>{{ row.teacher_name }}</div>
                            <div class="text-xs text-gray-400">{{ row.email }} · {{ row.mobile || '—' }}</div>
                        </td>
                        <td class="py-2">{{ row.designation || '—' }}</td>
                        <td class="py-2">
                            <div>{{ row.school || '—' }}</div>
                            <div class="text-xs text-gray-400">{{ row.school_code || '' }} {{ row.membership ? `· ${row.membership}` : '' }}</div>
                        </td>
                        <td class="py-2 text-xs">
                            <span v-if="row.teacher_created" class="mr-1">New teacher</span>
                            <span v-if="row.pending_school">Pending school</span>
                            <span v-if="!row.teacher_created && !row.pending_school">—</span>
                        </td>
                        <td class="py-2 capitalize">{{ row.status }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-sm text-gray-400">No QR registrations yet.</p>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    program: Object,
    report: Object,
    schools: { type: Array, default: () => [] },
});

const linkSchoolId = reactive({});
const linkingId = ref(null);
const rejectingId = ref(null);

function linkSchool(row) {
    const schoolId = linkSchoolId[row.id];
    if (!schoolId) return;
    linkingId.value = row.id;
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/pending-schools/${row.id}/link`,
        { school_id: schoolId },
        {
            preserveScroll: true,
            onFinish: () => { linkingId.value = null; },
        },
    );
}

function rejectSchool(row) {
    const reason = window.prompt(`Reject pending school "${row.school_name}"? Optional reason:`) ?? undefined;
    if (reason === undefined) return;
    rejectingId.value = row.id;
    router.post(
        `/sahodaya-admin/${props.sahodaya.id}/training/${props.program.id}/pending-schools/${row.id}/reject`,
        { reason: reason || null },
        {
            preserveScroll: true,
            onFinish: () => { rejectingId.value = null; },
        },
    );
}
</script>
