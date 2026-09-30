<template>
    <SchoolAdminLayout :title="`State slot choices — ${programLabel}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`${event.title} — State slot choices`"
            :eyebrow="programLabel"
            description="If one of our students or teams earned a slot to represent the Sahodaya at State, accept it or opt out here."
        >
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← Event reports</Link>
            </template>
        </PageHeader>

        <div v-if="event.state_slot_collection_approved_at" class="card mb-4 border border-emerald-200 bg-emerald-50/60 text-xs text-emerald-800">
            The Sahodaya has approved this list. It is now final on our side.
        </div>
        <div v-else-if="!event.state_slot_collection_open" class="card mb-4 text-xs text-gray-500">
            The Sahodaya hasn't opened State slot collection for this event yet. Check back later.
        </div>

        <p v-if="!slots.length" class="card text-sm text-gray-400">
            No State slots have been offered to us for this event.
        </p>

        <div v-for="s in slots" :key="s.id" class="card mb-3 flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm font-semibold text-gray-800">
                    <span class="font-mono text-xs text-gray-400">{{ s.item_code }}</span>
                    {{ s.item_title }}
                </p>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ s.student_name }}<span v-if="s.class_name"> · {{ s.class_name }}</span> — position {{ s.source_position }}
                </p>
                <p v-if="s.opt_out_reason" class="text-[11px] text-gray-500 mt-1">Reason given: {{ s.opt_out_reason }}</p>
                <p v-if="s.responded_at" class="text-[11px] text-gray-400 mt-0.5">
                    {{ responseLabel(s.school_response) }} by {{ s.responded_by_name || 'you' }} on {{ formatDate(s.responded_at) }}
                </p>
            </div>

            <div class="shrink-0">
                <span v-if="s.school_response !== 'pending'"
                      class="rounded-full px-3 py-1 text-[11px] font-semibold" :class="responseChipClass(s.school_response)">
                    {{ responseLabel(s.school_response) }}
                </span>
                <div v-else class="flex gap-2">
                    <button type="button" class="btn-primary text-xs !py-1.5" @click="accept(s)">Accept</button>
                    <button type="button" class="btn-secondary text-xs !py-1.5" @click="optOut(s)">Opt out</button>
                </div>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { router, Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object, program: [String, Object], programMeta: { type: Object, default: null },
    event: Object, stateProgram: { type: Object, default: null }, slots: { type: Array, default: () => [] },
    actionUrlBase: String,
});

const { programLabel, programBase } = useSchoolProgramContext(props);

function responseLabel(r) {
    return { accepted: 'Accepted', pending: 'Awaiting your response', opted_out: 'Opted out' }[r] || '—';
}
function responseChipClass(r) {
    return {
        accepted: 'bg-emerald-100 text-emerald-700',
        opted_out: 'bg-gray-200 text-gray-700',
    }[r] || 'bg-amber-100 text-amber-800';
}
function formatDate(v) {
    if (!v) return '';
    return new Date(v).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

function accept(slot) {
    if (!confirm(`Confirm ${slot.student_name} is going to State for ${slot.item_title}?`)) return;
    router.post(`${props.actionUrlBase}/${slot.id}/accept`, {}, { preserveScroll: true });
}
function optOut(slot) {
    const reason = window.prompt(`Why can ${slot.student_name} not go to State for ${slot.item_title}? The Sahodaya will see this.`);
    if (!reason || !reason.trim()) return;
    router.post(`${props.actionUrlBase}/${slot.id}/opt-out`, { reason }, { preserveScroll: true });
}
</script>
