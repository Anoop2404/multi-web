<template>
    <SahodayaEventsLayout :title="pageTitle" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="pageTitle" eyebrow="Administration"
                    :description="pageDescription" />
        <div class="mb-4">
            <Link :href="backHref" class="text-sm text-gray-500">{{ backLabel }}</Link>
        </div>

        <section v-if="canManageEventLogins" class="card mb-4 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h3 class="font-semibold">Event admin logins</h3>
                <button type="button" class="btn-secondary text-xs" :disabled="provisioning" @click="provisionLogins">Create missing event logins</button>
            </div>
            <p class="text-xs text-slate-500">A separate account for each event and regional event, with event operations access and no finance access.</p>
            <EventAdminLoginCard v-for="account in eventAdminAccounts" :key="account.event_id" :credentials="account" :tenant-id="sahodaya.id" />
        </section>

        <form @submit.prevent="assign" class="card mb-4 flex flex-wrap gap-2 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="text-xs text-gray-500">User</label>
                <SearchableSelect v-model="form.user_id" class="w-full" :options="staffOptions" :required="true" :all-option="true" all-label="Select user" />
            </div>
            <div>
                <label class="text-xs text-gray-500">Duty</label>
                <SearchableSelect v-model="form.duty" :options="duties" :required="true" :all-option="false" placeholder="Select duty" />
            </div>
            <div v-if="form.duty === 'stage'" class="min-w-[180px]">
                <label class="text-xs text-gray-500">Stage (optional scope)</label>
                <SearchableSelect v-model="form.stage_id" class="w-full" :options="stageOptions" :all-option="true" all-label="All stages" />
            </div>
            <div v-if="showHeadSelector" class="min-w-[180px]">
                <label class="text-xs text-gray-500">{{ headFieldLabel }}</label>
                <SearchableSelect v-model="form.head_id" class="w-full" :options="heads" :required="headRequired" :all-option="!headRequired" all-label="All heads" />
            </div>
            <div v-if="regionOptions && regionOptions.length && form.duty !== 'phase_admin'" class="min-w-[180px]">
                <label class="text-xs text-gray-500">Region (optional scope)</label>
                <SearchableSelect v-model="form.region_id" class="w-full" :options="regionOptions" :all-option="true" all-label="All regions" />
            </div>
            <div v-if="(form.duty === 'region_admin' || form.duty === 'phase_admin') && phaseOptions.length" class="min-w-[180px]">
                <label class="text-xs text-gray-500">{{ form.duty === 'phase_admin' ? 'Phase (required — every region)' : 'Phase (optional scope)' }}</label>
                <SearchableSelect v-model="form.source_phase_id" class="w-full" :options="phaseOptions" :required="form.duty === 'phase_admin'" :all-option="true" :all-label="form.duty === 'phase_admin' ? 'Select phase' : 'All phases in region'" />
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
            Sync Event Heads under <strong>Setup hub → Event Heads</strong> before assigning coordinators.
        </p>
        <p v-if="form.duty === 'phase_admin' && !phaseOptions.length" class="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg p-3 mb-4">
            This event has no regional phases configured yet — set one up under <strong>Phases</strong> before assigning a phase admin.
        </p>
        <p v-if="isSports" class="text-xs text-slate-600 bg-slate-50 border border-slate-100 rounded-lg p-3 mb-4">
            Event Head coordinators enter marks and manage competition for their head (Athletics, Chess, …).
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
                        <template v-if="a.region"> · {{ a.region.name }}</template>
                        <template v-if="a.source_phase"> · {{ a.source_phase.name }}</template>
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
import { computed, ref, watch } from 'vue';
import EventAdminLoginCard from '@/Components/sahodaya/EventAdminLoginCard.vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    assignments: Array,
    eventAdminAccounts: { type: Array, default: () => [] },
    canManageEventLogins: Boolean,
    staffPool: Array,
    stages: Array,
    heads: { type: Array, default: () => [] },
    regionOptions: { type: Array, default: () => [] },
    phaseOptions: { type: Array, default: () => [] },
    duties: Array,
    activityLogs: { type: Array, default: () => [] },
});

const provisioning = ref(false);
function provisionLogins() {
    provisioning.value = true;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/event-admin-login`, {}, {
        preserveScroll: true,
        onFinish: () => { provisioning.value = false; },
    });
}

const isSports = computed(() => props.event.event_type === 'sports');
const pageTitle = computed(() => (
    isSports.value
        ? `Event Head coordinators — ${props.event.title}`
        : `Event staff — ${props.event.title}`
));
const pageDescription = computed(() => (
    isSports.value
        ? 'Assign one coordinator per Event Head — they enter marks and run day-of operations for that section.'
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

const form = useForm({ user_id: '', duty: defaultDuty.value, stage_id: '', head_id: '', region_id: '', source_phase_id: '' });

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
    isSports.value && form.duty === 'marks' ? 'Event Head' : 'Item head (optional scope)'
));

function dutyLabel(duty) {
    return props.duties.find(d => d.value === duty)?.label ?? duty;
}

function stageOptionLabel(stage) {
    return stage.venue?.name ? `${stage.name} · ${stage.venue.name}` : stage.name;
}

const staffOptions = computed(() => props.staffPool.map((u) => ({ value: u.id, label: `${u.name} (${u.email})` })));
const stageOptions = computed(() => props.stages.map((s) => ({ value: s.id, label: stageOptionLabel(s) })));

function assign() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/event-staff`, {
        preserveScroll: true,
        onSuccess: () => form.reset('user_id', 'stage_id', 'head_id', 'source_phase_id'),
    });
}

function remove(a) {
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/event-staff/${a.id}`, { preserveScroll: true });
}
</script>
