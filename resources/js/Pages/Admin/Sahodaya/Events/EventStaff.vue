<template>
    <SahodayaEventsLayout :title="pageTitle" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="pageTitle" eyebrow="Administration"
                    :description="pageDescription" />
        <div class="mb-4">
            <Link :href="backHref" class="text-sm text-gray-500">{{ backLabel }}</Link>
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
            <div v-if="showHeadSelector" class="min-w-[180px]">
                <label class="text-xs text-gray-500">{{ headFieldLabel }}</label>
                <select v-model="form.head_id" class="field w-full" :required="headRequired">
                    <option v-if="!headRequired" value="">All heads</option>
                    <option v-for="h in heads" :key="h.id" :value="h.id">{{ h.name }}</option>
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
        <p v-if="showHeadSelector && !heads.length" class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg p-3 mb-4">
            Sync item heads under <strong>Setup hub → Item heads master</strong> before assigning coordinators.
        </p>
        <p v-if="isSports" class="text-xs text-slate-600 bg-slate-50 border border-slate-100 rounded-lg p-3 mb-4">
            Item head coordinators enter marks and manage competition for their head (Athletics, Chess, …).
            They sign in at <code>/portal/fest-coordinator/{{ sahodaya.id }}</code> or <code>/portal/fest-ops/{{ sahodaya.id }}</code>.
        </p>

        <ul class="card-list">
            <li v-for="a in assignments" :key="a.id" class="p-4 flex justify-between items-center">
                <span>
                    {{ a.user?.name }}
                    <span class="text-xs text-gray-400">
                        — {{ dutyLabel(a.duty) }}
                        <template v-if="a.stage"> · {{ a.stage.name }}</template>
                        <template v-if="a.head"> · {{ a.head.name }}</template>
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
import { computed, watch } from 'vue';
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
    heads: { type: Array, default: () => [] },
    duties: Array,
    activityLogs: { type: Array, default: () => [] },
});

const isSports = computed(() => props.event.event_type === 'sports');
const pageTitle = computed(() => (
    isSports.value
        ? `Item head coordinators — ${props.event.title}`
        : `Event staff — ${props.event.title}`
));
const pageDescription = computed(() => (
    isSports.value
        ? 'Assign one coordinator per item head — they enter marks and run day-of operations for that section.'
        : 'Assign fest ops and mark coordinators to duties.'
));
const backHref = computed(() => (
    isSports.value
        ? `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/setup`
        : `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`
));
const backLabel = computed(() => (isSports.value ? '← Setup hub' : '← Event'));

const defaultDuty = computed(() => {
    if (isSports.value) {
        return props.duties.find((d) => d.value === 'marks')?.value ?? props.duties[0]?.value ?? 'marks';
    }
    return props.duties[0]?.value ?? 'coordinator';
});

const form = useForm({ user_id: '', duty: defaultDuty.value, stage_id: '', head_id: '' });

watch(defaultDuty, (duty) => {
    if (!form.user_id) {
        form.duty = duty;
    }
});

const showHeadSelector = computed(() => (
    form.duty === 'discipline' || (isSports.value && form.duty === 'marks')
));
const headRequired = computed(() => isSports.value && form.duty === 'marks');
const headFieldLabel = computed(() => (
    isSports.value && form.duty === 'marks' ? 'Item head' : 'Item head (optional scope)'
));

function dutyLabel(duty) {
    return props.duties.find(d => d.value === duty)?.label ?? duty;
}

function stageOptionLabel(stage) {
    return stage.venue?.name ? `${stage.name} · ${stage.venue.name}` : stage.name;
}

function assign() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/event-staff`, {
        preserveScroll: true,
        onSuccess: () => form.reset('user_id', 'stage_id', 'head_id'),
    });
}

function remove(a) {
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/event-staff/${a.id}`, { preserveScroll: true });
}
</script>
