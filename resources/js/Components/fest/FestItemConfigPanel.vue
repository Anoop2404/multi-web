<template>
    <div class="card space-y-4">
        <div>
            <h3 class="section-title">Item settings</h3>
            <p class="section-desc text-xs">
                {{ isSports
                    ? 'Dates and Event Head assignment. Fees come from the Event Head (items inherit head rates).'
                    : 'Override head dates, assign a different head, set fee, or disable this item on the event.' }}
            </p>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <FormField :label="isSports ? 'Event Head' : 'Item head'">
                <select v-model="row.head_id" class="field text-sm">
                    <option :value="null">Unassigned</option>
                    <option v-for="h in headsForAssign" :key="h.id" :value="h.id">{{ h.name }}</option>
                </select>
            </FormField>
            <FormField v-if="!isSports" label="Item fee (₹)">
                <input v-model.number="row.fee_amount" type="number" min="0" step="0.01" class="field text-sm" placeholder="Leave blank for head/default">
            </FormField>
            <p v-else class="text-xs text-slate-500 pt-6 sm:col-span-1">
                Item fee inherits from Event Head billing (school / student / team rates).
            </p>
            <label class="flex items-center gap-2 text-sm pt-6">
                <input v-model="row.is_enabled" type="checkbox">
                <span>Enabled on this event</span>
            </label>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <FormField label="Registration opens">
                <input v-model="row.reg_start" type="date" class="field text-sm">
            </FormField>
            <FormField label="Registration closes">
                <input v-model="row.reg_end" type="date" class="field text-sm">
            </FormField>
            <FormField label="Competition start">
                <input v-model="row.competition_start" type="date" class="field text-sm">
            </FormField>
            <FormField label="Competition end">
                <input v-model="row.competition_end" type="date" class="field text-sm">
            </FormField>
            <FormField label="Start time">
                <input v-model="row.competition_time" type="time" class="field text-sm">
            </FormField>
        </div>

        <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">
            <button type="button" class="btn-primary text-sm" :disabled="saving" @click="save">
                {{ saving ? 'Saving…' : 'Save item settings' }}
            </button>
            <Link v-if="catalogUrl" :href="catalogUrl" class="btn-secondary text-sm">Full catalog editor →</Link>
            <button v-if="canRemove"
                    type="button"
                    class="btn-secondary text-sm text-red-700 border-red-200 hover:bg-red-50 ml-auto"
                    :disabled="removing"
                    @click="removeItem">
                {{ removing ? 'Removing…' : 'Remove item' }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    sahodayaId: { type: [String, Number], required: true },
    eventId: { type: [String, Number], required: true },
    itemConfig: { type: Object, required: true },
    headsForAssign: { type: Array, default: () => [] },
    catalogUrl: { type: String, default: null },
    isSports: { type: Boolean, default: false },
});

const saving = ref(false);
const removing = ref(false);

function toDateInput(value) {
    if (!value) return '';
    return String(value).slice(0, 10);
}

function buildRow(config) {
    return {
        head_id: config.head_id ?? null,
        fee_amount: config.fee_amount ?? '',
        is_enabled: config.is_enabled !== false,
        reg_start: toDateInput(config.reg_start),
        reg_end: toDateInput(config.reg_end),
        competition_start: toDateInput(config.competition_start),
        competition_end: toDateInput(config.competition_end),
        competition_time: config.competition_time ? String(config.competition_time).slice(0, 5) : '',
    };
}

const row = reactive(buildRow(props.itemConfig));

watch(() => props.itemConfig, (config) => Object.assign(row, buildRow(config)), { deep: true });

const canRemove = props.itemConfig.can_remove ?? false;
const base = `/sahodaya-admin/${props.sahodayaId}/events/${props.eventId}`;

function save() {
    saving.value = true;
    router.patch(`${base}/items/${props.itemConfig.id}/windows`, {
        head_id: row.head_id || null,
        reg_start: row.reg_start || null,
        reg_end: row.reg_end || null,
        competition_start: row.competition_start || null,
        competition_end: row.competition_end || null,
        competition_time: row.competition_time || null,
        is_enabled: row.is_enabled,
        fee_amount: row.fee_amount === '' ? null : row.fee_amount,
    }, {
        preserveScroll: true,
        onFinish: () => { saving.value = false; },
    });
}

function removeItem() {
    if (!window.confirm(`Remove "${props.itemConfig.title}" from this event?`)) {
        return;
    }
    removing.value = true;
    router.delete(`${base}/items/${props.itemConfig.id}`, {
        preserveScroll: true,
        onFinish: () => { removing.value = false; },
    });
}
</script>
