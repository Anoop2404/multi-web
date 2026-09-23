<template>
    <SchoolAdminLayout :title="`Student ID Cards — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`Student ID Cards — ${event.title}`"
            :eyebrow="programLabel"
            description="Participant Pass — one card per student listing every item they registered for."
        >
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← Reports</Link>
                <a :href="previewUrl" target="_blank" class="btn-secondary text-sm" :class="{ 'pointer-events-none opacity-50': !canGenerate || gate?.blocked }">
                    Preview in browser ↗
                </a>
                <a :href="pdfUrl" class="btn-primary text-sm" :class="{ 'pointer-events-none opacity-50': !canGenerate || gate?.blocked }">
                    Download PDF ↓
                </a>
            </template>
        </PageHeader>

        <div v-if="gate?.blocked" class="notice-banner notice-banner--warning mb-6 max-w-5xl text-sm">
            <p class="font-semibold">Payment pending</p>
            <p class="mt-0.5">{{ gate.reason }}</p>
            <p v-if="gate.links?.payments" class="mt-2">
                <Link :href="gate.links.payments" class="link-brand font-semibold">Go to payments →</Link>
            </p>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-4">
                <div class="card space-y-4">
                    <div>
                        <h3 class="section-title text-sm">1. Filters</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Participant Pass — one card per student listing every item they registered for.
                        </p>
                    </div>

                    <div v-if="hasLevels" class="space-y-2">
                        <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Registration level</p>
                        <div v-if="levelLocked" class="flex flex-wrap items-center gap-2">
                            <span class="px-3 py-1.5 rounded-lg text-xs font-semibold border bg-slate-50 border-slate-200 text-slate-700">
                                {{ levels[0].name }}
                            </span>
                            <span class="text-xs" :class="levels[0].paid ? 'text-emerald-700' : 'text-amber-700'">
                                {{ levels[0].paid ? 'Fee paid & approved' : 'Fee pending' }}
                            </span>
                        </div>
                        <div v-else class="flex flex-wrap gap-2">
                            <button v-if="allLevelsPaid" type="button"
                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition"
                                    :class="levelId === 'all'
                                        ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]'
                                        : 'bg-white border-slate-200 text-slate-700'"
                                    @click="setLevel('all')">
                                All levels
                            </button>
                            <button v-for="level in levels" :key="level.id" type="button"
                                    class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition"
                                    :class="String(levelId) === String(level.id)
                                        ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]'
                                        : 'bg-white border-slate-200 text-slate-700'"
                                    @click="setLevel(level.id)">
                                {{ level.name }}
                                <span :class="String(levelId) === String(level.id) ? 'opacity-80' : 'text-slate-400'">
                                    · {{ level.paid ? 'paid' : 'unpaid' }}
                                </span>
                            </button>
                        </div>
                        <p class="text-xs text-slate-500">
                            {{ levelLocked
                                ? 'This phase is billed under the level above. Its cards unlock as soon as that level\'s fee is approved, whatever other levels still owe.'
                                : 'Each level is paid and approved separately. Cards cover the items registered under the selected level only.' }}
                        </p>
                    </div>

                    <FormField v-if="studentOptions.length" label="Student filter (optional)">
                        <SearchableSelect
                            v-model="studentId"
                            :options="studentOptions"
                            :all-option="true"
                            all-label="All students"
                            placeholder="All students"
                            search-placeholder="Type student name to search…"
                            @change="loadPreview"
                        />
                    </FormField>

                    <div v-if="loading" class="text-sm text-slate-500 py-6 text-center">Loading preview…</div>

                    <div v-else-if="previewCards.length" class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="section-title text-sm">2. Preview ({{ previewCards.length }} cards)</h3>
                            <p class="text-xs text-slate-500">Approved registrations only</p>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3 max-h-[32rem] overflow-y-auto pr-1">
                            <IdCardPreviewTile v-for="card in previewCards" :key="card.entity_id"
                                               :card="card" :cluster-name="clusterName"
                                               :cluster-logo-url="clusterLogoUrl"
                                               :event-title="event.title" variant="pass" />
                        </div>
                    </div>

                    <EmptyState v-else title="No participants"
                                description="No approved participants from your school for this selection." icon="🪪" class="py-8" />
                </div>
            </div>

            <aside class="space-y-4">
                <div class="card space-y-3">
                    <h3 class="section-title text-sm">Generate</h3>
                    <div class="space-y-2">
                        <a :href="previewUrl" target="_blank" class="btn-secondary w-full justify-center text-sm" :class="{ 'pointer-events-none opacity-50': !canGenerate || gate?.blocked }">
                            Preview in browser ↗
                        </a>
                        <a :href="pdfUrl" class="btn-primary w-full justify-center text-sm" :class="{ 'pointer-events-none opacity-50': !canGenerate || gate?.blocked }">
                            Download PDF ↓
                        </a>
                    </div>
                </div>

                <div class="card space-y-3">
                    <h3 class="section-title text-sm">Layout guide</h3>
                    <ul class="text-xs text-slate-600 space-y-1.5 list-disc pl-4">
                        <li>Print on standard A4 paper (4 cards per sheet).</li>
                        <li>Cut along outer border guides.</li>
                        <li>Punch lanyard hole at top center mark.</li>
                        <li>QR codes verify participant status when scanned.</li>
                    </ul>
                </div>
            </aside>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { computed, ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import IdCardPreviewTile from '@/Components/fest/IdCardPreviewTile.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { useSchoolProgramContext } from '@/composables/useSchoolProgramContext.js';

const props = defineProps({
    school: Object,
    program: [String, Object],
    programMeta: { type: Object, default: null },
    event: Object,
    items: Array,
    heads: { type: Array, default: () => [] },
    meta: Object,
    clusterName: { type: String, default: 'Sahodaya' },
    clusterLogoUrl: { type: String, default: '' },
    students: { type: Array, default: () => [] },
    // Registration levels, for events billed one level at a time. Empty on every
    // other event, which keeps the single whole-event card set.
    levels: { type: Array, default: () => [] },
    defaultLevelId: { type: [Number, String], default: null },
    downloadGate: { type: Object, default: null },
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const studentId = ref('');
const studentOptions = computed(() => props.students ?? []);
const previewCards = ref([]);
const loading = ref(false);
const gate = ref(props.downloadGate);

const levelLocked = (props.levels?.length ?? 0) === 1;
const levelId = ref(
    levelLocked
        ? String(props.levels[0].id)
        : (props.levels?.length && props.levels.every((level) => level.paid)
            ? 'all'
            : (props.defaultLevelId != null ? String(props.defaultLevelId) : '')),
);
const hasLevels = computed(() => (props.levels?.length ?? 0) > 0);
const allLevelsPaid = computed(() => hasLevels.value && ! levelLocked
    && props.levels.every((level) => level.paid));

function setLevel(id) {
    levelId.value = String(id);
    loadPreview();
}

onMounted(() => {
    loadPreview();
});

const canGenerate = computed(() => true);
const cardsUrl = computed(() => `${programBase.value}/reports/${props.event.id}/id-cards/cards`);

function buildQueryParams() {
    const params = new URLSearchParams({ template: 'pass', scope: 'event' });
    if (levelId.value) params.set('batch_id', levelId.value);
    if (studentId.value) params.set('student_id', studentId.value);
    return params.toString();
}

const previewUrl = computed(() => `${programBase.value}/reports/${props.event.id}/id-cards/preview?${buildQueryParams()}`);
const pdfUrl = computed(() => `${programBase.value}/reports/${props.event.id}/id-cards/pdf?${buildQueryParams()}`);

async function loadPreview() {
    loading.value = true;
    try {
        const params = new URLSearchParams({ scope: 'event' });
        if (levelId.value) params.set('batch_id', levelId.value);
        if (studentId.value) params.set('student_id', studentId.value);
        const res = await fetch(`${cardsUrl.value}?${params.toString()}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        gate.value = data.downloadGate ?? props.downloadGate;
        previewCards.value = data.cards ?? [];
    } catch {
        previewCards.value = [];
    } finally {
        loading.value = false;
    }
}
</script>
