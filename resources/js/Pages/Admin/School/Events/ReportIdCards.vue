<template>
    <SchoolAdminLayout :title="`Student ID Cards — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`Student ID Cards — ${event.title}`"
            :eyebrow="programLabel"
            :description="isKalolsavam
                ? 'Participant Pass — one card per student listing every item they registered for.'
                : event.event_type === 'sports'
                    ? 'Item cards, sport event cards (one per Sport Event with items listed), or a single event participant pass for your school.'
                    : 'Item cards, head cards (one per item head with items listed), or a single event participant pass for your school.'"
        >
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← Reports</Link>
                <a :href="previewUrl" target="_blank" class="btn-secondary text-sm" :class="{ 'pointer-events-none opacity-50': !canGenerate || gate?.blocked }">
                    Preview in browser ↗
                </a>
                <a v-if="cardScope === 'head'" :href="pdfAllHeadsUrl" class="btn-secondary text-sm" :class="{ 'pointer-events-none opacity-50': gate?.blocked }">All heads PDF ↓</a>
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
                        <h3 class="section-title text-sm">1. Card style & filters</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Generate ID lanyards for approved participants. All items for each participant are listed on the card.
                        </p>
                    </div>

                    <div v-if="hasLevels" class="space-y-2">
                        <p class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Registration level</p>
                        <div class="flex flex-wrap gap-2">
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
                            Each level is paid and approved separately. Cards cover the items registered under the selected level only.
                        </p>
                    </div>

                    <div v-if="!isKalolsavam" class="flex flex-wrap gap-2">
                        <button v-for="t in templates" :key="t.id" type="button"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition"
                                :class="cardTemplate === t.id
                                    ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]'
                                    : 'bg-white border-slate-200 text-slate-700'"
                                @click="cardTemplate = t.id">
                            {{ t.label }}
                        </button>
                    </div>

                    <div v-if="!isKalolsavam" class="flex flex-wrap gap-2">
                        <button type="button" class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition"
                                :class="cardScope === 'item'
                                    ? 'bg-emerald-700 text-white border-emerald-700'
                                    : 'bg-white border-slate-200 text-slate-700'"
                                @click="setScope('item')">
                            Item ID card
                        </button>
                        <button type="button" class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition"
                                :class="cardScope === 'head'
                                    ? 'bg-emerald-700 text-white border-emerald-700'
                                    : 'bg-white border-slate-200 text-slate-700'"
                                @click="setScope('head')">
                            Head ID card
                        </button>
                        <button type="button" class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition"
                                :class="cardScope === 'event'
                                    ? 'bg-emerald-700 text-white border-emerald-700'
                                    : 'bg-white border-slate-200 text-slate-700'"
                                @click="setScope('event')">
                            Event participant pass
                        </button>
                    </div>

                    <FormField v-if="cardScope === 'item'" label="Fest item" required>
                        <SearchableSelect
                            v-model="itemId"
                            :options="itemOptions"
                            placeholder="Select item…"
                            search-placeholder="Type item name to search…"
                            :all-option="false"
                            @change="onItemChange"
                        />
                    </FormField>

                    <FormField v-if="cardScope === 'head'" :label="event.event_type === 'sports' ? 'Sport Event' : 'Item head'" required>
                        <SearchableSelect
                            v-model="headId"
                            :options="headOptions"
                            :all-option="true"
                            :all-label="`Select ${event.event_type === 'sports' ? 'Sport Event' : 'item head'}…`"
                            @change="loadPreview"
                        />
                    </FormField>

                    <div v-if="cardScope === 'item' && selectedItemSupportsTeam" class="flex flex-wrap gap-2">
                        <button type="button" class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition"
                                :class="layout === 'individual'
                                    ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]'
                                    : 'bg-white border-slate-200 text-slate-700'"
                                @click="setLayout('individual')">
                            Individual cards
                        </button>
                        <button type="button" class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition"
                                :class="layout === 'team'
                                    ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]'
                                    : 'bg-white border-slate-200 text-slate-700'"
                                @click="setLayout('team')">
                            Team / group roster
                        </button>
                    </div>

                    <div v-if="cardScope === 'item' && !itemId" class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                        Choose an item to see participant cards.
                    </div>

                    <div v-else-if="cardScope === 'head' && !headId" class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                        Choose {{ event.event_type === 'sports' ? 'a Sport Event' : 'an item head' }}. Each card lists all items your students registered under that head.
                    </div>

                    <div v-else-if="loading" class="text-sm text-slate-500 py-6 text-center">Loading preview…</div>

                    <div v-else-if="previewCards.length" class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="section-title text-sm">2. Preview ({{ previewCards.length }} cards)</h3>
                            <p class="text-xs text-slate-500">Approved registrations only</p>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3 max-h-[32rem] overflow-y-auto pr-1">
                            <IdCardPreviewTile v-for="card in previewCards" :key="card.entity_id"
                                               :card="card" :cluster-name="clusterName"
                                               :cluster-logo-url="clusterLogoUrl"
                                               :event-title="event.title" :variant="cardTemplate" />
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
                        <a v-if="cardScope === 'head'" :href="pdfAllHeadsUrl" class="btn-secondary w-full justify-center text-sm" :class="{ 'pointer-events-none opacity-50': gate?.blocked }">
                            All heads PDF ↓
                        </a>
                    </div>
                </div>

                <div class="card space-y-3">
                    <h3 class="section-title text-sm">Layout guide</h3>
                    <ul class="text-xs text-slate-600 space-y-1.5 list-disc pl-4">
                        <li v-if="cardTemplate === 'pass'">Print on standard A4 paper (10 cards per sheet).</li>
                        <li v-else>Print on standard A4 paper (4 cards per sheet).</li>
                        <li>Cut along outer border guides.</li>
                        <li v-if="cardTemplate !== 'pass'">Punch lanyard hole at top center mark.</li>
                        <li v-if="cardTemplate !== 'pass'">QR codes verify participant status when scanned.</li>
                        <li v-else>No QR code — name, photo, and registered items only.</li>
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
import { isKalolsavamEvent } from '@/support/festEventType.js';

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
    // Registration levels, for events billed one level at a time. Empty on every
    // other event, which keeps the single whole-event card set.
    levels: { type: Array, default: () => [] },
    defaultLevelId: { type: [Number, String], default: null },
    downloadGate: { type: Object, default: null },
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const isKalolsavam = computed(() => isKalolsavamEvent(props.event));
const cardScope = ref(
    isKalolsavamEvent(props.event) ? 'event'
        : props.event?.event_type === 'sports' ? 'head'
            : 'item',
);
const itemId = ref(cardScope.value === 'item' ? (props.items?.length ? 'all' : '') : '');
const headId = ref(cardScope.value === 'head' ? (props.heads?.length ? String(props.heads[0].id) : '') : '');
const cardTemplate = ref(isKalolsavamEvent(props.event) ? 'pass' : 'premium');
const layout = ref('individual');
const previewCards = ref([]);
const loading = ref(false);
// Payment-gating is per selection (a Kalotsavam phase's fee can be paid
// independently of other phases — see FestSchoolEventFeeService::isPhasePaid()),
// so the page-load `downloadGate` prop (a whole-event aggregate) is only a
// starting point. loadPreview() below refreshes this to reflect whatever item/
// head is currently selected.
const gate = ref(props.downloadGate);
// Each level is invoiced and approved on its own, so cards are generated one level at
// a time: the server gates on the selected level's fee and returns only that level's
// participants. Defaults to the first level the school has actually paid for.
// A school that has paid every level starts on the combined set, exactly as before
// levels existed; anyone mid-payment starts on the level they have actually paid for.
const levelId = ref(
    props.levels?.length && props.levels.every((level) => level.paid)
        ? 'all'
        : (props.defaultLevelId != null ? String(props.defaultLevelId) : ''),
);
const hasLevels = computed(() => (props.levels?.length ?? 0) > 0);
const allLevelsPaid = computed(() => hasLevels.value && props.levels.every((level) => level.paid));

function setLevel(id) {
    levelId.value = String(id);
    // The item picker is level-scoped, so a selection from another level can't survive.
    if (cardScope.value === 'item' && !isAllItems.value
        && !levelItems.value.some((item) => String(item.id) === String(itemId.value))) {
        itemId.value = levelItems.value.length ? 'all' : '';
    }
    loadPreview();
}

onMounted(() => {
    loadPreview();
});

const templates = [
    { id: 'pass', label: 'Participant Pass' },
    { id: 'premium', label: 'Premium' },
    { id: 'standard', label: 'Standard' },
];

const isAllItems = computed(() => cardScope.value === 'item' && itemId.value === 'all');

function itemLabel(item) {
    const withCategory = item.category_label ? `${item.title} — ${item.category_label}` : item.title;
    return `${withCategory} (${itemCountLabel(item)})`;
}

// Items carry the level they belong to; anything without one (non-levelled events)
// stays visible in every selection.
const levelItems = computed(() => {
    if (!hasLevels.value || !levelId.value || levelId.value === 'all') return props.items;
    return props.items.filter((item) => item.level_id == null
        || String(item.level_id) === String(levelId.value));
});

const itemOptions = computed(() => [
    { id: 'all', name: 'All items (bundle PDF)' },
    ...levelItems.value.map(item => ({ id: String(item.id), name: itemLabel(item) })),
]);

const headOptions = computed(() =>
    props.heads.map(head => ({ id: String(head.id), name: `${head.name} (${head.count} cards)` })),
);

const selectedItem = computed(() =>
    props.items.find((item) => String(item.id) === String(itemId.value)) ?? null,
);
const selectedItemSupportsTeam = computed(() =>
    !isAllItems.value && ['team', 'group', 'pair', 'trio'].includes(selectedItem.value?.participant_type),
);
const canGenerate = computed(() => {
    if (cardScope.value === 'event') return true;
    if (cardScope.value === 'head') return Boolean(headId.value);
    return Boolean(itemId.value);
});

const cardsUrl = computed(() => `${programBase.value}/reports/${props.event.id}/id-cards/cards`);

// "All items" bundles every item into one PDF (grouped, one section per item) —
// there's no single-page browser preview for that combination, only the PDF.
const pdfAllItemsUrl = computed(() => {
    const params = new URLSearchParams({ template: cardTemplate.value });
    if (levelId.value) params.set('batch_id', levelId.value);
    if (layout.value === 'team') params.set('layout', 'team');
    return `${programBase.value}/reports/${props.event.id}/id-cards/pdf-all-items?${params.toString()}`;
});

const previewUrl = computed(() => {
    const params = new URLSearchParams({ template: cardTemplate.value, scope: cardScope.value });
    if (levelId.value) params.set('batch_id', levelId.value);
    if (cardScope.value === 'item' && itemId.value && !isAllItems.value) params.set('item_id', itemId.value);
    if (cardScope.value === 'head' && headId.value) params.set('head_id', headId.value);
    if (cardScope.value === 'item' && layout.value === 'team' && selectedItemSupportsTeam.value) {
        params.set('layout', 'team');
    }
    return `${programBase.value}/reports/${props.event.id}/id-cards/preview?${params.toString()}`;
});

const pdfUrl = computed(() => {
    if (isAllItems.value) return pdfAllItemsUrl.value;

    const params = new URLSearchParams({ template: cardTemplate.value, scope: cardScope.value });
    if (levelId.value) params.set('batch_id', levelId.value);
    if (cardScope.value === 'item' && itemId.value) params.set('item_id', itemId.value);
    if (cardScope.value === 'head' && headId.value) params.set('head_id', headId.value);
    if (cardScope.value === 'item' && layout.value === 'team' && selectedItemSupportsTeam.value) {
        params.set('layout', 'team');
    }
    return `${programBase.value}/reports/${props.event.id}/id-cards/pdf?${params.toString()}`;
});

const pdfAllHeadsUrl = computed(() => {
    const params = new URLSearchParams({ template: cardTemplate.value });
    if (levelId.value) params.set('batch_id', levelId.value);
    return `${programBase.value}/reports/${props.event.id}/id-cards/pdf-all-heads?${params.toString()}`;
});

function itemCountLabel(item) {
    if (layout.value === 'team' && ['team', 'group', 'pair', 'trio'].includes(item.participant_type)) {
        return `${item.registration_count ?? 0} teams`;
    }
    return `${item.count ?? 0} cards`;
}

function setScope(scope) {
    cardScope.value = scope;
    if (scope === 'event' || scope === 'head') {
        layout.value = 'individual';
    }
    loadPreview();
}

function onItemChange() {
    if (!selectedItemSupportsTeam.value) {
        layout.value = 'individual';
    }
    loadPreview();
}

function setLayout(value) {
    layout.value = value;
    loadPreview();
}

async function loadPreview() {
    if (cardScope.value === 'item' && !itemId.value) {
        previewCards.value = [];
        return;
    }

    if (cardScope.value === 'head' && !headId.value) {
        previewCards.value = [];
        return;
    }

    loading.value = true;
    try {
        const params = new URLSearchParams({ scope: cardScope.value });
        if (levelId.value) params.set('batch_id', levelId.value);
        if (cardScope.value === 'item' && itemId.value) params.set('item_id', itemId.value);
        if (cardScope.value === 'head' && headId.value) params.set('head_id', headId.value);
        if (cardScope.value === 'item' && layout.value === 'team' && selectedItemSupportsTeam.value) {
            params.set('layout', 'team');
        }
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
