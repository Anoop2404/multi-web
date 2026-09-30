<template>
    <AdminLayout title="State slot collection">
        <div class="max-w-4xl space-y-4">
            <div class="card">
                <h3 class="font-semibold">State slot collection</h3>
                <p class="text-xs text-gray-500 mt-0.5 max-w-2xl">
                    Every hub event linked to a State program. Open one to auto-fill its top ranks
                    and let schools accept or opt out before you register with State.
                </p>
            </div>

            <p v-if="!events.length" class="card text-sm text-gray-400">
                No event is linked to a State program yet. Link one from Levels → State Program on
                the hub event you want to send winners from.
            </p>

            <Link v-for="e in events" :key="e.id" :href="e.url" class="card block hover:border-indigo-300 transition-colors">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-800">{{ e.title }}</p>
                        <p class="text-[11px] text-gray-500 mt-0.5">
                            {{ e.program_title || 'State program' }}
                            <span v-if="e.event_start"> · {{ formatDate(e.event_start) }}<span v-if="e.event_end"> – {{ formatDate(e.event_end) }}</span></span>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5 shrink-0">
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="statusClass(e)">
                            {{ statusLabel(e) }}
                        </span>
                        <span v-if="e.collection_open && !e.collection_approved_at" class="text-[11px] text-gray-500">
                            {{ e.accepted }} accepted · {{ e.pending }} pending
                        </span>
                    </div>
                </div>
            </Link>
        </div>
    </AdminLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps({ events: { type: Array, default: () => [] } });

function statusLabel(e) {
    if (e.collection_approved_at) return 'Approved';
    if (e.collection_open) return 'Open — awaiting responses';
    return 'Not opened yet';
}
function statusClass(e) {
    if (e.collection_approved_at) return 'bg-emerald-100 text-emerald-700';
    if (e.collection_open) return 'bg-amber-100 text-amber-800';
    return 'bg-gray-100 text-gray-600';
}
function formatDate(v) {
    if (!v) return '';
    return new Date(v).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>
