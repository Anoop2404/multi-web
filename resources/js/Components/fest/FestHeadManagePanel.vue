<template>
    <div class="card mb-6 space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="section-title">{{ head.head_name }}</h3>
                <p class="section-desc text-xs">
                    Set registration and competition dates once — optionally apply to all {{ head.item_count }} linked item(s).
                    Head fees apply when the event uses composite sports billing.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <span v-if="headRecord?.is_team_heading" class="text-xs px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700">
                    ID card heading
                </span>
                <button type="button" class="btn-secondary text-sm" @click="openHeadEdit">Edit head</button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <FormField label="Registration opens">
                <input v-model="row.reg_start" type="date" class="field text-sm">
            </FormField>
            <FormField label="Registration closes">
                <input v-model="row.reg_end" type="date" class="field text-sm">
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
                <p class="text-[11px] text-slate-500 self-end pb-2 lg:col-span-1 sm:col-span-2">
                    Every item under this head runs together on this date and time.
                </p>
            </div>

            <div v-else class="grid gap-3 sm:grid-cols-2">
                <FormField label="Competition window start">
                    <input v-model="row.competition_start" type="date" class="field text-sm">
                </FormField>
                <FormField label="Competition window end">
                    <input v-model="row.competition_end" type="date" class="field text-sm">
                </FormField>
                <p class="text-[11px] text-slate-500 sm:col-span-2">
                    Items run on different days — set each item's date and time individually below.
                    This window is just the overall span shown to schools.
                </p>
            </div>
        </div>

        <div v-if="showHeadFees" class="grid gap-3 sm:grid-cols-2 border-t border-slate-100 pt-4">
            <FormField label="Default item fee (₹)">
                <input v-model.number="row.default_item_fee" type="number" min="0" step="0.01" class="field text-sm" placeholder="Per billed item">
            </FormField>
            <FormField label="Extra item fee (₹)">
                <input v-model.number="row.extra_item_fee" type="number" min="0" step="0.01" class="field text-sm" placeholder="Beyond included quota">
            </FormField>
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

        <div v-if="editingHead" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeHeadEdit">
            <form @submit.prevent="saveHeadMeta" class="card w-full max-w-lg shadow-xl space-y-4">
                <div>
                    <h3 class="section-title">Edit item head</h3>
                    <p class="section-desc text-xs mt-1">Rename this head or change its master discipline and ID-card use.</p>
                </div>

                <FormField label="Head name">
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

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    eventId: { type: [String, Number], required: true },
    head: { type: Object, required: true },
    headRecord: { type: Object, default: null },
    disciplines: { type: Object, default: () => ({}) },
    showHeadFees: { type: Boolean, default: true },
});

const saving = ref(false);
const removing = ref(false);
const editingHead = ref(false);
const headForm = useForm({
    name: '',
    sport_discipline: '',
    is_team_heading: true,
});

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
        default_item_fee: source?.default_item_fee ?? '',
        extra_item_fee: source?.extra_item_fee ?? '',
        apply_to_items: true,
    };
}

const row = reactive(buildRow(props.headRecord ?? props.head));

watch(
    () => [props.headRecord, props.head],
    () => Object.assign(row, buildRow(props.headRecord ?? props.head)),
    { deep: true },
);

const canRemove = props.headRecord?.can_remove ?? false;
const itemsForHeadUrl = `/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}/items?head_id=${props.head.head_id}`;

function openHeadEdit() {
    headForm.clearErrors();
    headForm.name = props.headRecord?.name ?? props.head.head_name ?? '';
    headForm.sport_discipline = props.headRecord?.sport_discipline ?? '';
    headForm.is_team_heading = props.headRecord?.is_team_heading !== false;
    editingHead.value = true;
}

function closeHeadEdit() {
    editingHead.value = false;
    headForm.clearErrors();
}

function saveHeadMeta() {
    headForm.patch(`/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}/item-heads/${props.head.head_id}/windows`, {
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
        default_item_fee: row.default_item_fee === '' ? null : row.default_item_fee,
        extra_item_fee: row.extra_item_fee === '' ? null : row.extra_item_fee,
        apply_to_items: row.apply_to_items,
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
