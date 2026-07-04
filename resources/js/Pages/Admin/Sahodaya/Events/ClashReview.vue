<template>
    <SahodayaEventsLayout :title="`Clash reports — ${event.title}`" :sahodaya="sahodaya" :event="event" :show-header-title="false">
        <PageHeader :title="`Schedule clash reports`" :description="event.title" />

        <div class="card overflow-hidden p-0">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>School</th>
                        <th>Participant</th>
                        <th>Description</th>
                        <th>Requested resolution</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in requests.data" :key="r.id">
                        <td>{{ r.school?.name }}</td>
                        <td>{{ r.participant?.student?.name || '—' }}</td>
                        <td class="text-sm max-w-xs">{{ r.description }}</td>
                        <td class="text-sm max-w-xs">{{ r.requested_resolution || '—' }}</td>
                        <td><span class="text-xs capitalize">{{ r.status }}</span></td>
                        <td class="text-right whitespace-nowrap">
                            <template v-if="r.status === 'pending'">
                                <button type="button" class="btn-primary text-xs mr-1" @click="approve(r)">Resolve</button>
                                <button type="button" class="btn-secondary text-xs" @click="reject(r)">Reject</button>
                            </template>
                        </td>
                    </tr>
                    <tr v-if="!requests.data?.length">
                        <td colspan="6" class="text-center text-slate-400 py-8">No clash reports.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';

const props = defineProps({
    sahodaya: Object,
    event: Object,
    requests: Object,
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/clash-requests`;

function approve(r) {
    router.post(`${base}/${r.id}/approve`, {}, { preserveScroll: true });
}

function reject(r) {
    const note = window.prompt('Rejection note (optional)') || '';
    router.post(`${base}/${r.id}/reject`, { resolution_note: note }, { preserveScroll: true });
}
</script>
