<template>
    <SahodayaEventsLayout :title="`${event.title} — Grade Master`" :sahodaya="sahodaya" :event="event" :publicUrl="publicUrl"
                          :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Grade Master`" eyebrow="Grade master"
                    description="Map score ranges to grades, used for mark entry and results.">
            <template #actions>
                <button v-if="isHubWithRegions" type="button" class="btn-secondary text-sm"
                        :disabled="syncingToRegions || !props.gradeConfigs.length" :title="!props.gradeConfigs.length ? 'Add bands here first' : ''"
                        @click="syncToRegions">
                    {{ syncingToRegions ? 'Syncing…' : `🔄 Sync to All Regions (${regionCount})` }}
                </button>
                <button type="button" class="btn-secondary text-sm" :disabled="recalculating" @click="recalculateMarks">
                    {{ recalculating ? 'Recalculating…' : '🔄 Recalculate all marks' }}
                </button>
            </template>
        </PageHeader>

        <EventSubNav :sahodaya-id="sahodaya.id" :event-id="event.id" active="grade-master" class="mb-4" />

        <div v-if="event.scoring_preset" class="card !p-4 mb-5 border border-indigo-200 bg-indigo-50/60 text-xs text-indigo-950 space-y-1">
            <p class="font-bold flex items-center gap-1.5"><span>ℹ️</span> This event uses a fixed default scoring table</p>
            <p class="text-indigo-900/80 leading-relaxed">
                {{ event.scoring_preset === 'mcs_kalotsav' ? 'Malappuram Central Sahodaya (MCS) Kalotsav' : 'Confederation Kalotsav (Kalolsavam Manual)' }}
                scoring is used by default when no bands are set below. Add bands here to override it for this event — once you save even one band, your bands take over completely and the fixed table is no longer consulted.
            </p>
        </div>

        <div v-if="childEvents.length" class="card !p-4 mb-5 flex flex-wrap items-center gap-2">
            <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Region:</label>
            <SearchableSelect
                :model-value="String(event.id)"
                @update:model-value="switchSportEvent"
                :options="regionOptions"
                :all-option="false"
                class="w-64"
            />
        </div>

        <div class="space-y-6 max-w-3xl">
            <section class="card space-y-4">
                <div>
                    <h3 class="section-title">Grade bands</h3>
                    <p class="section-desc">
                        Give an item a "Total Marks" value (Mark Settings page) to switch it to a percentage range
                        here — one set of bands then applies consistently across items with different maximums.
                    </p>
                </div>
                <form @submit.prevent="saveGradeConfig" class="grid gap-3 sm:grid-cols-2">
                    <FormField label="Item" class-extra="sm:col-span-2">
                        <template #default>
                            <SearchableSelect
                                v-model="gradeForm.item_id"
                                :options="itemOptions"
                                placeholder="Event-wide (all items)"
                                search-placeholder="Type item name to search…"
                                all-label="Event-wide (all items)"
                                @change="clearRangeFields"
                            />
                        </template>
                    </FormField>
                    <FormField label="Grade" required hint="Pick an existing grade or type a new label — this event's grade set isn't limited to A+/A/B/C.">
                        <template #default="{ id }">
                            <input :id="id" v-model="gradeForm.grade" list="grade-key-options" class="field" required
                                   placeholder="A+, A, B, C, or a custom label">
                            <datalist id="grade-key-options">
                                <option v-for="g in existingGradeKeys" :key="g" :value="g" />
                            </datalist>
                        </template>
                    </FormField>
                    <FormField v-if="selectedItemUsesPercentage" label="Percentage range" :error="gradeForm.errors.min_percent || gradeForm.errors.max_percent">
                        <div class="flex items-center gap-2">
                            <input v-model.number="gradeForm.min_percent" type="number" min="0" max="100" step="0.01" class="field" placeholder="Min %" aria-label="Minimum percentage">
                            <span class="text-slate-400">–</span>
                            <input v-model.number="gradeForm.max_percent" type="number" min="0" max="100" step="0.01" class="field" placeholder="Max %" aria-label="Maximum percentage">
                        </div>
                    </FormField>
                    <FormField v-else label="Score range" :error="gradeForm.errors.min_score || gradeForm.errors.max_score"
                               hint="Use a decimal like 69.99 to close the gap right up to the next band's start (e.g. 60–69.99, then 70–100) — whole numbers alone leave a real gap between bands.">
                        <div class="flex items-center gap-2">
                            <input v-model.number="gradeForm.min_score" type="number" min="0" step="0.01" class="field" placeholder="Min" aria-label="Minimum score">
                            <span class="text-slate-400">–</span>
                            <input v-model.number="gradeForm.max_score" type="number" min="0" step="0.01" class="field" placeholder="Max" aria-label="Maximum score">
                        </div>
                    </FormField>
                    <div class="sm:col-span-2 flex gap-2">
                        <button type="submit" class="btn-primary flex-1" :disabled="gradeForm.processing">
                            {{ editingGradeConfigId ? 'Save changes' : 'Add grade band' }}
                        </button>
                        <button v-if="editingGradeConfigId" type="button" class="btn-secondary" @click="cancelEditGradeConfig">Cancel</button>
                    </div>
                </form>
            </section>

            <input v-if="props.gradeConfigs.length" v-model="searchQuery" type="search" class="field max-w-md" placeholder="Search item or grade…">

            <section class="form-section overflow-hidden !p-0">
                <EmptyState v-if="!filteredGrades.length" title="No grade bands" description="Add bands above or adjust your search." icon="📊" class="p-8" />
                <div v-else class="overflow-x-auto">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Grade</th>
                                <th>Range</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="g in filteredGrades" :key="g.id">
                                <td>{{ g.item?.title || 'Event-wide' }}</td>
                                <td class="font-semibold">{{ g.grade }}</td>
                                <td>
                                    <span v-if="g.min_percent !== null && g.min_percent !== undefined">{{ g.min_percent }}% – {{ g.max_percent }}%</span>
                                    <span v-else>{{ g.min_score }} – {{ g.max_score }}</span>
                                </td>
                                <td class="text-right whitespace-nowrap">
                                    <button type="button" @click="startEditGradeConfig(g)" class="text-indigo-600 text-xs mr-3">Edit</button>
                                    <button type="button" @click="removeGradeConfig(g.id)" class="text-red-600 text-xs">Remove</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventSubNav from '@/Components/sahodaya/EventSubNav.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    event: Object,
    gradeConfigs: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
    classGroupLabels: { type: Object, default: () => ({}) },
});

function itemCategoryLabel(item) {
    if (item.class_group && item.class_group !== 'open') {
        return props.classGroupLabels?.[item.class_group] ?? String(item.class_group).toUpperCase();
    }
    return null;
}

const itemOptions = computed(() => (props.event?.items ?? []).map(item => {
    const marksSuffix = item.total_marks ? ` — /${item.total_marks}` : '';
    const category = itemCategoryLabel(item);
    return {
        id: item.id,
        name: category ? `${item.title} — ${category}${marksSuffix}` : `${item.title}${marksSuffix}`,
    };
}));

const base = computed(() => `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}`);

const regionOptions = computed(() => props.childEvents.map((ev) => ({
    value: String(ev.id),
    label: ev.short_title || ev.title,
})));

function switchSportEvent(value) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${value}/grade-master`);
}

// childEvents[0] is always the hub ("All Regions") when regions exist — see
// FestEvent::regionDropdownOptions(). Only offer the sync action from the hub itself,
// since it's the one screen meant to hold the shared/canonical band set.
const isHubWithRegions = computed(() => props.childEvents.length > 1 && String(props.event.id) === String(props.childEvents[0]?.id));
const regionCount = computed(() => Math.max(props.childEvents.length - 1, 0));

const syncingToRegions = ref(false);
function syncToRegions() {
    syncingToRegions.value = true;
    router.post(`${base.value}/grade-configs/sync-to-regions`, {}, {
        preserveScroll: true,
        onFinish: () => { syncingToRegions.value = false; },
    });
}

const recalculating = ref(false);
async function recalculateMarks() {
    const ok = await confirm({
        message: 'Refresh every mark\'s stored grade and score against the current Grade Master bands and Rank Points config? This updates marks already saved — it won\'t touch raw scores a judge entered, only the grade/points derived from them.',
    });
    if (!ok) return;
    recalculating.value = true;
    router.post(`${base.value}/recalculate-marks`, {}, {
        preserveScroll: true,
        onFinish: () => { recalculating.value = false; },
    });
}

const gradeForm = useForm({ item_id: '', grade: 'A', min_score: null, max_score: null, min_percent: null, max_percent: null });
const editingGradeConfigId = ref(null);
const searchQuery = ref('');

const selectedItemUsesPercentage = computed(() => {
    if (!gradeForm.item_id) return false;
    const item = (props.event?.items ?? []).find((i) => i.id === gradeForm.item_id);
    return !!item?.total_marks;
});

// Switching the item dropdown flips the range inputs between raw-score and
// percentage mode — without this, values typed under the old mode stayed in
// the form and got submitted alongside whatever was typed under the new one.
function clearRangeFields() {
    gradeForm.min_score = null;
    gradeForm.max_score = null;
    gradeForm.min_percent = null;
    gradeForm.max_percent = null;
}

const existingGradeKeys = computed(() => {
    const used = props.gradeConfigs.map((g) => g.grade).filter(Boolean);
    return [...new Set([...used, 'A_plus', 'A', 'B', 'C'])];
});

const filteredGrades = computed(() => {
    const q = searchQuery.value.trim().toLowerCase();
    if (!q) return props.gradeConfigs;
    return props.gradeConfigs.filter((g) =>
        [g.item?.title, g.grade, String(g.min_score), String(g.max_score)].filter(Boolean).join(' ').toLowerCase().includes(q),
    );
});

function addGradeConfig() {
    gradeForm.post(`${base.value}/grade-configs`, { preserveScroll: true, onSuccess: () => gradeForm.reset({ grade: 'A' }) });
}

function startEditGradeConfig(config) {
    editingGradeConfigId.value = config.id;
    gradeForm.clearErrors();
    gradeForm.item_id = config.item_id ?? '';
    gradeForm.grade = config.grade;
    gradeForm.min_score = config.min_score;
    gradeForm.max_score = config.max_score;
    gradeForm.min_percent = config.min_percent;
    gradeForm.max_percent = config.max_percent;
}

function cancelEditGradeConfig() {
    editingGradeConfigId.value = null;
    gradeForm.reset({ grade: 'A' });
}

function saveGradeConfig() {
    if (!editingGradeConfigId.value) {
        addGradeConfig();
        return;
    }

    gradeForm.put(`${base.value}/grade-configs/${editingGradeConfigId.value}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingGradeConfigId.value = null;
            gradeForm.reset({ grade: 'A' });
        },
    });
}

async function removeGradeConfig(id) {
    const ok = await confirm({
        message: 'Remove this grade band? Marks that currently resolve to it will fall back to whatever band (or the default table) covers the gap instead.',
        destructive: true,
    });
    if (!ok) return;
    router.delete(`${base.value}/grade-configs/${id}`, { preserveScroll: true });
}
</script>
