<template>
    <SahodayaEventsLayout :title="`${event.title} — Results`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Results`" eyebrow="Scoring"
                    description="Publish results, manage qualifications, and promote to the next round." />
        <div class="flex flex-wrap justify-between gap-3 mb-4">
            <div class="flex flex-wrap gap-2 items-center">
                <form @submit.prevent="promote" class="flex flex-wrap gap-2 items-center">
                    <select v-model="promoteForm.next_event_id" class="field" required>
                        <option value="">Promote winners to…</option>
                        <option v-for="e in nextEvents" :key="e.id" :value="e.id">
                            {{ e.title }} ({{ levelLabels[e.level_round] ?? e.level_round }}){{ e.suggested ? ' ★' : '' }}
                        </option>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm">Promote qualifiers</button>
                </form>
                <button v-if="suggestedNextId"
                        @click="promoteAuto"
                        class="px-4 py-2 bg-amber-50 border border-amber-300 text-amber-800 rounded-lg text-sm font-semibold">
                    Promote to next level ★
                </button>
            </div>
            <button @click="confirmPublish" class="btn-primary">Publish Results</button>
            <button v-if="event.results_published" @click="unpublish"
                    class="px-4 py-2 border border-red-200 text-red-700 rounded-lg text-sm font-medium">Unpublish</button>
        </div>

        <div class="grid lg:grid-cols-2 gap-4 mb-4">
            <ol class="card-list">
                <li v-for="row in scoreboard" :key="row.school_id" class="p-4 flex justify-between">
                    <span><strong>#{{ row.rank }}</strong> {{ row.school_name }}</span>
                    <span class="font-mono">{{ row.total_points }} pts</span>
                </li>
                <li v-if="!scoreboard.length" class="p-4 text-gray-400 text-sm">No results yet</li>
            </ol>

            <div class="card">
                <h3 class="font-semibold text-sm mb-2">Promoted participants</h3>
                <ul class="text-sm divide-y max-h-64 overflow-y-auto">
                    <li v-for="q in qualifications" :key="q.id" class="py-2 flex justify-between gap-2 items-start">
                        <div>
                            {{ participantLabel(q) }} — {{ q.item?.title }}
                            <span class="text-gray-400 text-xs block">→ {{ q.next_level_event?.title }}</span>
                        </div>
                        <button @click="revoke(q.id)" class="text-red-600 text-xs font-semibold shrink-0">Revoke</button>
                    </li>
                    <li v-if="!qualifications.length" class="py-2 text-gray-400">None yet</li>
                </ul>
            </div>
        </div>

        <div class="card">
            <h3 class="font-semibold text-sm mb-3">Quick exports</h3>
            <p class="text-xs text-gray-500 mb-2">
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/reports`" class="text-indigo-600 font-semibold">Open full Reports hub →</Link>
                (judge sheets, attendance PDFs, clash reports, admit cards, and more)
            </p>
            <div class="flex flex-wrap gap-2">
                <a v-for="link in exportLinks" :key="link.type" :href="link.href"
                   class="px-3 py-2 border rounded-lg text-sm hover:border-indigo-300">{{ link.label }} ↓</a>
            </div>
        </div>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />

        <div v-if="publishModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="publishModalOpen = false">
            <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl space-y-4">
                <h3 class="font-semibold text-gray-900">Publish results?</h3>
                <p class="text-sm text-gray-600">
                    Schools and the public portal will see published results for <strong>{{ event.title }}</strong>.
                    You can unpublish later if needed.
                </p>
                <div class="flex gap-2 justify-end">
                    <button type="button" class="btn-ghost text-sm" @click="publishModalOpen = false">Cancel</button>
                    <button type="button" class="btn-primary text-sm" @click="publish">Publish now</button>
                </div>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, watch, ref } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, scoreboard: Array, qualifications: Array, nextEvents: Array,
    suggestedNextId: Number, levelLabels: Object,
    activityLogs: { type: Array, default: () => [] },
});

const promoteForm = useForm({ next_event_id: props.suggestedNextId ?? '' });
const publishModalOpen = ref(false);

watch(() => props.suggestedNextId, (id) => {
    if (id && !promoteForm.next_event_id) promoteForm.next_event_id = id;
}, { immediate: true });

const exportLinks = computed(() => {
    const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/export`;
    return [
        { type: 'registrations', label: 'Registrations', href: `${base}/registrations` },
        { type: 'results', label: 'Results', href: `${base}/results` },
        { type: 'attendance', label: 'Attendance', href: `${base}/attendance` },
        { type: 'fees', label: 'Fees', href: `${base}/fees` },
    ];
});

function participantLabel(q) {
    return q.participant?.student?.name
        ?? q.participant?.teacher?.name
        ?? 'Participant';
}

function confirmPublish() {
    publishModalOpen.value = true;
}

function publish() {
    publishModalOpen.value = false;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/results/publish`, {}, { preserveScroll: true });
}

function unpublish() {
    if (!confirm('Unpublish results? Schools will no longer see published results.')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/results/unpublish`, {}, { preserveScroll: true });
}

function promote() {
    promoteForm.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/results/promote`, {
        preserveScroll: true,
    });
}

function promoteAuto() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/results/promote-auto`, {}, { preserveScroll: true });
}

function revoke(id) {
    if (!confirm('Revoke this promotion and cancel the next-level registration?')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/results/qualifications/${id}/revoke`, {}, { preserveScroll: true });
}
</script>

