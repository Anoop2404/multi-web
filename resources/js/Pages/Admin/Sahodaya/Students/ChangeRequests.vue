<template>
    <SahodayaAdminLayout title="Student change requests" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <PageHeader title="Student change requests" eyebrow="Membership"
                    description="Schools submit edits when student records are locked. Approve to apply changes." />

        <div class="flex flex-wrap gap-2 mb-4">
            <button v-for="s in statusTabs" :key="s.key" type="button"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold border"
                    :class="filterStatus === s.key ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'bg-white border-slate-200'"
                    @click="setFilter(s.key)">
                {{ s.label }} ({{ counts[s.key] ?? 0 }})
            </button>
            <Link :href="`/sahodaya-admin/${sahodaya.id}/membership/settings`" class="btn-secondary text-xs ml-auto">
                Lock settings →
            </Link>
        </div>

        <div class="card card--flush overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Sl No</th>
                        <th class="p-3">School</th>
                        <th class="p-3">Student</th>
                        <th class="p-3">Requested changes</th>
                        <th class="p-3">Reason</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(req, idx) in requests.data" :key="req.id" class="border-t align-top">
                        <td class="p-3">{{ idx + 1 }}</td>
                        <td class="p-3 text-xs">{{ (req.school?.name || '').toUpperCase() || req.school_id }}</td>
                        <td class="p-3">
                            <span v-if="req.change_type === 'create'" class="font-medium text-emerald-800">New student</span>
                            <template v-else>
                                <span class="font-medium">{{ req.student?.name ?? '—' }}</span>
                                <p v-if="req.student?.reg_no" class="text-xs font-mono text-[#0f3d7a]">{{ req.student.reg_no }}</p>
                            </template>
                        </td>
                        <td class="p-3 text-xs">
                            <ul class="space-y-0.5">
                                <li v-for="(val, key) in req.changes_json" :key="key">
                                    <span class="text-slate-500">{{ label(key) }}:</span> {{ val ?? '—' }}
                                </li>
                                <li v-if="req.photo_path" class="text-emerald-700">New photo attached</li>
                            </ul>
                        </td>
                        <td class="p-3 text-xs max-w-xs">{{ req.reason }}</td>
                        <td class="p-3">
                            <span :class="statusClass(req.status)" class="text-xs font-semibold px-2 py-0.5 rounded capitalize">{{ req.status }}</span>
                            <p v-if="req.resolution_note" class="text-xs text-slate-500 mt-1 italic">{{ req.resolution_note }}</p>
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <template v-if="req.status === 'pending'">
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

        <div v-if="requests.links?.length" class="mt-4 flex justify-center gap-1">
            <Link v-for="link in requests.links" :key="link.label" :href="link.url || '#'"
                  class="px-3 py-1 text-xs rounded border"
                  :class="link.active ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]' : 'bg-white border-slate-200'"
                  v-html="link.label" />
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    requests: Object, counts: Object, filterStatus: { type: String, default: 'pending' },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/student-change-requests`;

const statusTabs = [
    { key: 'pending', label: 'Pending' },
    { key: 'approved', label: 'Approved' },
    { key: 'rejected', label: 'Rejected' },
    { key: 'all', label: 'All' },
];

function setFilter(status) {
    router.get(base, { status }, { preserveState: true, preserveScroll: true });
}

function label(key) {
    return String(key).replace(/_/g, ' ');
}

function statusClass(status) {
    return {
        pending: 'bg-amber-100 text-amber-800',
        approved: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-700',
    }[status] ?? 'bg-slate-100 text-slate-600';
}

function approve(req) {
    const note = prompt('Optional note for the school:') ?? '';
    router.post(`${base}/${req.id}/approve`, { resolution_note: note }, { preserveScroll: true });
}

function reject(req) {
    const note = prompt('Reason for rejection (optional):') ?? '';
    router.post(`${base}/${req.id}/reject`, { resolution_note: note }, { preserveScroll: true });
}
</script>
