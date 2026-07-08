<template>
    <PortalLayout
        role-label="Appeals officer"
        :title="event.title"
        :subtitle="sahodaya.name"
        accent="emerald"
        :nav-items="navItems"
    >
        <div class="card overflow-hidden p-0">
            <EmptyState
                v-if="!appeals.length"
                title="No appeals pending"
                description="Participant appeals submitted by schools will appear here for review."
                icon="⚖️"
            />
            <table v-else class="data-table">
                <thead>
                    <tr>
                        <th>Participant</th>
                        <th>Item</th>
                        <th>Status</th>
                        <th>Reason</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in appeals" :key="a.id">
                        <td class="font-medium">{{ a.participant?.student?.name ?? a.participant?.teacher?.name ?? '—' }}</td>
                        <td class="text-xs">{{ a.participant?.registration?.item?.title ?? '—' }}</td>
                        <td>
                            <span :class="statusClass(a.status)" class="status-pill">{{ a.status }}</span>
                        </td>
                        <td class="max-w-xs text-xs text-slate-600">{{ a.reason ?? '—' }}</td>
                        <td class="text-right whitespace-nowrap">
                            <template v-if="a.status === 'pending'">
                                <button type="button" @click="resolve(a.id, 'approved')" class="btn-ghost text-green-700">Approve</button>
                                <button type="button" @click="resolve(a.id, 'rejected')" class="btn-ghost text-red-600">Reject</button>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </PortalLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { festOpsEventNav } from '@/support/festOpsPortalNav.js';

const props = defineProps({ sahodaya: Object, event: Object, appeals: Array, duties: Array });

const base = computed(() => `/portal/fest-ops/${props.sahodaya.id}/events/${props.event.id}`);

const navItems = computed(() => festOpsEventNav(props.sahodaya.id, props.event.id, props.duties));

function statusClass(status) {
    return {
        pending: 'status-pill--ongoing',
        approved: 'status-pill--open',
        rejected: 'status-pill--draft',
    }[status] || 'status-pill--draft';
}

function resolve(id, status) {
    router.post(`${base.value}/appeals/${id}/resolve`, { status }, { preserveScroll: true });
}
</script>
