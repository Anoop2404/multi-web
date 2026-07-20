<template>
    <SahodayaEventsLayout :title="`${event.title} — Attendance`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Attendance`" eyebrow="Attendance"
                    description="Mark participant attendance by item." />

        <SportsSetupSubNav v-if="event.event_type === 'sports'"
                           :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="attendance" class="mb-4" />
        <div class="flex flex-wrap gap-2 mb-4 border-b border-gray-200 pb-3">
            <button type="button" @click="itemFilter = ''"
                    :class="itemFilter === '' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 border-gray-200 hover:bg-gray-50'"
                    class="px-3 py-1.5 rounded-lg text-sm font-semibold border">
                All items
            </button>
            <button v-for="item in event.items" :key="item.id" type="button" @click="itemFilter = item.id"
                    :class="itemFilter === item.id ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 border-gray-200 hover:bg-gray-50'"
                    class="px-3 py-1.5 rounded-lg text-sm font-semibold border">
                {{ item.title }}
            </button>
        </div>

        <div class="flex flex-wrap gap-3 mb-4">
            <button v-if="itemFilter" type="button" @click="bulkMark('present')"
                    class="btn-primary px-3 py-2 rounded-lg text-sm">Mark all present</button>
            <button v-if="itemFilter" type="button" @click="bulkMark('absent')"
                    class="px-3 py-2 bg-red-600 text-white rounded-lg text-sm">Mark all absent</button>
            <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/attendance/import-template`" class="text-xs font-semibold text-indigo-600 self-center">CSV template</a>
            <input type="file" accept=".csv" class="text-xs" @change="onImportFile">
            <button type="button" class="btn-secondary text-xs" :disabled="!importFile" @click="submitImport">Import attendance</button>
        </div>

        <div class="flex flex-wrap gap-2 mb-4">
            <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/export/attendance`"
               class="btn-secondary text-xs">
                Download attendance report (XLSX) ↓
            </a>
            <a :href="attendanceSheetPdfHref"
               target="_blank" rel="noopener"
               class="btn-secondary text-xs">
                Print blank attendance sheet (PDF) ↗
            </a>
        </div>

        <div class="card card--flush">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left">
                    <tr>
                        <th class="p-3">Chest</th>
                        <th class="p-3">Participant</th>
                        <th class="p-3">School</th>
                        <th class="p-3">Item</th>
                        <th class="p-3">Attendance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in displayRows" :key="row.key" class="border-t">
                        <td class="p-3 font-mono text-xs">{{ row.chest_no ?? '—' }}</td>
                        <td class="p-3 font-medium">
                            {{ row.name }}
                            <span v-if="row.is_team" class="ml-1 inline-flex items-center rounded-full bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700 align-middle">
                                Team · {{ row.member_count }}
                            </span>
                        </td>
                        <td class="p-3 text-gray-500">{{ row.school }}</td>
                        <td class="p-3 text-gray-500">{{ row.item_title }}</td>
                        <td class="p-3">
                            <div class="inline-flex rounded-lg border border-gray-200 overflow-hidden text-xs font-semibold">
                                <button @click="mark(row.representative, 'present')"
                                        :class="row.status === 'present' ? 'bg-green-600 text-white' : 'bg-white text-green-700 hover:bg-green-50'"
                                        class="px-3 py-1.5">Present</button>
                                <button @click="mark(row.representative, 'absent')"
                                        :class="row.status === 'absent' ? 'bg-red-600 text-white' : 'bg-white text-red-600 hover:bg-red-50'"
                                        class="px-3 py-1.5 border-l border-gray-200">Absent</button>
                            </div>
                            <span v-if="!row.status" class="ml-2 text-gray-400 text-xs">Not marked</span>
                        </td>
                    </tr>
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
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, participants: Array, attendance: Object,
    activityLogs: { type: Array, default: () => [] },
});

const itemFilter = ref('');
const importFile = ref(null);
const importForm = useForm({ file: null });

const GROUP_PARTICIPANT_TYPES = ['team', 'group', 'pair', 'trio'];

const filteredParticipants = computed(() => {
    if (!itemFilter.value) return props.participants;
    return props.participants.filter(p => p.registration?.item_id == itemFilter.value);
});

// Team/group items: one row per squad (attendance applies to the whole
// team), not one row per member.
const displayRows = computed(() => {
    const rows = [];
    const seenGroups = new Set();

    for (const p of filteredParticipants.value) {
        const item = p.registration?.item;
        const isGroupItem = item && GROUP_PARTICIPANT_TYPES.includes(item.participant_type);

        if (isGroupItem && p.group_id) {
            const key = `${p.registration.item_id}-${p.group_id}`;
            if (seenGroups.has(key)) continue;
            seenGroups.add(key);

            const members = filteredParticipants.value.filter(
                (m) => m.group_id === p.group_id && m.registration?.item_id === p.registration.item_id
            );

            rows.push({
                key,
                is_team: true,
                member_count: members.length,
                name: p.group?.team_name || 'Team',
                chest_no: p.group?.chest_no,
                school: p.registration?.school?.name ?? '—',
                item_title: item.title,
                status: statusFor(p),
                representative: p,
            });
            continue;
        }

        rows.push({
            key: `p-${p.id}`,
            is_team: false,
            name: p.student?.name ?? p.teacher?.name,
            chest_no: p.chest_no,
            school: p.registration?.school?.name ?? '—',
            item_title: item?.title,
            status: statusFor(p),
            representative: p,
        });
    }

    return rows;
});

// Blank, printable sheet (chest no / name / school, no status filled in) for marking
// by hand at the venue — pre-built PDF report, just not linked from this page before.
// Scoped to the currently selected item when one is chosen, same as bulk-marking.
const attendanceSheetPdfHref = computed(() => {
    const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/reports/export/attendance-sheet`;
    return itemFilter.value ? `${base}?item_id=${itemFilter.value}` : base;
});

function attendanceKey(p) {
    return `${p.registration.item_id}-${p.id}`;
}

function statusFor(p) {
    return props.attendance?.[attendanceKey(p)]?.status ?? null;
}

function mark(participant, status) {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/attendance`, {
        participant_id: participant.id,
        item_id: participant.registration.item_id,
        status,
    }, { preserveScroll: true });
}

function bulkMark(status) {
    const ids = filteredParticipants.value.map(p => p.id);
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/attendance`, {
        bulk: true,
        item_id: itemFilter.value,
        participant_ids: ids,
        status,
    }, { preserveScroll: true });
}

function onImportFile(e) { importFile.value = e.target.files[0] ?? null; }

function submitImport() {
    importForm.file = importFile.value;
    importForm.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/attendance/import`, {
        forceFormData: true,
        preserveScroll: true,
    });
}
</script>
