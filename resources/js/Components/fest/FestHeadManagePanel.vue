<template>
    <div class="card mb-6 space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="section-title">{{ head.head_name }}</h3>
                <p class="section-desc text-xs">
                    Schedule, fees, and policy for this head — same fields as when the head was created.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <span v-if="headRecord?.is_team_heading" class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">
                    ID card heading
                </span>
                <span v-if="headRecord?.status" class="text-xs px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 capitalize">
                    {{ headRecord.status.replace('_', ' ') }}
                </span>
                <button type="button" class="btn-secondary text-sm" @click="openHeadEdit">Edit head &amp; fees</button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <FormField label="Registration opens">
                <input v-model="row.reg_start" type="date" class="field text-sm">
            </FormField>
            <FormField label="Registration closes">
                <input v-model="row.reg_end" type="date" class="field text-sm">
            </FormField>
            <FormField label="Venue">
                <input v-model="row.venue" type="text" class="field text-sm" placeholder="Competition venue">
            </FormField>
            <FormField label="Status">
                <select v-model="row.status" class="field text-sm">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                    <option value="registration_open">Registration open</option>
                    <option value="ongoing">Ongoing</option>
                    <option value="completed">Completed</option>
                </select>
            </FormField>
            <FormField label="Event start">
                <input v-model="row.event_start" type="date" class="field text-sm">
            </FormField>
            <FormField label="Event end">
                <input v-model="row.event_end" type="date" class="field text-sm">
            </FormField>
        </div>

        <div class="border-t border-slate-100 pt-4 space-y-3">
            <div>
                <p class="text-xs font-semibold text-slate-700 mb-1">How do items under this head run?</p>
                <div class="flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="row.schedule_mode" type="radio" value="same_time" class="border-slate-300">
                        All items at the same time
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="row.schedule_mode" type="radio" value="different_days" class="border-slate-300">
                        Items on different days
                    </label>
                </div>
            </div>

            <div v-if="row.schedule_mode === 'same_time'" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <FormField label="Competition date">
                    <input v-model="row.competition_start" type="date" class="field text-sm">
                </FormField>
                <FormField label="Start time">
                    <input v-model="row.competition_time" type="time" class="field text-sm">
                </FormField>
            </div>

            <div v-else class="grid gap-3 sm:grid-cols-2">
                <FormField label="Competition window start">
                    <input v-model="row.competition_start" type="date" class="field text-sm">
                </FormField>
                <FormField label="Competition window end">
                    <input v-model="row.competition_end" type="date" class="field text-sm">
                </FormField>
            </div>
        </div>

        <div v-if="showHeadFees" class="border-t border-slate-100 pt-4 space-y-3">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fees &amp; policy</p>
            <FestHeadFeeFields v-model="feeFields" :show-help="false" />
        </div>

        <div v-if="notificationTriggers.length" class="border-t border-slate-100 pt-4 space-y-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Notifications</p>
                <p class="section-desc text-xs mt-1">
                    Untick anything this head shouldn't send. Everything is on by default.
                </p>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <label v-for="trigger in notificationTriggers" :key="trigger"
                       class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" class="rounded border-slate-300"
                           v-model="notif.enabled[trigger]">
                    {{ triggerLabel(trigger) }}
                </label>
            </div>

            <div v-if="eligibleNotificationUsers.length">
                <p class="text-xs font-semibold text-slate-700 mb-1">Also notify these platform users</p>
                <select v-model="notif.extra_recipient_user_ids" multiple class="field text-sm h-28">
                    <option v-for="user in eligibleNotificationUsers" :key="user.id" :value="user.id">
                        {{ user.name }}{{ user.email ? ` — ${user.email}` : '' }}
                    </option>
                </select>
                <p class="text-[11px] text-slate-500 mt-1">Ctrl/Cmd-click to select more than one.</p>
            </div>

            <button type="button" class="btn-secondary text-sm" :disabled="savingNotifications" @click="saveNotifications">
                {{ savingNotifications ? 'Saving…' : 'Save notification settings' }}
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
            <label class="flex items-center gap-2 text-xs text-slate-600">
                <input v-model="row.apply_to_items" type="checkbox" class="rounded border-slate-300">
                Apply dates to all items under this head
            </label>
            <button type="button" class="btn-primary text-sm"
                    :disabled="saving"
                    @click="save">
                {{ saving ? 'Saving…' : 'Save head settings' }}
            </button>
            <button v-if="canRemove"
                    type="button"
                    class="btn-secondary text-sm text-red-700 border-red-200 hover:bg-red-50 ml-auto"
                    :disabled="removing"
                    @click="removeHead">
                {{ removing ? 'Removing…' : 'Remove head' }}
            </button>
            <Link :href="itemsForHeadUrl" class="btn-secondary text-sm" :class="{ 'ml-auto': !canRemove }">
                Add / list items under this head →
            </Link>
        </div>

        <div v-if="editingHead" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 overflow-y-auto py-8" @click.self="closeHeadEdit">
            <form @submit.prevent="saveHeadMeta" class="card w-full max-w-2xl shadow-xl space-y-4 my-auto">
                <div>
                    <h3 class="section-title">Edit Event Head</h3>
                    <p class="section-desc text-xs mt-1">Same fee and policy layout as Add Event Head.</p>
                </div>

                <FormField label="Event Head name">
                    <input v-model="headForm.name" class="field" required>
                </FormField>
                <FormField label="Sport discipline">
                    <select v-model="headForm.sport_discipline" class="field">
                        <option value="">Any</option>
                        <option v-for="(label, key) in disciplines" :key="key" :value="key">{{ label }}</option>
                    </select>
                </FormField>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" v-model="headForm.is_team_heading"> Use as ID card heading
                </label>

                <div class="border-t border-slate-100 pt-4 space-y-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Fees &amp; policy</p>
                    <FestHeadFeeFields v-model="editFeeFields" />
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" class="btn-secondary" @click="closeHeadEdit">Cancel</button>
                    <button type="submit" class="btn-primary" :disabled="headForm.processing">Save head</button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import FestHeadFeeFields from '@/Components/fest/FestHeadFeeFields.vue';
import { emptyHeadFeeFields, headFeeFieldsFromRecord } from '@/support/festHeadFeeFields';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    eventId: { type: [String, Number], required: true },
    head: { type: Object, required: true },
    headRecord: { type: Object, default: null },
    disciplines: { type: Object, default: () => ({}) },
    showHeadFees: { type: Boolean, default: true },
    notificationTriggers: { type: Array, default: () => [] },
    eligibleNotificationUsers: { type: Array, default: () => [] },
});

const saving = ref(false);
const savingNotifications = ref(false);
const removing = ref(false);
const editingHead = ref(false);
const headForm = useForm({
    name: '',
    sport_discipline: '',
    is_team_heading: true,
});
const feeFields = reactive(emptyHeadFeeFields());
const editFeeFields = reactive(emptyHeadFeeFields());

function toDateInput(value) {
    if (!value) return '';
    return String(value).slice(0, 10);
}

function buildRow(source) {
    return {
        reg_start: toDateInput(source?.reg_start),
        reg_end: toDateInput(source?.reg_end),
        competition_start: toDateInput(source?.competition_start),
        competition_end: toDateInput(source?.competition_end),
        schedule_mode: source?.schedule_mode === 'same_time' ? 'same_time' : 'different_days',
        competition_time: source?.competition_time ? String(source.competition_time).slice(0, 5) : '',
        venue: source?.venue ?? '',
        status: source?.status || 'draft',
        event_start: toDateInput(source?.event_start),
        event_end: toDateInput(source?.event_end),
        apply_to_items: true,
    };
}

const row = reactive(buildRow(props.headRecord ?? props.head));

function syncFeeFields(source) {
    Object.assign(feeFields, headFeeFieldsFromRecord(source ?? {}));
}

syncFeeFields(props.headRecord);

watch(
    () => [props.headRecord, props.head],
    () => {
        Object.assign(row, buildRow(props.headRecord ?? props.head));
        syncFeeFields(props.headRecord);
    },
    { deep: true },
);

const canRemove = props.headRecord?.can_remove ?? false;
const itemsForHeadUrl = `/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}/items?head_id=${props.head.head_id}`;

function buildNotif(source) {
    const disabled = new Set(source?.notification_settings?.disabled_triggers ?? []);
    const enabled = {};
    for (const trigger of props.notificationTriggers) {
        enabled[trigger] = !disabled.has(trigger);
    }

    return {
        enabled,
        extra_recipient_user_ids: [...(source?.notification_settings?.extra_recipient_user_ids ?? [])],
    };
}

const notif = reactive(buildNotif(props.headRecord));

function triggerLabel(trigger) {
    return trigger.replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());
}

watch(
    () => props.headRecord,
    (source) => { Object.assign(notif, buildNotif(source)); },
    { deep: true },
);

function saveNotifications() {
    savingNotifications.value = true;
    const disabledTriggers = props.notificationTriggers.filter((trigger) => notif.enabled[trigger] === false);

    router.patch(`/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}/item-heads/${props.head.head_id}/notifications`, {
        disabled_triggers: disabledTriggers,
        extra_recipient_user_ids: notif.extra_recipient_user_ids,
    }, {
        preserveScroll: true,
        onFinish: () => { savingNotifications.value = false; },
    });
}

function openHeadEdit() {
    headForm.clearErrors();
    headForm.name = props.headRecord?.name ?? props.head.head_name ?? '';
    headForm.sport_discipline = props.headRecord?.sport_discipline ?? '';
    headForm.is_team_heading = props.headRecord?.is_team_heading !== false;
    Object.assign(editFeeFields, headFeeFieldsFromRecord(props.headRecord ?? {}));
    editingHead.value = true;
}

function closeHeadEdit() {
    editingHead.value = false;
    headForm.clearErrors();
}

function nullableNumber(value) {
    return value === '' || value === null || value === undefined ? null : Number(value);
}

function feePayload(fields) {
    return {
        school_registration_fee: nullableNumber(fields.school_registration_fee),
        student_registration_fee: nullableNumber(fields.student_registration_fee),
        team_registration_fee: nullableNumber(fields.team_registration_fee),
        included_items_per_student: fields.included_items_per_student === '' ? 0 : Number(fields.included_items_per_student ?? 0),
        included_teams: fields.included_teams === '' ? 0 : Number(fields.included_teams ?? 0),
        verification_policy: fields.verification_policy || 'all_students',
        approval_policy: fields.approval_policy || 'auto',
        max_participants: nullableNumber(fields.max_participants),
        max_teams: nullableNumber(fields.max_teams),
    };
}

function saveHeadMeta() {
    headForm.transform((data) => ({
        ...data,
        ...feePayload(editFeeFields),
    })).patch(`/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}/item-heads/${props.head.head_id}/windows`, {
        preserveScroll: true,
        onSuccess: closeHeadEdit,
    });
}

function save() {
    saving.value = true;
    const sameTime = row.schedule_mode === 'same_time';
    router.patch(`/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}/item-heads/${props.head.head_id}/windows`, {
        reg_start: row.reg_start || null,
        reg_end: row.reg_end || null,
        competition_start: row.competition_start || null,
        competition_end: sameTime ? (row.competition_start || null) : (row.competition_end || null),
        schedule_mode: row.schedule_mode,
        competition_time: sameTime ? (row.competition_time || null) : null,
        apply_to_items: row.apply_to_items,
        venue: row.venue || null,
        status: row.status || 'draft',
        event_start: row.event_start || null,
        event_end: row.event_end || null,
        ...feePayload(feeFields),
    }, {
        preserveScroll: true,
        onFinish: () => { saving.value = false; },
    });
}

function removeHead() {
    if (!window.confirm(`Remove "${props.head.head_name}"? Linked items will become unassigned.`)) {
        return;
    }
    removing.value = true;
    router.delete(`/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}/item-heads/${props.head.head_id}`, {
        preserveScroll: true,
        onFinish: () => { removing.value = false; },
    });
}
</script>
