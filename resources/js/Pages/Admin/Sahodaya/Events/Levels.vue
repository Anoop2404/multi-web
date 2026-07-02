<template>
    <SahodayaEventsLayout :title="`${event.title} — Levels`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Levels & cascade`" eyebrow="Multi-level"
                    description="School rounds, promotions, and child events." />

        <EventSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="levels" />

        <div class="grid lg:grid-cols-2 gap-6 max-w-4xl">
            <div class="card space-y-4">
                <h4 class="section-title">Current round</h4>
                <p class="section-desc">
                    Round: <strong class="text-slate-700">{{ levelLabels[event.level_round] ?? event.level_round }}</strong>
                </p>
                <form v-if="event.conduct_levels?.includes('school') && schoolRoundCount > 0" @submit.prevent="promoteAllSchoolRounds" class="space-y-1">
                    <button type="submit" class="btn-primary w-full text-sm">Promote all school-round winners</button>
                    <p class="form-hint">Only rounds with published results are included.</p>
                </form>
                <form v-if="event.conduct_levels?.includes('school')" @submit.prevent="spawnSchoolRounds">
                    <button type="submit" class="btn-secondary w-full text-sm">Create school rounds ({{ schoolRoundCount }} exist)</button>
                </form>
            </div>

            <div class="card space-y-4">
                <h4 class="section-title">Child events</h4>
                <form @submit.prevent="spawnChild" class="flex gap-2">
                    <input v-model="cascadeForm.title" class="field flex-1" placeholder="Child event title" required>
                    <button class="btn-primary text-sm shrink-0">Spawn child</button>
                </form>
                <ul v-if="event.child_events?.length" class="text-sm space-y-2">
                    <li v-for="c in event.child_events" :key="c.id">
                        <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${c.id}`" class="link-brand">{{ c.title }}</Link>
                    </li>
                </ul>
                <p v-else class="text-sm text-slate-400">No child events yet.</p>
                <p v-if="event.parent_event" class="section-desc">
                    Parent: <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.parent_event.id}`" class="link-brand">{{ event.parent_event.title }}</Link>
                </p>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8 max-w-4xl" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, levelLabels: Object, schoolRoundCount: Number,
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`;
const cascadeForm = useForm({ title: '' });

function spawnChild() {
    cascadeForm.post(`${base}/spawn`, { preserveScroll: true, onSuccess: () => cascadeForm.reset() });
}
function spawnSchoolRounds() {
    router.post(`${base}/spawn-school-rounds`, {}, { preserveScroll: true });
}
function promoteAllSchoolRounds() {
    if (!confirm('Promote winners from all school rounds with published results into this cluster event?')) return;
    router.post(`${base}/promote-all-school-rounds`, {}, { preserveScroll: true });
}
</script>
