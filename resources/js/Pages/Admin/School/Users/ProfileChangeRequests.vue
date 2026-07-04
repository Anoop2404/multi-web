<template>
    <SchoolAdminLayout title="Profile change requests" :school="school" :show-header-title="false">
        <PageHeader title="Profile change requests" eyebrow="Review"
                    description="Review and approve or reject profile update requests submitted by teachers and staff.">
            <template #actions>
                <Link :href="`/school-admin/${school.id}/portal-users`" class="btn-secondary text-sm">← Portal users</Link>
            </template>
        </PageHeader>

        <div class="flex flex-wrap gap-2 mb-4">
            <Link v-for="chip in statusFilters" :key="chip.value || 'all'"
                  :href="chip.href"
                  class="text-xs font-semibold px-3 py-1.5 rounded-full border transition"
                  :class="chip.active ? 'bg-[#041525] text-white border-[#041525]' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                {{ chip.label }}
            </Link>
        </div>

        <div class="card card--flush overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">User</th>
                        <th class="p-3">Requested changes</th>
                        <th class="p-3">Reason</th>
                        <th class="p-3">Submitted</th>
                        <th class="p-3 w-48"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="req in requests.data" :key="req.id" class="border-t align-top">
                        <td class="p-3">
                            <p class="font-medium">{{ req.user?.name ?? '—' }}</p>
                            <p class="text-xs text-gray-500">{{ req.user?.email }}</p>
                        </td>
                        <td class="p-3 text-xs space-y-0.5">
                            <div v-for="(val, key) in req.changes_json" :key="key">
                                <span class="text-gray-500 capitalize">{{ key }}:</span>
                                <span class="font-medium ml-1">{{ val }}</span>
                            </div>
                        </td>
                        <td class="p-3 text-xs text-gray-600">{{ req.reason || '—' }}</td>
                        <td class="p-3 text-xs text-slate-500">{{ formatDate(req.created_at) }}</td>
                        <td class="p-3">
                            <div v-if="req.status === 'pending_school'" class="flex gap-2">
                                <button type="button" class="btn-primary text-xs"
                                        @click="approve(req)">
                                    Approve
                                </button>
                                <button type="button" class="btn-secondary text-xs"
                                        @click="reject(req)">
                                    Reject
                                </button>
                            </div>
                            <span v-else class="text-xs px-2 py-0.5 rounded font-medium"
                                  :class="{
                                      'bg-emerald-50 text-emerald-700': req.status === 'approved',
                                      'bg-red-50 text-red-700': req.status === 'school_rejected' || req.status === 'rejected',
                                      'bg-amber-50 text-amber-700': req.status === 'sahodaya_pending',
                                  }">
                                {{ statusLabel(req.status) }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="!requests.data?.length">
                        <td colspan="5" class="p-8 text-center text-gray-400">No profile change requests.</td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>

        <div v-if="pageCount > 1" class="flex justify-center mt-4 gap-2">
            <Link v-for="page in pageCount" :key="page"
                  :href="pageHref(page)"
                  :class="['px-3 py-1.5 rounded text-sm border', page === requests.current_page ? 'bg-[#041525] text-white border-[#041525]' : 'border-gray-200 hover:bg-gray-50']">
                {{ page }}
            </Link>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { computed } from 'vue';

const props = defineProps({
    school:   Object,
    requests: Object,
    filters:  { type: Object, default: () => ({}) },
});

const pageCount = computed(() => Number(props.requests?.last_page ?? 0));

const statusFilters = computed(() => {
    const base = `/school-admin/${props.school.id}/users/profile-change-requests`;
    const current = props.filters?.status ?? null;
    const options = [
        { value: null, label: 'All' },
        { value: 'pending_school', label: 'Pending' },
        { value: 'approved', label: 'Approved' },
        { value: 'school_rejected', label: 'Rejected' },
    ];

    return options.map((opt) => ({
        ...opt,
        href: opt.value ? `${base}?status=${opt.value}` : base,
        active: (current ?? null) === opt.value,
    }));
});

function pageHref(page) {
    const params = new URLSearchParams();
    params.set('page', String(page));
    if (props.filters?.status) {
        params.set('status', props.filters.status);
    }
    return `${props.requests.path}?${params.toString()}`;
}

function formatDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' });
}

function statusLabel(status) {
    const map = {
        approved:         'Approved',
        school_rejected:  'Rejected',
        rejected:         'Rejected',
        sahodaya_pending: 'Sent to Sahodaya',
        pending_school:   'Pending',
    };
    return map[status] ?? status;
}

function approve(req) {
    if (!confirm('Approve this profile change request?')) return;
    router.post(`/school-admin/${props.school.id}/users/profile-change-requests/${req.id}/approve`, {}, { preserveScroll: true });
}

function reject(req) {
    const note = prompt('Reason for rejection (optional):');
    if (note === null) return; // cancelled
    router.post(`/school-admin/${props.school.id}/users/profile-change-requests/${req.id}/reject`, { note }, { preserveScroll: true });
}
</script>
