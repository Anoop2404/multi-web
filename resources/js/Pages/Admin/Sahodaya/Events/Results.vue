<template>
    <SahodayaEventsLayout :title="`${event.title} — Results`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="pageTitle" eyebrow="Scoring"
                    :description="pageDescription" />

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold">{{ publishTotals.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Items</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold text-emerald-700">{{ publishTotals.published }}</p>
                <p class="text-xs text-slate-500 mt-1">Published</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold text-amber-700">{{ publishTotals.pending }}</p>
                <p class="text-xs text-slate-500 mt-1">Not published</p>
            </div>
            <div class="card card--muted !py-4 text-center">
                <p class="text-2xl font-bold">{{ publishTotals.marks_ready }}/{{ publishTotals.items }}</p>
                <p class="text-xs text-slate-500 mt-1">Marks complete</p>
            </div>
        </div>

        <ReportHeadItemNavigator :groups="headItemGroups"
                                 :base-url="resultsBaseUrl"
                                 :selected-head-id="selectedHeadId"
                                 :selected-item-id="selectedItemId"
                                 :has-item-heads="hasItemHeads"
                                 :show-result-stats="true"
                                 :hint="hasItemHeads
                                     ? 'Pick an item head (Athletics, Chess, …), then a competition item to review marks and publish results.'
                                     : 'Select a competition item to review marks and publish results.'">

            <template #head-detail="{ head }">
                <FestHeadItemInfoPanel mode="head" :head="head" class="mb-4" />
            </template>

            <template #default="{ item, head }">
                <div class="space-y-4 mt-2">
                    <FestHeadItemInfoPanel mode="item" :head="head" :item="item" :summary="selectedItem" />

                    <div class="flex flex-wrap gap-2">
                        <Link :href="marksUrl(selectedItem ?? item)" class="btn-secondary text-sm">Edit marks</Link>
                        <button v-if="selectedItem && !selectedItem.results_published && selectedItem.marks_ready"
                                type="button"
                                class="btn-primary text-sm"
                                @click="publishItem(selectedItem.item_id)">
                            Publish item results
                        </button>
                        <button v-if="selectedItem?.results_published"
                                type="button"
                                class="px-4 py-2 border border-red-200 text-red-700 rounded-lg text-sm font-medium"
                                @click="unpublishItem(selectedItem.item_id)">
                            Unpublish
                        </button>
                    </div>

                    <div v-if="selectedItem && !selectedItem.marks_ready"
                         class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Enter marks for all participants before publishing this item.
                        <Link :href="marksUrl(selectedItem)" class="font-semibold underline ml-1">Go to mark entry →</Link>
                    </div>

                    <div class="card overflow-hidden p-0">
                        <div class="px-5 py-3 border-b border-slate-100 bg-slate-50/80">
                            <h3 class="text-sm font-semibold text-slate-800">Result marks</h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th class="pl-5">Participant</th>
                                        <th>School</th>
                                        <th>Chest</th>
                                        <th>Rank</th>
                                        <th>Grade</th>
                                        <th>Score</th>
                                        <th v-if="isSports">Time / distance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in itemResultRows" :key="row.participant_id">
                                        <td class="pl-5">
                                            <span class="font-medium">{{ row.name ?? '—' }}</span>
                                            <span v-if="row.reg_no" class="block text-xs text-slate-500 font-mono">{{ row.reg_no }}</span>
                                        </td>
                                        <td class="text-sm">{{ row.school ?? '—' }}</td>
                                        <td class="font-mono text-xs">{{ row.chest_no ?? '—' }}</td>
                                        <td>{{ row.position ?? '—' }}</td>
                                        <td>{{ row.grade ?? '—' }}</td>
                                        <td>{{ row.score ?? '—' }}</td>
                                        <td v-if="isSports">
                                            <span v-if="row.measurement">{{ row.measurement }} {{ row.measurement_unit }}</span>
                                            <span v-else>—</span>
                                        </td>
                                    </tr>
                                    <tr v-if="!itemResultRows.length">
                                        <td :colspan="isSports ? 7 : 6" class="p-6 text-center text-slate-400">No marks entered yet.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </template>
        </ReportHeadItemNavigator>

        <div v-if="!selectedHeadId && !selectedItemId" class="card overflow-hidden p-0 mb-6">
            <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/80 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="section-title">Item publish status</h3>
                    <p class="section-desc mt-0.5">Head-wise list — sort by published or pending. Click an item to review marks.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button v-for="opt in statusFilters" :key="opt.id" type="button"
                            class="text-xs px-3 py-1.5 rounded-full border transition-colors"
                            :class="statusFilter === opt.id
                                ? 'bg-indigo-600 text-white border-indigo-600'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-indigo-300'"
                            @click="statusFilter = opt.id">
                        {{ opt.label }}
                    </button>
                </div>
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Head</th>
                        <th>Item</th>
                        <th>Details</th>
                        <th>Competition</th>
                        <th>Marks</th>
                        <th>Status</th>
                        <th class="pr-5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(row, idx) in filteredSummaries" :key="row.item_id">
                        <tr v-if="shouldShowHeadDivider(row, filteredSummaries[idx - 1])" class="bg-slate-50/80">
                            <td colspan="7" class="px-5 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                                {{ row.head_name ?? 'Other items' }}
                                <span v-if="headPublishCounts[row.head_id ?? 'other']" class="font-normal normal-case ml-2 text-slate-500">
                                    {{ headPublishCounts[row.head_id ?? 'other'].published }}/{{ headPublishCounts[row.head_id ?? 'other'].total }} published
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="pl-5 text-xs text-slate-400">{{ row.head_name ?? '—' }}</td>
                            <td>
                                <Link :href="itemResultsUrl(row)" class="font-medium text-indigo-700 hover:underline">
                                    {{ row.title }}
                                </Link>
                                <p v-if="row.item_code" class="text-xs font-mono text-slate-400 mt-0.5">{{ row.item_code }}</p>
                            </td>
                            <td class="text-xs text-slate-600">
                                <span v-if="row.age_group">{{ row.age_group }}</span>
                                <span v-if="row.class_group && row.class_group !== 'open'"> · {{ row.class_group }}</span>
                                <span v-if="row.sport_discipline"> · {{ row.sport_discipline }}</span>
                                <span v-if="!row.age_group && !row.sport_discipline">—</span>
                            </td>
                            <td class="text-xs text-slate-600">{{ formatWindow(row) }}</td>
                            <td class="text-sm">
                                <span :class="row.marks_ready ? 'text-emerald-700' : 'text-amber-700'">
                                    {{ row.marks_entered }}/{{ row.performers }}
                                </span>
                            </td>
                            <td>
                                <span class="status-pill text-xs"
                                      :class="row.results_published ? 'status-pill--published' : (row.marks_ready ? 'status-pill--open' : 'status-pill--draft')">
                                    {{ row.results_published ? 'Published' : (row.marks_ready ? 'Ready' : 'Marks pending') }}
                                </span>
                            </td>
                            <td class="pr-5 text-right">
                                <div class="flex flex-wrap justify-end gap-2">
                                    <Link :href="marksUrl(row)" class="btn-secondary text-xs !min-h-0 px-2 py-1.5">Marks</Link>
                                    <button v-if="!row.results_published && row.marks_ready"
                                            type="button"
                                            class="btn-primary text-xs !min-h-0 px-2 py-1.5"
                                            @click="publishItem(row.item_id)">
                                        Publish
                                    </button>
                                    <button v-if="row.results_published"
                                            type="button"
                                            class="text-xs px-2 py-1.5 rounded-lg border border-red-200 text-red-700 hover:bg-red-50"
                                            @click="unpublishItem(row.item_id)">
                                        Unpublish
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="!filteredSummaries.length">
                        <td colspan="7" class="p-6 text-center text-slate-400">No items match the selected filters.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card mb-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                    <h3 class="section-title">Event-wide release</h3>
                    <p class="section-desc mt-0.5">Publish all results on the public portal and mark the event completed.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button @click="confirmPublish" class="btn-primary">Publish all results</button>
                    <button v-if="event.results_published" @click="unpublishEvent"
                            class="px-4 py-2 border border-red-200 text-red-700 rounded-lg text-sm font-medium">
                        Unpublish event
                    </button>
                </div>
            </div>
            <p v-if="event.results_published" class="text-sm text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-lg px-4 py-3">
                Event results are published on the public portal.
            </p>
            <p v-else-if="publishTotals.published > 0" class="text-sm text-indigo-800 bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3">
                {{ publishTotals.published }} item(s) published individually. Schools see those items; use “Publish all” for full portal release.
            </p>
        </div>

        <div class="flex flex-wrap justify-between gap-3 mb-4">
            <div class="flex flex-wrap gap-2 items-center">
                <form @submit.prevent="promote" class="flex flex-wrap gap-2 items-center">
                    <select v-model="promoteForm.next_event_id" class="field" required>
                        <option value="">Promote winners to…</option>
                        <option v-for="e in nextEvents" :key="e.id" :value="e.id">
                            {{ e.title }} ({{ levelLabels[e.level_round] ?? e.level_round }}){{ e.suggested ? ' ★' : '' }}
                        </option>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm">Promote qualifiers</button>
                </form>
                <button v-if="suggestedNextId"
                        @click="promoteAuto"
                        class="px-4 py-2 bg-amber-50 border border-amber-300 text-amber-800 rounded-lg text-sm font-semibold">
                    Promote to next level ★
                </button>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-4 mb-4">
            <ol class="card-list">
                <li v-for="row in scoreboard" :key="row.school_id" class="p-4 flex justify-between">
                    <span><strong>#{{ row.rank }}</strong> {{ row.school_name }}</span>
                    <span class="font-mono">{{ row.total_points }} pts</span>
                </li>
                <li v-if="!scoreboard.length" class="p-4 text-gray-400 text-sm">No results yet</li>
            </ol>

            <div class="card">
                <h3 class="font-semibold text-sm mb-2">Promoted participants</h3>
                <ul class="text-sm divide-y max-h-64 overflow-y-auto">
                    <li v-for="q in qualifications" :key="q.id" class="py-2 flex justify-between gap-2 items-start">
                        <div>
                            {{ participantLabel(q) }} — {{ q.item?.title }}
                            <span class="text-gray-400 text-xs block">→ {{ q.next_level_event?.title }}</span>
                        </div>
                        <button @click="revoke(q.id)" class="text-red-600 text-xs font-semibold shrink-0">Revoke</button>
                    </li>
                    <li v-if="!qualifications.length" class="py-2 text-gray-400">None yet</li>
                </ul>
            </div>
        </div>

        <div class="card">
            <h3 class="font-semibold text-sm mb-3">Quick exports</h3>
            <p class="text-xs text-gray-500 mb-2">
                <Link :href="`/sahodaya-admin/${sahodaya.id}/events/${event.id}/reports`" class="text-indigo-600 font-semibold">Open full Reports hub →</Link>
                (judge sheets, attendance PDFs, clash reports, admit cards, and more)
            </p>
            <div class="flex flex-wrap gap-2">
                <a v-for="link in exportLinks" :key="link.type" :href="link.href"
                   class="px-3 py-2 border rounded-lg text-sm hover:border-indigo-300">{{ link.label }} ↓</a>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />

        <div v-if="publishModalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="publishModalOpen = false">
            <div class="bg-white rounded-xl p-6 w-full max-w-md shadow-2xl space-y-4">
                <h3 class="font-semibold text-gray-900">Publish all results?</h3>
                <p class="text-sm text-gray-600">
                    Schools and the public portal will see all published results for <strong>{{ event.title }}</strong>.
                    You can unpublish later if needed.
                </p>
                <div class="flex gap-2 justify-end">
                    <button type="button" class="btn-ghost text-sm" @click="publishModalOpen = false">Cancel</button>
                    <button type="button" class="btn-primary text-sm" @click="publishEvent">Publish now</button>
                </div>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import ReportHeadItemNavigator from '@/Components/reports/ReportHeadItemNavigator.vue';
import FestHeadItemInfoPanel from '@/Components/fest/FestHeadItemInfoPanel.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, scoreboard: Array, qualifications: Array, nextEvents: Array,
    suggestedNextId: Number, levelLabels: Object,
    activityLogs: { type: Array, default: () => [] },
    headItemGroups: { type: Array, default: () => [] },
    hasItemHeads: { type: Boolean, default: false },
    itemSummaries: { type: Array, default: () => [] },
    publishTotals: { type: Object, default: () => ({}) },
    filterHeadId: [String, Number],
    filterItemId: [String, Number],
    selectedHeadId: [String, Number],
    selectedItemId: [String, Number],
    selectedItem: { type: Object, default: null },
    itemResultRows: { type: Array, default: () => [] },
    resultsBaseUrl: String,
    marksBaseUrl: String,
});

const promoteForm = useForm({ next_event_id: props.suggestedNextId ?? '' });
const publishModalOpen = ref(false);
const statusFilter = ref('all');

watch(() => props.suggestedNextId, (id) => {
    if (id && !promoteForm.next_event_id) promoteForm.next_event_id = id;
}, { immediate: true });

const isSports = computed(() => props.event?.event_type === 'sports');

const pageTitle = computed(() => {
    if (props.selectedItem) return `${props.event.title} — ${props.selectedItem.title}`;
    if (props.selectedHeadId) {
        const head = props.headItemGroups.find((g) =>
            String(g.head_id ?? 'other') === String(props.selectedHeadId));
        if (head) return `${props.event.title} — ${head.head_name}`;
    }
    return `${props.event.title} — Results`;
});

const pageDescription = computed(() => {
    if (props.selectedItem) {
        return `${props.selectedItem.head_name ? props.selectedItem.head_name + ' · ' : ''}Review marks and publish or unpublish results for this item.`;
    }
    if (props.selectedHeadId) {
        return 'Select a competition item to load marks and publish results.';
    }
    return 'Publish results item by item after mark entry, or release all results for the event.';
});

const statusFilters = [
    { id: 'all', label: 'All' },
    { id: 'published', label: 'Published' },
    { id: 'pending', label: 'Not published' },
    { id: 'marks_pending', label: 'Marks pending' },
];

const scopedSummaries = computed(() => props.itemSummaries ?? []);

const filteredSummaries = computed(() => {
    let rows = scopedSummaries.value;
    if (statusFilter.value === 'published') {
        rows = rows.filter((r) => r.results_published);
    } else if (statusFilter.value === 'pending') {
        rows = rows.filter((r) => !r.results_published);
    } else if (statusFilter.value === 'marks_pending') {
        rows = rows.filter((r) => !r.marks_ready);
    }
    return rows;
});

const headPublishCounts = computed(() => {
    const map = {};
    for (const row of props.itemSummaries ?? []) {
        const key = row.head_id ?? 'other';
        if (!map[key]) map[key] = { total: 0, published: 0 };
        map[key].total += 1;
        if (row.results_published) map[key].published += 1;
    }
    return map;
});

const exportLinks = computed(() => {
    const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/export`;
    return [
        { type: 'registrations', label: 'Registrations', href: `${base}/registrations` },
        { type: 'results', label: 'Results', href: `${base}/results` },
        { type: 'attendance', label: 'Attendance', href: `${base}/attendance` },
        { type: 'fees', label: 'Fees', href: `${base}/fees` },
    ];
});

function headParam(headId) {
    return headId == null ? 'other' : String(headId);
}

function itemResultsUrl(row) {
    const q = new URLSearchParams();
    if (row.head_id != null) q.set('head_id', headParam(row.head_id));
    else q.set('head_id', 'other');
    q.set('item_id', String(row.item_id));
    return `${props.resultsBaseUrl}?${q.toString()}`;
}

function marksUrl(row) {
    const itemId = row?.item_id ?? row?.id;
    const headId = row?.head_id;
    const q = new URLSearchParams();
    if (headId != null) q.set('head_id', headParam(headId));
    else q.set('head_id', 'other');
    q.set('item_id', String(itemId));
    return `${props.marksBaseUrl}?${q.toString()}`;
}

function formatWindow(row) {
    const start = row.competition_start ?? row.item_competition_start ?? row.head_competition_start;
    const end = row.competition_end ?? row.item_competition_end ?? row.head_competition_end;
    if (start && end) return `${formatShortDate(start)} – ${formatShortDate(end)}`;
    if (start) return `from ${formatShortDate(start)}`;
    if (end) return `until ${formatShortDate(end)}`;
    return '—';
}

function formatShortDate(iso) {
    const d = new Date(`${iso}T12:00:00`);
    return d.toLocaleDateString('en-IN', { day: 'numeric', month: 'short' });
}

function shouldShowHeadDivider(row, prev) {
    if (!prev) return true;
    return (row.head_id ?? null) !== (prev.head_id ?? null);
}

function participantLabel(q) {
    return q.participant?.student?.name
        ?? q.participant?.teacher?.name
        ?? 'Participant';
}

function confirmPublish() {
    publishModalOpen.value = true;
}

function publishEvent() {
    publishModalOpen.value = false;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/results/publish`, {}, { preserveScroll: true });
}

function unpublishEvent() {
    if (!confirm('Unpublish all event results? Schools will no longer see event-wide published results.')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/results/unpublish`, {}, { preserveScroll: true });
}

function publishItem(itemId) {
    if (!confirm('Publish results for this item? Schools with participants will see marks for this item.')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/results/items/${itemId}/publish`, {}, { preserveScroll: true });
}

function unpublishItem(itemId) {
    if (!confirm('Unpublish this item? Schools will no longer see these results unless the full event is published.')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/results/items/${itemId}/unpublish`, {}, { preserveScroll: true });
}

function promote() {
    promoteForm.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/results/promote`, {
        preserveScroll: true,
    });
}

function promoteAuto() {
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/results/promote-auto`, {}, { preserveScroll: true });
}

function revoke(id) {
    if (!confirm('Revoke this promotion and cancel the next-level registration?')) return;
    router.post(`/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/results/qualifications/${id}/revoke`, {}, { preserveScroll: true });
}
</script>
