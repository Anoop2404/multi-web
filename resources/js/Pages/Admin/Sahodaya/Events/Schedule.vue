<template>
    <SahodayaEventsLayout :title="`${event.title} — Schedule`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Schedule`" eyebrow="Schedule"
                    description="Build performance order, resolve clashes, and publish to the public portal." />

        <SportsSetupSubNav v-if="event.event_type === 'sports'" :sahodaya-id="sahodaya.id" :event-id="event.id" active="schedule" :event="event" />
        <div class="flex flex-wrap gap-2 mb-4 items-center">
            <button type="button" @click="autoGenerate"
                    class="btn-primary px-4 py-2 rounded-lg text-sm">Auto-order by chest no.</button>
            <button v-if="!event.schedule_published" type="button" @click="publishSchedule"
                    class="btn-primary px-4 py-2 rounded-lg text-sm">Publish schedule to public</button>
            <button v-else type="button" @click="unpublishSchedule"
                    class="px-4 py-2 border border-amber-500 text-amber-700 rounded-lg text-sm">Unpublish public schedule</button>
            <span v-if="event.schedule_published" class="text-xs text-emerald-700 font-semibold">Public schedule live</span>
            <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/schedule/import-template`"
               class="text-xs font-semibold text-indigo-600">Schedule CSV template</a>
        </div>

        <form @submit.prevent="importSchedule" class="card mb-4 max-w-xl flex flex-wrap gap-2 items-end p-4">
            <div class="flex-1 min-w-[12rem]">
                <label class="text-xs font-semibold text-gray-600">Import schedule CSV (reg_no or participant_id)</label>
                <input type="file" accept=".csv,text/csv" class="text-xs mt-1 block w-full" @change="onScheduleFile">
            </div>
            <button type="submit" class="btn-secondary text-sm" :disabled="!scheduleImportFile || scheduleImportForm.processing">Import</button>
        </form>

        <div v-if="clashCount > 0" class="mb-4 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold">{{ clashCount }} schedule clash(es)</p>
            <p class="text-xs mt-1 text-amber-800">Resolve overlapping participant times and stage double-bookings before publishing.</p>
            <ul v-if="clashes.length" class="mt-2 space-y-1 text-xs">
                <li v-for="(clash, idx) in clashes" :key="'p-'+idx">
                    Participant: {{ clash.student_name }} ({{ clash.school_name }}): {{ clash.event1 }} ↔ {{ clash.event2 }} — {{ clash.time }}
                </li>
            </ul>
            <ul v-if="stageConflicts.length" class="mt-2 space-y-1 text-xs">
                <li v-for="(clash, idx) in stageConflicts" :key="'s-'+idx">
                    Stage {{ clash.stage }}<span v-if="clash.venue"> ({{ clash.venue }})</span>: {{ clash.item1 }} ↔ {{ clash.item2 }} — {{ clash.time }}
                </li>
            </ul>
        </div>

        <section class="card mb-4">
            <h3 class="section-title">Add schedule slot</h3>
            <p class="section-desc mb-3">Assign a date/time and stage for a specific item or individual participant.</p>
            <form @submit.prevent="save" class="grid md:grid-cols-5 gap-2">
                <SearchableSelect
                    v-model="form.item_id"
                    :options="itemOptions"
                    placeholder="Item"
                    search-placeholder="Type item name to search…"
                    :all-option="false"
                />
                <SearchableSelect
                    v-model="form.participant_id"
                    :options="participantSelectOptions"
                    placeholder="Participant"
                    search-placeholder="Type name or chest no. to search…"
                    :all-option="true"
                    all-label="All item (block)"
                />
                <input v-model="form.scheduled_at" type="datetime-local" class="field">
                <SearchableSelect
                    v-if="stages.length"
                    v-model="form.stage_id"
                    :options="stageSelectOptions"
                    placeholder="Stage"
                    search-placeholder="Type stage name to search…"
                    :all-option="true"
                    all-label="Stage"
                />
                <input v-else v-model="form.stage" class="field" placeholder="Stage">
                <button class="btn-primary">Save slot</button>
            </form>
        </section>

        <div class="card card--flush overflow-x-auto">
            <table class="w-full min-w-[800px] text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="p-3">Order</th>
                        <th class="p-3">Time</th>
                        <th class="p-3">Stage</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Participant</th>
                        <th class="p-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in schedules" :key="row.id" class="border-t">
                        <td class="p-3">{{ row.sort_order }}</td>
                        <td class="p-3">{{ formatTime(row.scheduled_at) }}</td>
                        <td class="p-3">{{ row.fest_stage?.name || row.stage || '—' }}</td>
                        <td class="p-3">{{ row.item?.title }}</td>
                        <td class="p-3">{{ participantLabel(row) }}</td>
                        <td class="p-3 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-1">
                                <button type="button" class="text-xs text-slate-500 hover:text-slate-800 px-1"
                                        title="Move up" @click="moveSlot(row, 'up')">↑</button>
                                <button type="button" class="text-xs text-slate-500 hover:text-slate-800 px-1"
                                        title="Move down" @click="moveSlot(row, 'down')">↓</button>
                                <button type="button" class="text-xs text-red-600 font-semibold ml-2" @click="removeSlot(row.id)">Remove</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!schedules.length"><td colspan="6" class="p-6 text-center text-gray-400">No schedule yet</td></tr>
                </tbody>
            </table>
        </div>
            <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, participants: Array, schedules: Array, stages: Array,
    clashCount: { type: Number, default: 0 },
    clashes: { type: Array, default: () => [] },
    stageConflicts: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    classGroupLabels: { type: Object, default: () => ({}) },
    ageGroupLabels: { type: Object, default: () => ({}) },
});

function itemCategoryLabel(item) {
    if (item.age_group && item.age_group !== 'open') {
        return props.ageGroupLabels?.[item.age_group] ?? String(item.age_group).toUpperCase();
    }
    if (item.class_group && item.class_group !== 'open') {
        return props.classGroupLabels?.[item.class_group] ?? String(item.class_group).toUpperCase();
    }
    return null;
}

const itemOptions = computed(() => (props.event?.items ?? []).map(item => ({
    id: item.id,
    name: itemCategoryLabel(item) ? `${item.title} — ${itemCategoryLabel(item)}` : item.title,
})));

const form = useForm({ item_id: '', participant_id: '', scheduled_at: '', stage_id: '', stage: '' });
const scheduleImportFile = ref(null);
const scheduleImportForm = useForm({ file: null });

function onScheduleFile(e) {
    scheduleImportFile.value = e.target.files[0] ?? null;
}

const { confirm } = useConfirm();

function importSchedule() {
    scheduleImportForm.file = scheduleImportFile.value;
    scheduleImportForm.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/schedule/import`, {
        forceFormData: true,
        preserveScroll: true,
    });
}

async function removeSlot(id) {
    if (!(await confirm({ message: 'Remove this schedule slot?' }))) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/schedule/${id}`, { preserveScroll: true });
}

function moveSlot(row, direction) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/schedule/${row.id}/reorder`, {
        direction,
    }, { preserveScroll: true });
}

const participantsForItem = computed(() =>
    props.participants.filter(p => p.registration?.item_id == form.item_id)
);

const participantSelectOptions = computed(() => participantsForItem.value.map(p => ({
    value: p.id,
    label: `#${p.chest_no} ${p.student?.name ?? p.teacher?.name ?? ''}`,
})));

function formatTime(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString();
}

function participantLabel(row) {
    if (!row.participant_id) return '—';
    const p = row.participant;
    return `#${p?.chest_no ?? '?'} ${p?.student?.name ?? p?.teacher?.name ?? ''}`;
}

function stageOptionLabel(stage) {
    return stage.venue?.name ? `${stage.name} · ${stage.venue.name}` : stage.name;
}

const stageSelectOptions = computed(() => props.stages.map(s => ({
    value: s.id,
    label: stageOptionLabel(s),
})));

function save() {
    if (!form.item_id) return;
    form.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/schedule`, {
        preserveScroll: true,
        onSuccess: () => form.reset('participant_id', 'scheduled_at', 'stage', 'stage_id'),
    });
}

function autoGenerate() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/schedule/auto`, {}, { preserveScroll: true });
}

function publishSchedule() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/schedule/publish`, {}, { preserveScroll: true });
}

function unpublishSchedule() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/schedule/unpublish`, {}, { preserveScroll: true });
}
</script>

