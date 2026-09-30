<template>
    <AdminLayout :title="`State slot collection — ${program.title}`">
        <div class="max-w-6xl space-y-4">
            <div class="card">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="font-semibold">Get a confirmed list before State registration</h3>
                        <p class="text-xs text-gray-500 mt-0.5 max-w-2xl">
                            Opening this auto-fills each item's top ranks, same as "Fill from the top" on
                            the winner sheet, and asks each winning school to accept or opt out of their
                            slot. If a school opts out, the next rank is offered the freed slot in their
                            place. Once every offer is answered, approve the list — after that you
                            register with State from the winner sheet exactly as before.
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass">
                        {{ statusLabel }}
                    </span>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button v-if="!event.state_slot_collection_open" type="button" class="btn-primary text-xs" @click="open">
                        Open slot collection
                    </button>
                    <template v-else-if="!event.state_slot_collection_approved_at">
                        <span class="text-xs text-gray-500">
                            {{ totals.accepted }} accepted · {{ totals.pending }} awaiting response · {{ totals.optedOut }} opted out
                        </span>
                        <button type="button" class="btn-primary text-xs ml-auto" :disabled="totals.pending > 0" @click="approve">
                            Approve list
                        </button>
                    </template>
                    <span v-else class="text-xs text-emerald-700">
                        Approved {{ formatDate(event.state_slot_collection_approved_at) }}. Register with State from the winner sheet.
                    </span>
                </div>

                <div v-if="event.state_slot_collection_open" class="mt-3 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">
                    <p class="text-[11px] font-semibold text-slate-600">How schools respond</p>
                    <p class="text-[11px] text-slate-600 mt-0.5">
                        Ask schools to log into their own school panel, then go to
                        <strong>Reports → this event → "State Slot Choices"</strong> — the offer shows up
                        there for any winner they have. No separate link is needed since each school
                        already signs into its own panel.
                    </p>
                </div>
            </div>

            <p v-if="!summary.length" class="card text-sm text-gray-400">
                No State items have results from this event yet.
            </p>

            <section v-for="row in summary" :key="row.item_id" class="card">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h4 class="font-semibold text-sm text-gray-800">
                            <span class="font-mono text-xs text-gray-400">{{ row.item_code }}</span>
                            {{ row.title }}
                        </h4>
                        <p class="text-[11px] text-gray-500">
                            <span v-if="row.category">{{ row.category }} · </span>
                            <span v-if="row.class_group">{{ row.class_group }} · </span>
                            {{ row.participant_type || 'individual' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5 shrink-0">
                        <span class="rounded-full px-2.5 py-1 text-[11px] font-semibold bg-emerald-100 text-emerald-700">
                            {{ row.school_responses.accepted }} accepted
                        </span>
                        <span v-if="row.school_responses.pending" class="rounded-full px-2.5 py-1 text-[11px] font-semibold bg-amber-100 text-amber-800">
                            {{ row.school_responses.pending }} pending
                        </span>
                        <span v-if="row.school_responses.opted_out" class="rounded-full px-2.5 py-1 text-[11px] font-semibold bg-gray-200 text-gray-700">
                            {{ row.school_responses.opted_out }} opted out
                        </span>
                    </div>
                </div>

                <p v-if="row.unresolved_ties.length" class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-[11px] text-amber-900">
                    Position {{ row.unresolved_ties.join(', ') }} is tied and more are tied than there are
                    slots. Resolve this on the winner sheet before this item can be approved.
                </p>

                <div v-if="row.chosen.length" class="mt-2 space-y-1">
                    <p v-for="s in row.chosen" :key="`c${s.id}`" class="text-[11px] flex items-center gap-2">
                        <span class="text-emerald-800"><strong>{{ s.student_name }}</strong> ({{ s.school_name }}) — position {{ s.source_position }}</span>
                        <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="responseChipClass(s.school_response)">
                            {{ responseLabel(s.school_response) }}
                        </span>
                        <span v-if="s.school_responded_at" class="text-gray-400">{{ formatDate(s.school_responded_at) }}</span>
                    </p>
                </div>
                <div v-if="row.declined.length" class="mt-2 space-y-1 border-t pt-2">
                    <p v-for="s in row.declined" :key="`d${s.id}`" class="text-[11px] text-gray-500">
                        <strong>Opted out:</strong> {{ s.student_name }} ({{ s.school_name }}), position {{ s.source_position }} — {{ s.note }}
                    </p>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>

<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({
    event: Object, program: Object, summary: Array, actionUrls: Object,
});

const totals = computed(() => props.summary.reduce((acc, row) => ({
    accepted: acc.accepted + row.school_responses.accepted,
    pending: acc.pending + row.school_responses.pending,
    optedOut: acc.optedOut + row.school_responses.opted_out,
}), { accepted: 0, pending: 0, optedOut: 0 }));

const statusLabel = computed(() => {
    if (props.event.state_slot_collection_approved_at) return 'Approved';
    if (props.event.state_slot_collection_open) return 'Open — awaiting responses';
    return 'Not opened yet';
});
const statusClass = computed(() => {
    if (props.event.state_slot_collection_approved_at) return 'bg-emerald-100 text-emerald-700';
    if (props.event.state_slot_collection_open) return 'bg-amber-100 text-amber-800';
    return 'bg-gray-100 text-gray-600';
});

function responseLabel(r) {
    return { accepted: 'Accepted', pending: 'Awaiting response', opted_out: 'Opted out' }[r] || '—';
}
function responseChipClass(r) {
    return {
        accepted: 'bg-emerald-100 text-emerald-700',
        pending: 'bg-amber-100 text-amber-800',
        opted_out: 'bg-gray-200 text-gray-700',
    }[r] || 'bg-gray-100 text-gray-500';
}
function formatDate(v) {
    if (!v) return '';
    return new Date(v).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

function open() {
    if (!confirm('Open slot collection? This fills each item\'s top ranks and sends the offer to schools.')) return;
    router.post(props.actionUrls.open, {}, { preserveScroll: true });
}
function approve() {
    if (!confirm('Approve this list? You can then register with State from the winner sheet.')) return;
    router.post(props.actionUrls.approve, {}, { preserveScroll: true });
}
</script>
