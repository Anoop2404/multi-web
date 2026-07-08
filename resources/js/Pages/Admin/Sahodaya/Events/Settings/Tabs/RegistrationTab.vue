<template>
    <div class="space-y-6 max-w-5xl">
        <form @submit.prevent="saveRegistrationSettings" class="card space-y-4">
            <div>
                <h3 class="section-title">Event vs item registration</h3>
                <p class="section-desc">Require students to register for the event (₹300 student fee) before item entries.</p>
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
            <FormActions>
                <button type="submit" class="btn-primary" :disabled="registrationSettingsForm.processing">Save registration settings</button>
            </FormActions>
        </form>

        <section v-if="itemHeads.length" class="card space-y-4">
            <div>
                <h3 class="section-title">Per-head windows</h3>
                <p class="section-desc text-xs">Set registration and competition dates once per item head. Items inherit these dates unless overridden below.</p>
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
                            <td class="px-3 py-2"><input v-model="row.reg_start" type="date" class="field text-xs"></td>
                            <td class="px-3 py-2"><input v-model="row.reg_end" type="date" class="field text-xs"></td>
                            <td class="px-3 py-2"><input v-model="row.competition_start" type="date" class="field text-xs"></td>
                            <td class="px-3 py-2"><input v-model="row.competition_end" type="date" class="field text-xs"></td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" class="btn-secondary text-xs py-1 px-2"
                                        :disabled="savingHeadId === row.id"
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
                    <p class="section-desc text-xs">Optional per-item overrides. Leave blank to inherit from the item head above.</p>
                </div>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/competition`" class="btn-secondary text-sm shrink-0">Competition hub →</Link>
            </div>

            <div v-if="itemRows.length" class="overflow-x-auto rounded-xl border border-slate-100">
                <table class="data-table text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600">Item</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600">Head</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600">Reg opens</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600">Reg closes</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600">Competition start</th>
                            <th class="text-left px-3 py-2 text-xs font-semibold text-slate-600">Competition end</th>
                            <th class="px-3 py-2 w-20"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-50">
                        <tr v-for="row in itemRows" :key="row.id">
                            <td class="px-3 py-2 font-medium text-slate-900">{{ row.title }}</td>
                            <td class="px-3 py-2">
                                <select v-if="itemHeads.length" v-model="row.head_id" class="field text-xs min-w-[7rem]">
                                    <option :value="null">—</option>
                                    <option v-for="h in itemHeads" :key="h.id" :value="h.id">{{ h.name }}</option>
                                </select>
                                <span v-else class="text-slate-400 text-xs">{{ row.head_name || '—' }}</span>
                            </td>
                            <td class="px-3 py-2"><input v-model="row.reg_start" type="date" class="field text-xs"></td>
                            <td class="px-3 py-2"><input v-model="row.reg_end" type="date" class="field text-xs"></td>
                            <td class="px-3 py-2"><input v-model="row.competition_start" type="date" class="field text-xs"></td>
                            <td class="px-3 py-2"><input v-model="row.competition_end" type="date" class="field text-xs"></td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" class="btn-secondary text-xs py-1 px-2"
                                        :disabled="savingId === row.id"
                                        @click="saveRow(row)">
                                    {{ savingId === row.id ? '…' : 'Save' }}
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-sm text-slate-500 italic">No items in this event yet — add items from the event catalog first.</p>
        </section>
    </div>
</template>

<script setup>
import { inject, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';

const { registrationSettingsForm, saveRegistrationSettings, saveItemWindow, saveHeadWindow, sahodaya, event, itemHeads } = inject('eventSettings');

const savingId = ref(null);
const savingHeadId = ref(null);

function toDateInput(value) {
    if (!value) return '';
    return String(value).slice(0, 10);
}

function mapItem(item) {
    return {
        id: item.id,
        title: item.title,
        head_id: item.head_id ?? item.head?.id ?? null,
        head_name: item.head?.name ?? null,
        reg_start: toDateInput(item.reg_start),
        reg_end: toDateInput(item.reg_end),
        competition_start: toDateInput(item.competition_start),
        competition_end: toDateInput(item.competition_end),
    };
}

const itemRows = ref((event.items ?? []).map(mapItem));

const headRows = ref((itemHeads ?? []).map((head) => ({
    id: head.id,
    name: head.name,
    reg_start: toDateInput(head.reg_start),
    reg_end: toDateInput(head.reg_end),
    competition_start: toDateInput(head.competition_start),
    competition_end: toDateInput(head.competition_end),
})));

watch(() => event.items, (items) => {
    itemRows.value = (items ?? []).map(mapItem);
}, { deep: true });

watch(() => itemHeads, (heads) => {
    headRows.value = (heads ?? []).map((head) => ({
        id: head.id,
        name: head.name,
        reg_start: toDateInput(head.reg_start),
        reg_end: toDateInput(head.reg_end),
        competition_start: toDateInput(head.competition_start),
        competition_end: toDateInput(head.competition_end),
    }));
}, { deep: true });

function saveRow(row) {
    savingId.value = row.id;
    saveItemWindow(row.id, row);
    setTimeout(() => { savingId.value = null; }, 600);
}

function saveHeadWindowRow(row) {
    savingHeadId.value = row.id;
    saveHeadWindow(row.id, { ...row, apply_to_items: true });
    setTimeout(() => { savingHeadId.value = null; }, 600);
}
</script>
