<template>
    <SchoolAdminLayout :title="`${event.title} — Sports`" :school="school" :show-header-title="false">
        <PageHeader :title="event.title" eyebrow="Sports Meet"
                    :description="tabDescriptions[tab] ?? tabDescriptions.overview">
            <template #actions>
                <a v-if="['ongoing','registration_open','published'].includes(event.status)"
                   :href="`/school-admin/${school.id}/sports/fest-day/${event.id}`"
                   class="btn-secondary text-sm">Fest day →</a>
                <Link :href="`/school-admin/${school.id}/sports`" class="btn-secondary text-sm">Sports hub</Link>
            </template>
        </PageHeader>

        <nav class="flex flex-wrap gap-2 mb-6">
            <Link v-for="t in tabs" :key="t.key"
                  :href="tabHref(t.key)"
                  :class="tab === t.key ? 'subnav-link subnav-link--active' : 'subnav-link'">
                {{ t.label }}
            </Link>
        </nav>

        <!-- Overview -->
        <div v-if="tab === 'overview'" class="space-y-4 max-w-3xl">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="card card--muted text-center !py-4">
                    <p class="text-2xl font-bold text-indigo-700">{{ stats.items }}</p>
                    <p class="text-xs text-slate-500 mt-1">Items</p>
                </div>
                <div class="card card--muted text-center !py-4">
                    <p class="text-2xl font-bold">{{ stats.registrations }}</p>
                    <p class="text-xs text-slate-500 mt-1">Registrations</p>
                </div>
                <div class="card card--muted text-center !py-4">
                    <p class="text-2xl font-bold text-emerald-700">{{ stats.marks_entered }}</p>
                    <p class="text-xs text-slate-500 mt-1">Marks entered</p>
                </div>
                <div class="card card--muted text-center !py-4">
                    <p class="text-2xl font-bold text-amber-700">{{ stats.ranked }}</p>
                    <p class="text-xs text-slate-500 mt-1">Ranked</p>
                </div>
            </div>

            <!-- Inter-House Championship Standings -->
            <div v-if="houseStandings && houseStandings.length" class="card overflow-hidden p-0 border border-slate-200">
                <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex items-center justify-between">
                    <div>
                        <h3 class="font-semibold text-slate-900 text-sm">Inter-House Championship Standings</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Cumulative points tallied across all sports items in this meet</p>
                    </div>
                    <span class="text-xs font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full border border-indigo-200">
                        {{ houseStandings.length }} Houses
                    </span>
                </div>
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th class="w-16">Rank</th>
                                <th>House</th>
                                <th class="text-center w-28">Scored Athletes</th>
                                <th class="text-right w-28">Total Points</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="h in houseStandings" :key="h.house_id" class="hover:bg-slate-50/60">
                                <td class="font-bold">
                                    <span v-if="h.rank === 1" class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-100 text-amber-800 text-xs font-black">1</span>
                                    <span v-else-if="h.rank === 2" class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-slate-200 text-slate-700 text-xs font-black">2</span>
                                    <span v-else-if="h.rank === 3" class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-amber-700/10 text-amber-900 text-xs font-black">3</span>
                                    <span v-else class="text-slate-500 pl-2">#{{ h.rank }}</span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-2">
                                        <span class="inline-block w-3 h-3 rounded-full shrink-0 border border-black/10"
                                              :style="{ backgroundColor: h.color || '#94a3b8' }"></span>
                                        <span class="font-semibold text-slate-800">{{ h.house_name }}</span>
                                    </div>
                                </td>
                                <td class="text-center text-slate-600">{{ h.participants }}</td>
                                <td class="text-right font-black text-indigo-700">{{ h.total_points }} pts</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card text-sm space-y-2">
                <p><span class="text-slate-500">Status:</span> <span class="capitalize font-medium">{{ event.status }}</span></p>
                <p>
                    <span class="text-slate-500">Linked Sahodaya meet:</span>
                    <span v-if="event.parent_event_id" class="text-green-700 font-medium">{{ event.parent_event?.title ?? 'Linked' }}</span>
                    <span v-else class="text-amber-700">Not linked — required to submit winners</span>
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <Link :href="tabHref('marks')" class="btn-primary text-sm">Enter marks</Link>
                <Link :href="tabHref('winners')" class="btn-secondary text-sm">Submit winners</Link>
                <Link v-if="!event.parent_event_id" :href="tabHref('link')" class="btn-secondary text-sm">Link parent event</Link>
            </div>
        </div>

        <!-- Marks -->
        <SchoolRoundMarks v-else-if="tab === 'marks'" :school="school" :event="event" :registrations="registrations" :marks="marks"
                          :athletic-records="athleticRecords"
                          :save-url="`/school-admin/${school.id}/sports/my-event/${event.id}/marks`" />

        <!-- Results -->
        <div v-else-if="tab === 'results'">
            <EmptyState v-if="!Object.keys(resultsByItem).length" title="No ranked results yet"
                        description="Enter marks and positions on the Marks tab." icon="🏅" />
            <div v-else class="space-y-4">
                <section v-for="reg in resultItems" :key="reg.item.id" class="card overflow-hidden p-0">
                    <div class="border-b border-slate-100 px-5 py-3 bg-slate-50/80">
                        <h3 class="section-title">{{ reg.item?.title }}</h3>
                    </div>
                    <div v-if="resultsByItem[reg.item?.id]?.length" class="overflow-x-auto">
                        <table class="data-table">
                            <thead><tr><th>Place</th><th>Athlete</th><th>House / Class</th><th>Measurement</th><th>Points</th></tr></thead>
                            <tbody>
                                <tr v-for="(row, idx) in resultsByItem[reg.item.id]" :key="idx">
                                    <td class="font-semibold">
                                        <span v-if="row.position === 1" class="badge badge--success">1st</span>
                                        <span v-else-if="row.position === 2" class="badge badge--info">2nd</span>
                                        <span v-else-if="row.position === 3" class="badge badge--warning">3rd</span>
                                        <span v-else class="text-slate-600 font-medium">{{ row.position }}th</span>
                                    </td>
                                    <td>
                                        <span class="font-medium text-slate-800">{{ row.name }}</span>
                                    </td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <span v-if="row.house"
                                                  class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs font-semibold"
                                                  :style="{
                                                      backgroundColor: row.house.color ? `${row.house.color}15` : '#f1f5f9',
                                                      color: row.house.color || '#334155'
                                                  }">
                                                <span class="w-2 h-2 rounded-full shrink-0"
                                                      :style="{ backgroundColor: row.house.color || '#64748b' }"></span>
                                                {{ row.house.name }}
                                            </span>
                                            <span v-if="row.class" class="text-xs text-slate-500">
                                                Class {{ row.class }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="font-mono text-slate-700">{{ row.measurement || '—' }}</td>
                                    <td class="font-semibold text-indigo-700">{{ row.score ?? 0 }} pts</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="p-4 text-sm text-slate-400">No ranks for this item yet.</p>
                </section>
            </div>
        </div>

        <!-- Records -->
        <div v-else-if="tab === 'records'" class="space-y-6">
            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-4 text-sm text-slate-800 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-semibold text-slate-900">
                        Record Tracking:
                        <span :class="event.record_tracking_enabled ? 'text-emerald-700' : 'text-slate-500'">
                            {{ event.record_tracking_enabled ? 'ENABLED' : 'DISABLED' }}
                        </span>
                    </p>
                    <p class="text-xs text-slate-600 mt-0.5">
                        When enabled, marks that surpass standing records will automatically flag as New Records during evaluation.
                    </p>
                </div>
                <button type="button" class="btn-secondary text-xs !py-1.5" @click="toggleRecordTracking">
                    {{ event.record_tracking_enabled ? 'Disable tracking' : 'Enable record tracking' }}
                </button>
            </div>

            <div class="grid lg:grid-cols-2 gap-6">
                <!-- Standing Records & Manual Entry Form -->
                <div class="space-y-4">
                    <div class="card p-4 space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-sm text-slate-900">Add / Update Standing Record</h3>
                            <span class="text-xs text-slate-500">Record previous meet or historical results</span>
                        </div>
                        <form @submit.prevent="saveRecord" class="grid sm:grid-cols-2 gap-3 text-xs">
                            <div class="sm:col-span-2">
                                <label class="label text-xs">Sports Item</label>
                                <SearchableSelect
                                    v-model="recordForm.item_id"
                                    :options="sportsItemOptions"
                                    placeholder="Select sports item"
                                    search-placeholder="Search item…"
                                    :all-option="false"
                                />
                            </div>
                            <div>
                                <label class="label text-xs">Class Group</label>
                                <SearchableSelect
                                    v-model="recordForm.class_group"
                                    :options="classGroupOptions"
                                    placeholder="Class group"
                                    :all-option="false"
                                />
                            </div>
                            <div>
                                <label class="label text-xs">Gender</label>
                                <SearchableSelect
                                    v-model="recordForm.gender"
                                    :options="[{ value: 'male', label: 'Boys / Male' }, { value: 'female', label: 'Girls / Female' }, { value: 'open', label: 'Open' }]"
                                    placeholder="Gender"
                                    :all-option="false"
                                />
                            </div>
                            <div>
                                <label class="label text-xs">Type / Direction</label>
                                <SearchableSelect
                                    v-model="recordForm.record_direction"
                                    :options="[{ value: 'lower_better', label: 'Time (Faster wins)' }, { value: 'higher_better', label: 'Distance (Longer wins)' }]"
                                    :all-option="false"
                                />
                            </div>
                            <div>
                                <label class="label text-xs">Record Value</label>
                                <input v-model="recordForm.record_value" class="field text-xs" :placeholder="recordForm.record_direction === 'lower_better' ? 'e.g. 11.45 or 1:02.50' : 'e.g. 5.45'" required>
                            </div>
                            <div>
                                <label class="label text-xs">Unit</label>
                                <input v-model="recordForm.record_unit" class="field text-xs" placeholder="s, m, cm">
                            </div>
                            <div>
                                <label class="label text-xs">Record Date / Year</label>
                                <input v-model="recordForm.record_date" type="date" class="field text-xs">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="label text-xs">Previous Holder Name</label>
                                <input v-model="recordForm.holder_name" class="field text-xs" placeholder="Student / athlete name (optional)">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="label text-xs">Notes / Previous Meet Details</label>
                                <input v-model="recordForm.notes" class="field text-xs" placeholder="e.g. 2024 Sports Meet · Red House · 10.98s">
                            </div>
                            <button type="submit" class="btn-primary text-xs sm:col-span-2 py-2" :disabled="recordForm.processing">
                                {{ recordForm.processing ? 'Saving record…' : 'Save standing record' }}
                            </button>
                        </form>
                    </div>

                    <!-- Standing Records List -->
                    <div class="card p-0 overflow-hidden">
                        <div class="px-4 py-3 bg-slate-50 border-b flex items-center justify-between">
                            <h3 class="font-semibold text-xs text-slate-900 uppercase tracking-wider">Standing Meet Records ({{ athleticRecords.length }})</h3>
                        </div>
                        <ul v-if="athleticRecords.length" class="divide-y text-xs">
                            <li v-for="r in athleticRecords" :key="r.id" class="p-3.5 flex justify-between items-start gap-2 hover:bg-slate-50/50">
                                <div>
                                    <p class="font-semibold text-slate-800 text-sm">{{ r.item?.title }}</p>
                                    <div class="flex items-center gap-2 mt-1 text-slate-600 flex-wrap">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 font-mono font-bold text-xs border border-indigo-100">
                                            {{ r.record_value }} {{ r.record_unit }}
                                        </span>
                                        <span class="capitalize">{{ r.gender }}</span>
                                        <span>·</span>
                                        <span>{{ classGroups[r.class_group] || r.class_group }}</span>
                                    </div>
                                    <p v-if="r.holder_name" class="text-slate-700 mt-1 font-medium">
                                        Holder: {{ r.holder_name }}
                                    </p>
                                    <p v-if="r.notes" class="text-slate-500 mt-0.5 text-[11px]">
                                        {{ r.notes }}
                                    </p>
                                </div>
                                <button type="button" @click="removeRecord(r.id)" class="text-rose-600 hover:text-rose-800 font-medium text-xs shrink-0">
                                    Remove
                                </button>
                            </li>
                        </ul>
                        <div v-else class="p-8 text-center text-slate-400 text-xs">
                            No standing records added yet. Use the form above to add previous meet records.
                        </div>
                    </div>
                </div>

                <!-- Record Breaks in this meet -->
                <div>
                    <div class="card p-0 overflow-hidden">
                        <div class="px-4 py-3 bg-slate-50 border-b">
                            <h3 class="font-semibold text-xs text-slate-900 uppercase tracking-wider">Record Breaks (This Meet)</h3>
                        </div>
                        <ul v-if="recordBreaks.length" class="divide-y text-xs">
                            <li v-for="b in recordBreaks" :key="b.id" class="p-3.5 space-y-1 hover:bg-slate-50/50">
                                <div class="flex items-center justify-between">
                                    <span class="font-semibold text-slate-800">{{ b.item?.title }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                        🏆 {{ b.prize_label || 'New Record' }}
                                    </span>
                                </div>
                                <p class="text-slate-700">
                                    <strong>{{ b.participant?.student?.name }}</strong>
                                    <span v-if="b.participant?.student?.school_house"> ({{ b.participant?.student?.school_house.name }})</span>
                                    surpassed previous record:
                                    <span class="line-through text-slate-400">{{ b.previous_value }}</span>
                                    → <strong class="text-emerald-700 font-mono">{{ b.new_value }} {{ b.record_unit }}</strong>
                                </p>
                                <p class="text-[10px] text-slate-400 mt-1">{{ b.broken_at }}</p>
                            </li>
                        </ul>
                        <div v-else class="p-8 text-center text-slate-400 text-xs">
                            No records broken in this meet yet.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Link parent -->
        <div v-else-if="tab === 'link'" class="max-w-lg">
            <div v-if="event.parent_event_id" class="notice-banner notice-banner--success text-sm mb-4">
                Linked to <strong>{{ event.parent_event?.title }}</strong>.
            </div>
            <form v-else-if="parentEvents?.length" @submit.prevent="linkParent" class="card space-y-3">
                <h3 class="font-semibold">Link to Sahodaya sports meet</h3>
                <p class="text-xs text-slate-500">Required before submitting school-round winners to the cluster meet.</p>
                <SearchableSelect v-model="parentEventId" :options="parentEventOptions"
                                  :all-option="true" all-label="Select Sahodaya event…" :required="true" />
                <button class="btn-primary text-sm">Link parent event</button>
            </form>
            <EmptyState v-else title="No Sahodaya sports events" description="Ask Sahodaya to publish a cluster sports meet first." icon="🔗" />
        </div>

        <!-- Winners -->
        <div v-else-if="tab === 'winners'" class="max-w-2xl">
            <div class="notice-banner notice-banner--info text-sm mb-4">
                Submit top performers from this school round to the linked Sahodaya meet.
                <span v-if="!event.parent_event_id" class="block mt-2 text-amber-800 font-medium">
                    Link a parent event first (Link tab).
                </span>
            </div>
            <Link :href="`/school-admin/${school.id}/sports/submit-winners`" class="btn-primary text-sm">
                Open submit winners →
            </Link>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import SchoolRoundMarks from '@/Components/events/SchoolRoundMarks.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    school: Object,
    tab: { type: String, default: 'overview' },
    event: Object,
    parentEvents: { type: Array, default: () => [] },
    registrations: { type: Array, default: () => [] },
    marks: { type: Object, default: () => ({}) },
    resultsByItem: { type: Object, default: () => ({}) },
    houseStandings: { type: Array, default: () => [] },
    athleticRecords: { type: Array, default: () => [] },
    recordBreaks: { type: Array, default: () => [] },
    classGroups: { type: Object, default: () => ({}) },
    ageGroupLabels: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
});

const { confirm } = useConfirm();

const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'marks', label: 'Marks' },
    { key: 'results', label: 'Results' },
    { key: 'records', label: 'Records' },
    { key: 'link', label: 'Link parent' },
    { key: 'winners', label: 'Winners' },
];

const tabDescriptions = {
    overview: 'School-round sports day — registrations, marks, and winner promotion.',
    marks: 'Enter times, distances, and ranks for your athletes.',
    results: 'Ranked results by event item.',
    records: 'School & meet athletic records — standing records, previous records, and record breaks.',
    link: 'Connect this school meet to the Sahodaya cluster event.',
    winners: 'Promote school-round winners to Sahodaya registration.',
};

const parentEventId = ref('');
const parentEventOptions = computed(() => (props.parentEvents ?? []).map((p) => ({
    value: p.id,
    label: `${p.title} (${p.level_round})`,
})));
const resultItems = computed(() => [...new Map(props.registrations.map(reg => [reg.item?.id, reg])).values()].filter(reg => reg.item));

const sportsItems = computed(() => (props.event.items || []).filter(i => i.category === 'sports' || i.sport_discipline || ['time', 'distance'].includes(i.result_method)));

const sportsItemOptions = computed(() => sportsItems.value.map(i => ({
    value: i.id,
    label: i.class_group && i.class_group !== 'open' ? `${i.title} (${props.classGroups?.[i.class_group] || i.class_group})` : i.title,
})));

const classGroupOptions = computed(() => Object.entries(props.classGroups || {}).map(([value, label]) => ({ value, label })));

const recordForm = useForm({
    item_id: '',
    class_group: 'open',
    gender: 'open',
    record_direction: 'lower_better',
    record_value: '',
    record_unit: 's',
    holder_name: '',
    notes: '',
    record_date: '',
});

function tabHref(key) {
    return `/school-admin/${props.school.id}/sports/my-event/${props.event.id}/${key}`;
}

function linkParent() {
    router.post(`/school-admin/${props.school.id}/sports/my-event/${props.event.id}/link-parent`, {
        parent_event_id: parentEventId.value,
    }, { preserveScroll: true });
}

function saveRecord() {
    if (!recordForm.item_id) return;
    recordForm.post(`/school-admin/${props.school.id}/sports/my-event/${props.event.id}/records`, {
        preserveScroll: true,
        onSuccess: () => {
            recordForm.reset('record_value', 'holder_name', 'notes');
        },
    });
}

async function removeRecord(recordId) {
    if (await confirm({ message: 'Are you sure you want to remove this standing record?' })) {
        router.delete(`/school-admin/${props.school.id}/sports/my-event/${props.event.id}/records/${recordId}`, {
            preserveScroll: true,
        });
    }
}

function toggleRecordTracking() {
    router.post(`/school-admin/${props.school.id}/sports/my-event/${props.event.id}/toggle-record-tracking`, {}, {
        preserveScroll: true,
    });
}
</script>
