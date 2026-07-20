<template>
    <SahodayaAdminLayout title="Profile change requests" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <PageHeader title="Profile change requests" eyebrow="Users"
                    description="Teacher and staff profile edits escalated from schools during lock periods." />

        <div class="flex flex-wrap gap-2 mb-4">
            <button v-for="s in statusTabs" :key="s.key" type="button"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold border"
                    :class="filterStatus === s.key ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'bg-white border-slate-200'"
                    @click="setFilter(s.key)">
                {{ s.label }} ({{ counts[s.key] ?? 0 }})
            </button>
        </div>

        <div class="card card--flush overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm min-w-[640px]">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3">Sl No</th>
                            <th class="p-3">School</th>
                            <th class="p-3">User</th>
                            <th class="p-3">Changes</th>
                            <th class="p-3">Reason</th>
                            <th class="p-3">Status</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(req, idx) in requests.data" :key="req.id" class="border-t align-top">
                            <td class="p-3">{{ idx + 1 }}</td>
                            <td class="p-3 text-xs">{{ (req.school?.name || '').toUpperCase() || '—' }}</td>
                            <td class="p-3">
                                <p class="font-medium">{{ req.user?.name ?? '—' }}</p>
                                <p class="text-xs text-slate-500">{{ req.user?.email }}</p>
                            </td>
                            <td class="p-3 text-xs">
                                <ul class="space-y-0.5">
                                    <li v-for="(val, key) in req.changes_json" :key="key">
                                        <span class="text-slate-500 capitalize">{{ key }}:</span> {{ val ?? '—' }}
                                    </li>
                                </ul>
                            </td>
                            <td class="p-3 text-xs max-w-xs">{{ req.reason ?? '—' }}</td>
                            <td class="p-3">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded capitalize">{{ req.status.replace('_', ' ') }}</span>
                            </td>
                            <td class="p-3 text-right whitespace-nowrap">
                                <template v-if="req.status === 'sahodaya_pending'">
                                    <button type="button" class="text-emerald-700 text-xs font-semibold mr-2" @click="approve(req)">Approve</button>
                                    <button type="button" class="text-red-600 text-xs font-semibold" @click="reject(req)">Reject</button>
                                </template>
                            </td>
                        </tr>
                        <tr v-if="!requests.data?.length">
                            <td colspan="7" class="p-8 text-center text-gray-400">No requests</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    requests: Object,
    counts: Object,
    filterStatus: String,
});

const statusTabs = [
    { key: 'pending', label: 'Pending' },
    { key: 'approved', label: 'Approved' },
    { key: 'rejected', label: 'Rejected' },
];

function setFilter(status) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/users/profile-change-requests`, { status }, { preserveState: true });
}

function approve(req) {
    if (!confirm('Approve and apply this profile change?')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/users/profile-change-requests/${req.id}/approve`, {}, { preserveScroll: true });
}

function reject(req) {
    const note = prompt('Reason for rejection (optional):');
    if (note === null) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/users/profile-change-requests/${req.id}/reject`, { resolution_note: note }, { preserveScroll: true });
}
</script>
