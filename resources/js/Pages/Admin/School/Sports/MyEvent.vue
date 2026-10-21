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
                            <thead><tr><th>Place</th><th>Athlete</th><th>Measurement</th><th>Score</th></tr></thead>
                            <tbody>
                                <tr v-for="(row, idx) in resultsByItem[reg.item.id]" :key="idx">
                                    <td class="font-semibold">{{ row.position }}</td>
                                    <td>{{ row.name }}</td>
                                    <td>{{ row.measurement || '—' }}</td>
                                    <td>{{ row.score ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="p-4 text-sm text-slate-400">No ranks for this item yet.</p>
                </section>
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
import { Link, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import SchoolRoundMarks from '@/Components/events/SchoolRoundMarks.vue';

const props = defineProps({
    school: Object,
    tab: { type: String, default: 'overview' },
    event: Object,
    parentEvents: { type: Array, default: () => [] },
    registrations: { type: Array, default: () => [] },
    marks: { type: Object, default: () => ({}) },
    resultsByItem: { type: Object, default: () => ({}) },
    stats: { type: Object, default: () => ({}) },
});

const tabs = [
    { key: 'overview', label: 'Overview' },
    { key: 'marks', label: 'Marks' },
    { key: 'results', label: 'Results' },
    { key: 'link', label: 'Link parent' },
    { key: 'winners', label: 'Winners' },
];

const tabDescriptions = {
    overview: 'School-round sports day — registrations, marks, and winner promotion.',
    marks: 'Enter times, distances, and ranks for your athletes.',
    results: 'Ranked results by event item.',
    link: 'Connect this school meet to the Sahodaya cluster event.',
    winners: 'Promote school-round winners to Sahodaya registration.',
};

const parentEventId = ref('');
const parentEventOptions = computed(() => (props.parentEvents ?? []).map((p) => ({
    value: p.id,
    label: `${p.title} (${p.level_round})`,
})));
const resultItems = computed(() => [...new Map(props.registrations.map(reg => [reg.item?.id, reg])).values()].filter(reg => reg.item));

function tabHref(key) {
    return `/school-admin/${props.school.id}/sports/my-event/${props.event.id}/${key}`;
}

function linkParent() {
    router.post(`/school-admin/${props.school.id}/sports/my-event/${props.event.id}/link-parent`, {
        parent_event_id: parentEventId.value,
    }, { preserveScroll: true });
}
</script>
