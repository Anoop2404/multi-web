<template>
    <SahodayaEventsLayout :title="`Event staff — ${event.title}`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`Event staff — ${event.title}`" eyebrow="Registration"
                    description="Assign fest ops and mark coordinators to duties." />
        <div class="mb-4">
            <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}`" class="text-sm text-gray-500">← Event</Link>
        </div>

        <form @submit.prevent="assign" class="card mb-4 flex flex-wrap gap-2 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="text-xs text-gray-500">User</label>
                <select v-model="form.user_id" class="field w-full" required>
                    <option value="">Select user</option>
                    <option v-for="u in staffPool" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500">Duty</label>
                <select v-model="form.duty" class="field" required>
                    <option v-for="d in duties" :key="d.value" :value="d.value">{{ d.label }}</option>
                </select>
            </div>
            <div v-if="form.duty === 'stage'" class="min-w-[180px]">
                <label class="text-xs text-gray-500">Stage (optional scope)</label>
                <select v-model="form.stage_id" class="field w-full">
                    <option value="">All stages</option>
                    <option v-for="s in stages" :key="s.id" :value="s.id">{{ stageOptionLabel(s) }}</option>
                </select>
            </div>
            <button class="btn-primary" :disabled="form.processing">Assign</button>
        </form>
        <p v-if="!staffPool.length" class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg p-3 mb-4">
            Create users with ops roles under
            <Link :href="`/sahodaya-admin/${sahodaya.id}/users`" class="font-semibold underline">Portal users</Link> first.
        </p>
        <p v-if="form.duty === 'stage' && !stages.length" class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg p-3 mb-4">
            Add stages under <strong>Event settings → Venues & stages</strong> before scoping stage managers.
        </p>

        <ul class="card-list">
            <li v-for="a in assignments" :key="a.id" class="p-4 flex justify-between items-center">
                <span>
                    {{ a.user?.name }}
                    <span class="text-xs text-gray-400">
                        — {{ dutyLabel(a.duty) }}
                        <template v-if="a.stage"> · {{ a.stage.name }}</template>
                    </span>
                </span>
                <button @click="remove(a)" class="text-xs text-red-600">Remove</button>
            </li>
            <li v-if="!assignments.length" class="p-6 text-center text-gray-400 text-sm">No event staff assigned.</li>
        </ul>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { Link, useForm, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    assignments: Array,
    staffPool: Array,
    stages: Array,
    duties: Array,
    activityLogs: { type: Array, default: () => [] },
});

const form = useForm({ user_id: '', duty: props.duties[0]?.value ?? 'coordinator', stage_id: '' });

function dutyLabel(duty) {
    return props.duties.find(d => d.value === duty)?.label ?? duty;
}

function stageOptionLabel(stage) {
    return stage.venue?.name ? `${stage.name} · ${stage.venue.name}` : stage.name;
}

function assign() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/event-staff`, {
        preserveScroll: true,
        onSuccess: () => form.reset('user_id', 'stage_id'),
    });
}

function remove(a) {
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/event-staff/${a.id}`, { preserveScroll: true });
}
</script>

