<template>
    <SchoolAdminLayout title="Pending change requests" :school="school" :show-header-title="false">
        <PageHeader title="Pending change requests" eyebrow="Review"
                    description="Approve or reject student change requests before they go to Sahodaya.">
            <template #actions>
                <Link :href="`/school-admin/${school.id}/students`" class="btn-secondary text-sm">← Students</Link>
            </template>
        </PageHeader>

        <div class="card card--flush overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Student</th>
                        <th class="p-3">Type</th>
                        <th class="p-3">Reason</th>
                        <th class="p-3">Submitted</th>
                        <th class="p-3 w-40"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="req in requests.data" :key="req.id" class="border-t align-top">
                        <td class="p-3 font-medium">
                            {{ req.change_type === 'create' ? 'New student' : (req.student?.name ?? '—') }}
                        </td>
                        <td class="p-3 capitalize">{{ req.change_type }}</td>
                        <td class="p-3 text-xs">{{ req.reason }}</td>
                        <td class="p-3 text-xs text-slate-500">{{ formatDate(req.created_at) }}</td>
                        <td class="p-3 space-x-2">
                            <button type="button" class="btn-primary text-xs" @click="approve(req)">Approve</button>
                            <button type="button" class="btn-secondary text-xs" @click="reject(req)">Reject</button>
                        </td>
                    </tr>
                    <tr v-if="!requests.data?.length">
                        <td colspan="5" class="p-8 text-center text-gray-400">No pending requests.</td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({
    school: Object,
    requests: Object,
    studentEditLock: Object,
});

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}

function approve(req) {
    router.post(`/school-admin/${props.school.id}/students/pending-change-requests/${req.id}/approve`);
}

function reject(req) {
    const note = window.prompt('Rejection note (optional)') ?? '';
    router.post(`/school-admin/${props.school.id}/students/pending-change-requests/${req.id}/reject`, {
        resolution_note: note,
    });
}
</script>
