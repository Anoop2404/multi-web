<template>
    <SahodayaAdminLayout title="Fest appeals queue" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :approvedSchoolsCount="approvedSchoolsCount"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <PageHeader
            title="Fest appeals queue"
            eyebrow="Fest operations"
            description="All participant appeals across events — review without opening each event separately."
        />

        <div class="flex flex-wrap gap-2 mb-4">
            <button v-for="tab in statusTabs" :key="tab.value" type="button"
                    class="px-3 py-1.5 rounded-lg text-sm font-semibold border transition"
                    :class="filterForm.status === tab.value
                        ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]'
                        : 'bg-white text-slate-600 border-slate-200 hover:border-[#0f3d7a]/30'"
                    @click="setStatus(tab.value)">
                {{ tab.label }}
                <span class="ml-1 opacity-80">({{ statusCounts[tab.value] ?? 0 }})</span>
            </button>
        </div>

        <div class="filter-bar mb-4">
            <input v-model="filterForm.search" type="search" placeholder="Search participant, school, reason…"
                   class="field max-w-sm">
        </div>

        <div class="card card--flush overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                    <tr>
                        <th class="p-3">Event</th>
                        <th class="p-3">Participant</th>
                        <th class="p-3">School / Item</th>
                        <th class="p-3">Reason</th>
                        <th class="p-3">Status</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in appeals.data" :key="a.id" class="border-t align-top">
                        <td class="p-3">
                            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${a.event_id}/appeals`"
                                  class="font-semibold text-[#0f3d7a] hover:underline text-xs">
                                {{ a.event?.title ?? 'Event' }}
                            </Link>
                        </td>
                        <td class="p-3 font-medium">
                            {{ participantName(a) }}
                            <p v-if="a.participant?.student?.reg_no" class="text-xs font-mono text-slate-500">{{ a.participant.student.reg_no }}</p>
                        </td>
                        <td class="p-3 text-xs">
                            <p>{{ a.participant?.registration?.school?.name ?? '—' }}</p>
                            <p class="text-slate-500">{{ a.participant?.registration?.item?.title }}</p>
                        </td>
                        <td class="p-3 text-xs max-w-xs">{{ a.reason }}</td>
                        <td class="p-3">
                            <span :class="statusClass(a.status)" class="text-xs font-semibold px-2 py-0.5 rounded capitalize">{{ a.status }}</span>
                        </td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <template v-if="a.status === 'pending'">
                                <button type="button" class="text-green-700 text-xs font-semibold mr-2"
                                        @click="resolve(a.id, 'approved')">Approve</button>
                                <button type="button" class="text-red-600 text-xs font-semibold mr-2"
                                        @click="resolve(a.id, 'rejected')">Reject</button>
                                <button v-if="a.fee_amount != null && Number(a.fee_amount) > 0 && !a.fee_paid_at"
                                        type="button" class="text-[#0f3d7a] text-xs font-semibold"
                                        @click="markFeePaid(a.id)">Mark fee paid</button>
                            </template>
                        </td>
                    </tr>
                    <tr v-if="!appeals.data?.length">
                        <td colspan="6" class="p-8 text-center text-gray-400">No appeals in this queue.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="appeals.links?.length > 3" class="mt-4 flex justify-center gap-1">
            <Link v-for="link in appeals.links" :key="link.label"
                  :href="link.url || '#'"
                  class="px-3 py-1 rounded text-sm"
                  :class="link.active ? 'bg-[#0f3d7a] text-white' : 'text-gray-600 hover:bg-gray-100'"
                  v-html="link.label" />
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useDebouncedInertiaFilters } from '@/composables/useDebouncedInertiaFilters.js';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    approvedSchoolsCount: Number,
    pendingSchoolsCount: Number,
    pendingSubmissionsCount: Number,
    pendingPaymentsCount: Number,
    appeals: Object,
    filters: Object,
    statusCounts: Object,
});

const filterForm = reactive({
    status: props.filters?.status ?? 'pending',
    search: props.filters?.search ?? '',
});

const statusTabs = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
];

function applyFilters() {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/fest/appeals`, {
        status: filterForm.status,
        search: filterForm.search,
    }, { preserveState: true, replace: true });
}

useDebouncedInertiaFilters(filterForm, applyFilters, () => props.filters);

function setStatus(status) {
    filterForm.status = status;
    applyFilters();
}

function participantName(appeal) {
    return appeal.participant?.student?.name ?? 'Participant';
}

function statusClass(status) {
    return {
        pending: 'bg-amber-100 text-amber-800',
        approved: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-red-100 text-red-700',
    }[status] ?? 'bg-slate-100 text-slate-600';
}

function resolve(appealId, status) {
    const note = status === 'rejected' ? prompt('Rejection note (optional):') : null;
    if (status === 'rejected' && note === null) return;

    router.post(`/sahodaya-admin/${props.sahodaya.id}/fest/appeals/${appealId}/resolve`, {
        status,
        resolution_note: note || null,
    }, { preserveScroll: true });
}

function markFeePaid(appealId) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/fest/appeals/${appealId}/mark-fee-paid`, {}, { preserveScroll: true });
}
</script>
