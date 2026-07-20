<template>
    <SahodayaEventsLayout :title="`${event.title} — Attendance`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        
        <!-- Page Header & Action Controls -->
        <PageHeader :title="`${event.title} — Attendance`" eyebrow="Event Attendance"
                    description="Mark participant and team attendance by competition item.">
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" class="btn-secondary text-xs flex items-center gap-1" @click="showImportModal = true">
                        <span>📥 Import CSV</span>
                    </button>
                    <a :href="attendanceXlsxHref"
                       class="btn-secondary text-xs flex items-center gap-1">
                        <span>Download XLSX report ↓</span>
                    </a>
                    <a :href="attendanceSheetPdfHref" target="_blank" rel="noopener"
                       class="btn-primary text-xs flex items-center gap-1">
                        <span>Print attendance sheet (PDF) ↗</span>
                    </a>
                </div>
            </template>
        </PageHeader>

        <!-- Sub Navigation -->
        <SportsSetupSubNav v-if="event.event_type === 'sports'"
                           :sahodaya-id="sahodaya.id" :event-id="event.id"
                           :event="event" active="attendance" class="mb-4" />
        <EventSubNav v-else :sahodaya-id="sahodaya.id" :event-id="event.id" active="attendance" class="mb-4" />

        <!-- Main Card Section -->
        <div class="card !p-4 space-y-4">
            
            <!-- Item Filter Chips -->
            <div class="space-y-2 border-b border-slate-100 pb-3">
                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Competition Item</p>
                <div class="flex flex-wrap gap-1.5 text-xs">
                    <button v-for="item in event.items" :key="item.id" type="button" @click="itemFilter = item.id"
                            :class="itemFilter === item.id
                                ? 'bg-slate-900 text-white font-bold shadow-sm'
                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200 font-semibold'"
                            class="px-3.5 py-1.5 rounded-full transition whitespace-nowrap">
                        {{ item.title }}
                    </button>
                </div>
            </div>

            <!-- Toolbar: Bulk Actions & Search -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <!-- Bulk Actions -->
                <div class="flex items-center gap-2">
                    <button type="button" @click="bulkMark('present')"
                            class="btn-primary !bg-emerald-600 hover:!bg-emerald-500 text-xs flex items-center gap-1 shadow-sm">
                        <span>Mark all present ✓</span>
                    </button>
                    <button type="button" @click="bulkMark('absent')"
                            class="btn-secondary text-xs !text-rose-700 hover:!bg-rose-50 flex items-center gap-1">
                        <span>Mark all absent ✕</span>
                    </button>
                </div>

                <!-- Search Input -->
                <div class="flex items-center gap-2 flex-1 min-w-[14rem] max-w-xs ml-auto">
                    <input v-model="searchQuery" type="search" placeholder="Search participant, chest #, or school…"
                           class="field text-xs !py-1.5 flex-1" autocomplete="off">
                    <span class="text-xs text-slate-500 whitespace-nowrap tabular-nums shrink-0 font-medium">
                        {{ displayRows.length }} entries
                    </span>
                </div>
            </div>

            <!-- Table Registry -->
            <div class="rounded-xl border border-slate-200 overflow-hidden bg-white">
                <table class="w-full text-xs text-left">
                    <thead class="bg-slate-50 text-slate-500 border-b border-slate-200 uppercase tracking-wider text-[10px] font-bold">
                        <tr>
                            <th class="p-3.5 w-12 text-center">Sl No</th>
                            <th class="p-3.5 w-20 text-center">Chest #</th>
                            <th class="p-3.5">Participant / Team</th>
                            <th class="p-3.5">School</th>
                            <th class="p-3.5">Item</th>
                            <th class="p-3.5 text-right">Attendance Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(row, idx) in displayRows" :key="row.key" class="hover:bg-slate-50/70 transition items-center">
                            <td class="p-3.5 text-center text-slate-500">
                                {{ idx + 1 }}
                            </td>
                            <td class="p-3.5 font-mono text-center font-bold text-slate-700">
                                {{ row.chest_no ?? '—' }}
                            </td>
                            <td class="p-3.5 font-bold text-slate-900">
                                <div class="flex items-center gap-2">
                                    <span>{{ row.name }}</span>
                                    <span v-if="row.is_team" class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-[10px] font-bold text-indigo-700 border border-indigo-100">
                                        Team · {{ row.member_count }} members
                                    </span>
                                </div>
                            </td>
                            <td class="p-3.5 text-slate-600">
                                {{ (row.school || '').toUpperCase() }}
                            </td>
                            <td class="p-3.5 font-medium text-slate-700">
                                {{ row.item_title }}
                            </td>
                            <td class="p-3.5 text-right">
                                <div class="inline-flex items-center gap-2">
                                    <div class="inline-flex rounded-lg border border-slate-200 p-0.5 bg-slate-50">
                                        <button type="button" @click="mark(row.representative, 'present')"
                                                :class="row.status === 'present'
                                                    ? 'bg-emerald-600 text-white font-bold shadow-sm'
                                                    : 'text-slate-600 hover:text-emerald-700 hover:bg-white font-semibold'"
                                                class="px-3 py-1 rounded-md text-xs transition">
                                            Present ✓
                                        </button>
                                        <button type="button" @click="mark(row.representative, 'absent')"
                                                :class="row.status === 'absent'
                                                    ? 'bg-rose-600 text-white font-bold shadow-sm'
                                                    : 'text-slate-600 hover:text-rose-700 hover:bg-white font-semibold'"
                                                class="px-3 py-1 rounded-md text-xs transition">
                                            Absent ✕
                                        </button>
                                    </div>
                                    <span v-if="!row.status" class="text-[11px] text-slate-400 font-medium whitespace-nowrap">
                                        Not marked
                                    </span>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="!displayRows.length">
                            <td colspan="5" class="p-12 text-center text-slate-400">
                                <p class="text-sm font-medium">No participants match your filter or search.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- CSV IMPORT MODAL -->
        <div v-if="showImportModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 overflow-y-auto" @click.self="showImportModal = false">
            <div class="card w-full max-w-lg shadow-2xl space-y-4 my-auto bg-white border border-slate-200">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="section-title !mb-0">Import Attendance CSV</h3>
                    <button type="button" class="text-slate-400 hover:text-slate-600 text-xl font-bold leading-none" @click="showImportModal = false">×</button>
                </div>

                <div class="space-y-3 text-xs">
                    <p class="text-slate-600 leading-relaxed">
                        Upload a CSV file containing participant attendance entries.
                        You can download the template below to ensure correct columns.
                    </p>
                    <a :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/attendance/import-template`"
                       class="inline-block text-indigo-600 font-bold hover:underline">
                        📄 Download Attendance CSV Template →
                    </a>

                    <div class="pt-2">
                        <label class="form-label text-xs">Select CSV File</label>
                        <input type="file" accept=".csv" class="field text-xs !py-1.5" @change="onImportFile">
                    </div>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
                    <button type="button" class="btn-secondary text-xs" @click="showImportModal = false">Cancel</button>
                    <button type="button" class="btn-primary text-xs" :disabled="!importFile || importForm.processing" @click="submitImport">
                        {{ importForm.processing ? 'Importing...' : 'Upload & Import Attendance' }}
                    </button>
                </div>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import SportsSetupSubNav from '@/Components/sahodaya/SportsSetupSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, participants: Array, attendance: Object,
    activityLogs: { type: Array, default: () => [] },
});

// Attendance, chest numbers, and marks are always entered per competition
// item — there's no meaningful "mark everyone present across every item at
// once" action, so this page requires an item to be selected rather than
// offering an "All items" combined view. Default to the first item.
const itemFilter = ref(props.event.items?.[0]?.id ?? '');
const searchQuery = ref('');
const showImportModal = ref(false);
const importFile = ref(null);
const importForm = useForm({ file: null });

const GROUP_PARTICIPANT_TYPES = ['team', 'group', 'pair', 'trio'];

const filteredParticipants = computed(() => {
    let list = props.participants ?? [];
    if (itemFilter.value) {
        list = list.filter(p => p.registration?.item_id == itemFilter.value);
    }
    return list;
});

const displayRows = computed(() => {
    const rows = [];
    const seenGroups = new Set();
    const q = searchQuery.value.trim().toLowerCase();

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

            const rowData = {
                key,
                is_team: true,
                member_count: members.length,
                name: p.group?.team_name || 'Team',
                chest_no: p.group?.chest_no,
                school: p.registration?.school?.name ?? '—',
                item_title: item.title,
                status: statusFor(p),
                representative: p,
            };

            if (q) {
                const match = (rowData.name ?? '').toLowerCase().includes(q)
                    || (rowData.school ?? '').toLowerCase().includes(q)
                    || (String(rowData.chest_no ?? '')).toLowerCase().includes(q)
                    || (rowData.item_title ?? '').toLowerCase().includes(q);
                if (!match) continue;
            }

            rows.push(rowData);
            continue;
        }

        const rowData = {
            key: `p-${p.id}`,
            is_team: false,
            name: p.student?.name ?? p.teacher?.name ?? 'Participant',
            chest_no: p.chest_no,
            school: p.registration?.school?.name ?? '—',
            item_title: item?.title,
            status: statusFor(p),
            representative: p,
        };

        if (q) {
            const match = (rowData.name ?? '').toLowerCase().includes(q)
                || (rowData.school ?? '').toLowerCase().includes(q)
                || (String(rowData.chest_no ?? '')).toLowerCase().includes(q)
                || (rowData.item_title ?? '').toLowerCase().includes(q);
            if (!match) continue;
        }

        rows.push(rowData);
    }

    return rows;
});

const attendanceXlsxHref = computed(() => {
    const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/export/attendance`;
    return itemFilter.value ? `${base}?item_id=${itemFilter.value}` : base;
});

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
        onSuccess: () => {
            showImportModal.value = false;
        },
    });
}
</script>
