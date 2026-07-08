<template>
    <PortalLayout
        role-label="Registration desk"
        :title="event.title"
        :subtitle="sahodaya.name"
        accent="emerald"
        :nav-items="navItems"
    >
        <ReportHeadSubNav v-if="hasItemHeads && event.event_type === 'sports'"
                          :head-item-groups="headItemGroups"
                          :base-url="`${base}/registrations`"
                          :selected-head-id="selectedHeadId"
                          :selected-item-id="selectedItemId"
                          :show-item-links="true" />

        <p v-if="feeRequired" class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
            School event fees must be approved before registrations can be approved.
        </p>

        <div class="mb-4 flex flex-wrap gap-2">
            <button type="button" class="btn-secondary text-xs" @click="bulkApprove" :disabled="!selectedIds.length">Approve selected ({{ selectedIds.length }})</button>
            <button type="button" class="btn-secondary text-xs text-red-600" @click="bulkReject" :disabled="!selectedIds.length">Reject selected</button>
        </div>

        <div class="card overflow-hidden p-0">
            <EmptyState
                v-if="!registrations.length"
                title="No registrations yet"
                description="Entries will appear here when schools submit registrations for this event."
                icon="📝"
            />
            <table v-else class="data-table">
                <thead>
                    <tr>
                        <th></th>
                        <th>School</th>
                        <th>Item</th>
                        <th>Status</th>
                        <th>Participants</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="reg in registrations" :key="reg.id">
                        <td><input v-if="reg.status === 'submitted'" type="checkbox" :value="reg.id" v-model="selectedIds"></td>
                        <td>{{ schools[reg.school_id] ?? reg.school_id }}</td>
                        <td>{{ reg.item?.head?.name ? `${reg.item.head.name} · ` : '' }}{{ reg.item?.title ?? '—' }}</td>
                        <td>
                            <span :class="statusClass(reg.status)" class="status-pill">
                                {{ reg.status }}
                            </span>
                        </td>
                        <td class="text-xs">{{ reg.participants?.length ?? 0 }} participant(s)</td>
                        <td class="text-right whitespace-nowrap">
                            <template v-if="reg.status === 'submitted'">
                                <button type="button" @click="approve(reg.id)" class="btn-ghost text-green-700">Approve</button>
                                <button type="button" @click="reject(reg.id)" class="btn-ghost text-red-600">Reject</button>
                            </template>
                            <button v-if="canCancel(reg)" type="button" @click="cancel(reg.id)" class="btn-ghost text-slate-600">Cancel</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PortalLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import ReportHeadSubNav from '@/Components/reports/ReportHeadSubNav.vue';
import { festOpsEventNav } from '@/support/festOpsPortalNav.js';

const props = defineProps({
    sahodaya: Object, event: Object, registrations: Array,
    schools: Object, feeRequired: Boolean, duties: Array,
    headItemGroups: { type: Array, default: () => [] },
    hasItemHeads: { type: Boolean, default: false },
    selectedHeadId: { type: [String, Number], default: null },
    selectedItemId: { type: [String, Number], default: null },
});

const selectedIds = ref([]);

const base = computed(() => `/portal/fest-ops/${props.sahodaya.id}/events/${props.event.id}`);

const navItems = computed(() => festOpsEventNav(props.sahodaya.id, props.event.id, props.duties));

function statusClass(status) {
    return {
        submitted: 'status-pill--ongoing',
        approved: 'status-pill--open',
        rejected: 'status-pill--draft',
        withdrawn: 'status-pill--draft',
    }[status] || 'status-pill--draft';
}

function canCancel(reg) {
    return !['withdrawn', 'rejected'].includes(reg.status);
}

function approve(id) {
    router.post(`${base.value}/registrations/${id}/approve`, {}, { preserveScroll: true });
}

function reject(id) {
    router.post(`${base.value}/registrations/${id}/reject`, {}, { preserveScroll: true });
}

function cancel(id) {
    router.post(`${base.value}/registrations/${id}/cancel`, {}, { preserveScroll: true });
}

function bulkApprove() {
    router.post(`${base.value}/registrations/bulk-approve`, { registration_ids: selectedIds.value }, {
        preserveScroll: true, onSuccess: () => { selectedIds.value = []; },
    });
}

function bulkReject() {
    if (!confirm(`Reject ${selectedIds.value.length} registration(s)?`)) return;
    router.post(`${base.value}/registrations/bulk-reject`, { registration_ids: selectedIds.value }, {
        preserveScroll: true, onSuccess: () => { selectedIds.value = []; },
    });
}
</script>
