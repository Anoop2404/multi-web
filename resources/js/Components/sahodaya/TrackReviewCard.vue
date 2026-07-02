<template>
    <div class="card p-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="font-semibold text-slate-900">{{ label }}</p>
            <p class="text-xs capitalize mt-1" :class="statusClass">{{ statusLabel }}</p>
            <p v-if="rejectionReason" class="text-xs text-red-600 mt-2">{{ rejectionReason }}</p>
        </div>
        <div v-if="status === 'submitted'" class="flex flex-wrap gap-2">
            <button type="button" class="btn-primary text-xs !min-h-0 !py-1.5" @click="approve">Approve</button>
            <button type="button" class="btn-secondary text-xs !min-h-0 !py-1.5" @click="reject">Reject</button>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    label: String,
    status: String,
    rejectionReason: String,
    track: String,
    submissionId: [Number, String],
    sahodayaId: [Number, String],
});

const base = computed(() => `/sahodaya-admin/${props.sahodayaId}/membership/submissions/${props.submissionId}`);

const statusLabel = computed(() => ({
    pending: 'Not submitted by school',
    submitted: 'Awaiting your review',
    approved: 'Approved',
    rejected: 'Rejected',
    not_applicable: 'Not required',
}[props.status] ?? props.status));

const statusClass = computed(() => ({
    submitted: 'text-amber-700 font-semibold',
    approved: 'text-green-700 font-semibold',
    rejected: 'text-red-600 font-semibold',
    pending: 'text-slate-500',
}[props.status] ?? 'text-slate-500'));

function approve() {
    router.post(`${base.value}/approve-track`, { track: props.track }, { preserveScroll: true });
}

function reject() {
    const reason = prompt('Reason for rejection (required):');
    if (!reason?.trim()) return;
    router.post(`${base.value}/reject-track`, { track: props.track, reason: reason.trim() }, { preserveScroll: true });
}
</script>
