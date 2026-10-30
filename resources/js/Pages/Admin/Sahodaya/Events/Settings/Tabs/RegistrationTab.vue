<template>
    <div class="space-y-6 max-w-5xl">
        <ValidationBanner :errors="registrationSettingsForm.errors" class="mb-4" />
        <form @submit.prevent="saveRegistrationSettings" class="card space-y-4">
            <div>
                <h3 class="section-title">Event vs item registration</h3>
                <p class="section-desc">Require students to register for the event (per-student fee, configured on the Fees & Registration tab) before item entries.</p>
            </div>
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" v-model="registrationSettingsForm.require_event_registration" class="mt-0.5">
                <span>Require event registration before item registration</span>
            </label>
            <div class="grid gap-3 sm:grid-cols-2">
                <FormField label="Event registration opens">
                    <template #default="{ id }">
                        <input :id="id" v-model="registrationSettingsForm.event_reg_start" type="date" class="field">
                    </template>
                </FormField>
                <FormField label="Event registration closes">
                    <template #default="{ id }">
                        <input :id="id" v-model="registrationSettingsForm.event_reg_end" type="date" class="field">
                    </template>
                </FormField>
            </div>
            <label class="flex items-start gap-2 text-sm">
                <input type="checkbox" v-model="registrationSettingsForm.allow_student_self_register" class="mt-0.5">
                <span>Allow students to self-register from their portal profile</span>
            </label>
            <FormField label="Student verification for item registration">
                <SearchableSelect v-model="registrationSettingsForm.student_verification_mode"
                                   :options="studentVerificationOptions"
                                   :all-option="false" />
            </FormField>
            <FormActions>
                <button type="submit" class="btn-primary" :disabled="registrationSettingsForm.processing">Save registration settings</button>
            </FormActions>
        </form>

        <section v-if="itemHeads.length" class="card space-y-4">
            <div>
                <h3 class="section-title">Per-head windows</h3>
                <p class="section-desc text-xs">Set registration and competition dates for this event. Items inherit these dates unless overridden below.</p>
            </div>
            <div class="overflow-x-auto rounded-xl border border-slate-100">
                <table class="data-table text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600">Head</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600">Reg opens</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600">Reg closes</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600">Competition start</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600">Competition end</th>
                            <th class="px-3 py-2 w-24"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="row in headRows" :key="row.id">
                            <td class="px-3 py-2 font-medium text-slate-900">{{ row.name }}</td>
                            <td class="px-3 py-2"><input v-model="row.reg_start" :aria-label="`${row.title ?? row.name} — registration opens`" type="date" class="field text-xs"></td>
                            <td class="px-3 py-2"><input v-model="row.reg_end" :aria-label="`${row.title ?? row.name} — registration closes`" type="date" class="field text-xs"></td>
                            <td class="px-3 py-2"><input v-model="row.competition_start" :aria-label="`${row.title ?? row.name} — competition start`" type="date" class="field text-xs"></td>
                            <td class="px-3 py-2"><input v-model="row.competition_end" :aria-label="`${row.title ?? row.name} — competition end`" type="date" class="field text-xs"></td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" class="btn-secondary text-xs py-1 px-2"
                                        :disabled="savingHeadId !== null || savingId !== null || bulkSavingItemWindows"
                                        @click="saveHeadWindowRow(row)">
                                    {{ savingHeadId === row.id ? '…' : 'Save' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="section-title">Per-item windows</h3>
                    <p class="section-desc text-xs">Optional per-item overrides. Leave blank to inherit from the event dates above.</p>
                </div>
                <div class="flex w-full min-w-0 flex-wrap items-center gap-2 sm:w-auto">
                    <button v-if="itemRows.length" type="button" class="btn-primary text-sm"
                            :disabled="bulkSavingItemWindows || savingId !== null || !filteredItemRows.length"
                            @click="saveAllRows">
                        {{ bulkSavingItemWindows ? 'Saving…' : `Save shown items (${filteredItemRows.length})` }}
                    </button>
                    <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/competition`" class="btn-secondary text-sm">Competition hub →</Link>
                </div>
            </div>

            <div class="flex flex-wrap items-end gap-3">
                <FormField label="Find an item" class-extra="mb-0 flex-1 min-w-[12rem]"><input v-model="itemSearch" type="search" class="field" placeholder="Item name or code"></FormField>
                <FormField v-if="event.event_type === 'sports'" label="Age category" class-extra="mb-0 min-w-[12rem]"><SearchableSelect v-model="ageFilter" :options="ageOptions" :all-option="true" all-label="All age categories" /></FormField>
                <p class="text-xs text-slate-500">{{ filteredItemRows.length }} of {{ itemRows.length }} items. Bulk fill and save apply to shown items.</p>
            </div>
            <div v-if="itemRows.length" class="rounded-xl border border-amber-100 bg-amber-50/60 p-3 space-y-2">
                <p class="text-xs font-semibold text-amber-800">Bulk fill — set a date once and apply it to the shown items below</p>
                <div class="flex flex-wrap items-end gap-2">
                    <label class="text-xs text-slate-600">
                        Reg opens
                        <input v-model="bulkDates.reg_start" type="date" class="field text-xs block mt-1">
                    </label>
                    <label class="text-xs text-slate-600">
                        Reg closes
                        <input v-model="bulkDates.reg_end" type="date" class="field text-xs block mt-1">
                    </label>
                    <label class="text-xs text-slate-600">
                        Competition start
                        <input v-model="bulkDates.competition_start" type="date" class="field text-xs block mt-1">
                    </label>
                    <label class="text-xs text-slate-600">
                        Competition end
                        <input v-model="bulkDates.competition_end" type="date" class="field text-xs block mt-1">
                    </label>
                    <button type="button" class="btn-secondary text-xs py-1.5 px-2.5" @click="applyBulkDates('empty')">
                        Fill empty rows
                    </button>
                    <button type="button" class="btn-secondary text-xs py-1.5 px-2.5" @click="applyBulkDates('all')">
                        Overwrite shown items
                    </button>
                </div>
                <p class="text-[11px] text-amber-700">Only fields you fill in above get applied — leave a field blank to skip it. Remember to click "Save shown items" after applying.</p>
            </div>

            <p v-if="itemRows.length" class="text-xs text-slate-500 sm:hidden">Swipe the table sideways to edit dates. The Save button stays visible.</p>
            <div v-if="itemRows.length" class="overflow-x-auto rounded-xl border border-slate-100" role="region" tabindex="0" aria-label="Item registration dates — scroll horizontally for date columns">
                <table class="data-table text-sm w-full" style="min-width: 1100px;">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600 min-w-[16rem]">Item</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600 min-w-[9.5rem] whitespace-nowrap">Reg opens</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600 min-w-[9.5rem] whitespace-nowrap">Reg closes</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600 min-w-[9.5rem] whitespace-nowrap">Competition start</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600 min-w-[9.5rem] whitespace-nowrap">Competition end</th>
                            <th class="px-3 py-2 sticky right-0 bg-slate-50 min-w-[4.5rem]"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="row in filteredItemRows" :key="row.id">
                            <td class="px-3 py-2 font-medium text-slate-900 whitespace-normal">
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span>{{ row.title }}</span>
                                    <span v-if="row.item_code" class="text-xs font-mono text-slate-400">[{{ row.item_code }}]</span>
                                </div>
                                <div v-if="itemMeta(row)" class="text-xs font-normal text-slate-500 mt-0.5">{{ itemMeta(row) }}</div>
                            </td>
                            <td class="px-3 py-2"><input v-model="row.reg_start" :aria-label="`${row.title ?? row.name} — registration opens`" type="date" class="field text-xs w-full min-w-[8.5rem]"></td>
                            <td class="px-3 py-2"><input v-model="row.reg_end" :aria-label="`${row.title ?? row.name} — registration closes`" type="date" class="field text-xs w-full min-w-[8.5rem]"></td>
                            <td class="px-3 py-2"><input v-model="row.competition_start" :aria-label="`${row.title ?? row.name} — competition start`" type="date" class="field text-xs w-full min-w-[8.5rem]"></td>
                            <td class="px-3 py-2"><input v-model="row.competition_end" :aria-label="`${row.title ?? row.name} — competition end`" type="date" class="field text-xs w-full min-w-[8.5rem]"></td>
                            <td class="px-3 py-2 text-right sticky right-0 bg-white">
                                <button type="button" class="btn-secondary text-xs py-1 px-2"
                                        :disabled="savingId !== null || savingHeadId !== null || bulkSavingItemWindows"
                                        @click="saveRow(row)">
                                    {{ savingId === row.id ? '…' : 'Save' }}
                                </button>
                            </td>
                        </tr>
                    <tr v-if="!filteredItemRows.length"><td colspan="6" class="p-5 text-center text-sm text-slate-500">No items match these filters.</td></tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-sm text-slate-500 italic">No items in this event yet — add items from the event catalog first.</p>
        </section>
    </div>
</template>

<script setup>
import { computed, inject, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import ValidationBanner from '@/Components/ui/ValidationBanner.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

import { mergeWindowRows, WINDOW_DATE_FIELDS } from '@/support/eventWindowSettings.js';
import { genderLabel } from '@/support/festItemEligibility.js';

const { registrationSettingsForm, saveRegistrationSettings, saveItemWindow, saveAllItemWindows, bulkSavingItemWindows, saveHeadWindow, sahodaya, event, itemHeads, ageGroupLabels, clusterRequireStudentVerification } = inject('eventSettings');

// event and itemHeads come from inject('eventSettings'), which Settings.vue provides
// as `{ ...toRefs(props), ...ctx }` — every prop-sourced key is a real Vue Ref, not
// the plain value. Template expressions auto-unwrap refs transparently, but plain
// script code does NOT get that treatment (see the identical bug fix in FeesTab.vue).
// `.value` is required here to access the actual items/heads array.
const eventVal = event.value ?? {};
const itemHeadsVal = itemHeads.value ?? [];

const savingId = ref(null);
const savingHeadId = ref(null);

const studentVerificationOptions = computed(() => [
    {
        value: 'inherit',
        label: `Use cluster default — ${clusterRequireStudentVerification.value ? 'verified students only' : 'unverified allowed'}`,
    },
    { value: 'required', label: 'Require verified students only' },
    { value: 'optional', label: 'Allow unverified students' },
]);

function toDateInput(value) {
    if (!value) return '';
    return String(value).slice(0, 10);
}

function mapItem(item) {
    return {
        id: item.id,
        title: item.title,
        item_code: item.item_code ?? null,
        category: item.category ?? null,
        category_label: item.category_label ?? null,
        class_group: item.class_group ?? null,
        age_group: item.age_group ?? null,
        gender: item.gender ?? null,
        head_id: item.head_id ?? item.head?.id ?? null,
        head_name: item.head?.name ?? null,
        reg_start: toDateInput(item.reg_start),
        reg_end: toDateInput(item.reg_end),
        competition_start: toDateInput(item.competition_start),
        competition_end: toDateInput(item.competition_end),
    };
}

function itemMeta(row) {
    const parts = [];
    const cat = (event.value?.event_type === 'sports' ? ageGroupLabels.value?.[row.age_group] : null) || row.category_label
        || (row.age_group && row.age_group !== 'open' ? String(row.age_group).toUpperCase() : null)
        || (row.class_group && row.class_group !== 'open' ? String(row.class_group).replace(/[_-]/g, ' ').toUpperCase() : null)
        || (row.category && row.category !== 'general' ? String(row.category).replace(/[_-]/g, ' ') : null);
    if (cat) parts.push(cat);

    const g = genderLabel(row.gender);
    if (g) parts.push(g);

    return parts.join(' · ');
}

const itemRows = ref((eventVal.items ?? []).map(mapItem));
let previousItems = itemRows.value.map(row => ({ ...row }));
const itemSearch = ref('');
const ageFilter = ref('');
const ageOptions = computed(() => [...new Set(itemRows.value.map(row => row.age_group).filter(Boolean))].map(value => ({ value, label: ageGroupLabels.value?.[value] ?? value.toUpperCase() })));
const filteredItemRows = computed(() => itemRows.value.filter(row =>
    (!ageFilter.value || row.age_group === ageFilter.value)
    && (!itemSearch.value.trim() || `${row.title} ${row.item_code ?? ''}`.toLowerCase().includes(itemSearch.value.trim().toLowerCase()))));

const headRows = ref(itemHeadsVal.map((head) => ({
    id: head.id,
    name: head.name,
    reg_start: toDateInput(head.reg_start),
    reg_end: toDateInput(head.reg_end),
    competition_start: toDateInput(head.competition_start),
    competition_end: toDateInput(head.competition_end),
})));

let previousHeads = headRows.value.map(row => ({ ...row }));

watch(() => event.value?.items, (items) => {
    const incoming = (items ?? []).map(mapItem);
    itemRows.value = mergeWindowRows(itemRows.value, previousItems, incoming);
    previousItems = incoming.map(row => ({ ...row }));
}, { deep: true });

watch(() => itemHeads.value, (heads) => {
    const incoming = (heads ?? []).map((head) => ({
        id: head.id,
        name: head.name,
        reg_start: toDateInput(head.reg_start),
        reg_end: toDateInput(head.reg_end),
        competition_start: toDateInput(head.competition_start),
        competition_end: toDateInput(head.competition_end),
    }));
    headRows.value = mergeWindowRows(headRows.value, previousHeads, incoming);
    previousHeads = incoming.map(row => ({ ...row }));
}, { deep: true });

async function saveRow(row) {
    savingId.value = row.id;
    try { await saveItemWindow(row.id, row); } finally { savingId.value = null; }
}

function saveAllRows() {
    saveAllItemWindows(filteredItemRows.value);
}

const bulkDates = ref({
    reg_start: '',
    reg_end: '',
    competition_start: '',
    competition_end: '',
});

const bulkDateFields = WINDOW_DATE_FIELDS;

function applyBulkDates(mode) {
    const fieldsToApply = bulkDateFields.filter((field) => bulkDates.value[field]);
    if (!fieldsToApply.length) return;

    filteredItemRows.value.forEach((row) => {
        fieldsToApply.forEach((field) => {
            if (mode === 'all' || !row[field]) {
                row[field] = bulkDates.value[field];
            }
        });
    });
}

async function saveHeadWindowRow(row) {
    savingHeadId.value = row.id;
    try { await saveHeadWindow(row.id, { ...row, apply_to_items: true }); } finally { savingHeadId.value = null; }
}
</script>
