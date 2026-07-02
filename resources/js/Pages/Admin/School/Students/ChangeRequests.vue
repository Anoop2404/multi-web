<template>
    <SchoolAdminLayout title="Change requests" :school="school" :show-header-title="false">
        <PageHeader title="Student change requests" eyebrow="Records"
                    description="Track edits submitted while student records are locked.">
            <template #actions>
                <Link :href="`/school-admin/${school.id}/students`" class="btn-secondary text-sm">← Students</Link>
            </template>
        </PageHeader>

        <div v-if="studentEditLock?.locked" class="notice-banner notice-banner--warning mb-4 text-sm">
            {{ studentEditLock.message }}
        </div>

        <div class="card card--flush overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Student</th>
                        <th class="p-3">Changes</th>
                        <th class="p-3">Reason</th>
                        <th class="p-3">Status</th>
                        <th class="p-3">Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="req in requests.data" :key="req.id" class="border-t align-top">
                        <td class="p-3 font-medium">
                            {{ req.change_type === 'create' ? 'New student' : (req.student?.name ?? '—') }}
                        </td>
                        <td class="p-3 text-xs">
                            <span v-for="(val, key) in req.changes_json" :key="key" class="block">
                                {{ key }}: {{ val }}
                            </span>
                        </td>
                        <td class="p-3 text-xs">{{ req.reason }}</td>
                        <td class="p-3 capitalize">{{ req.status }}</td>
                        <td class="p-3 text-xs text-slate-500">{{ formatDate(req.created_at) }}</td>
                    </tr>
                    <tr v-if="!requests.data?.length">
                        <td colspan="5" class="p-8 text-center text-gray-400">No change requests yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

defineProps({
    school: Object,
    requests: Object,
    studentEditLock: Object,
});

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleString();
}
</script>
