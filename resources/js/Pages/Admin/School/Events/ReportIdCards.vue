<template>
    <SchoolAdminLayout :title="`Student ID Cards — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`Student ID Cards — ${event.title}`"
            :eyebrow="programLabel"
            :description="event.event_type === 'sports'
                ? 'Item cards, sport event cards (one per Sport Event with items listed), or a single event participant pass for your school.'
                : 'Item cards, head cards (one per item head with items listed), or a single event participant pass for your school.'"
        >
            <template #actions>
                <Link :href="`${programBase}/reports/${event.id}`" class="btn-secondary text-sm">← Reports</Link>
                <a v-if="cardScope === 'head'" :href="pdfAllHeadsUrl" class="btn-secondary text-sm" :class="{ 'pointer-events-none opacity-50': downloadGate?.blocked }">All heads PDF ↓</a>
                <a :href="pdfUrl" class="btn-primary text-sm" :class="{ 'pointer-events-none opacity-50': !canGenerate || downloadGate?.blocked }">
                    Download PDF ↓
                </a>
            </template>
        </PageHeader>

        <div v-if="downloadGate?.blocked" class="notice-banner notice-banner--warning mb-6 max-w-5xl text-sm">
            <p class="font-semibold">Payment pending</p>
            <p class="mt-0.5">{{ downloadGate.reason }} Complete membership and event fee payment to preview or download ID cards.</p>
            <p v-if="downloadGate.links?.payments" class="mt-2">
                <Link :href="downloadGate.links.payments" class="link-brand font-semibold">Go to payments →</Link>
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

                    <div class="flex flex-wrap gap-2">
                        <button v-for="t in templates" :key="t.id" type="button"
                                class="px-3 py-1.5 rounded-lg text-xs font-semibold border transition"
                                :class="cardTemplate === t.id
                                    ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]'
                                    : 'bg-white border-slate-200 text-slate-700'"
                                @click="cardTemplate = t.id">
                            {{ t.label }}
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-2">
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
                        <select v-model="itemId" class="field text-sm" @change="onItemChange">
                            <option value="">Select item…</option>
                            <option value="all">All items (bundle PDF)</option>
                            <option v-for="item in items" :key="item.id" :value="String(item.id)">
                                {{ item.title }} ({{ itemCountLabel(item) }})
                            </option>
                        </select>
                    </FormField>

                    <FormField v-if="cardScope === 'head'" :label="event.event_type === 'sports' ? 'Sport Event' : 'Item head'" required>
                        <select v-model="headId" class="field text-sm" @change="loadPreview">
                            <option value="">Select {{ event.event_type === 'sports' ? 'Sport Event' : 'item head' }}…</option>
                            <option v-for="head in heads" :key="head.id" :value="String(head.id)">
                                {{ head.name }} ({{ head.count }} cards)
                            </option>
                        </select>
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
                        <a v-if="!isAllItems" :href="previewUrl" target="_blank" class="btn-secondary w-full justify-center text-sm" :class="{ 'pointer-events-none opacity-50': !canGenerate || downloadGate?.blocked }">
                            Preview in browser ↗
                        </a>
                        <p v-else class="text-xs text-slate-500">"All items" bundles every item into one PDF — download directly below.</p>
                        <a :href="pdfUrl" class="btn-primary w-full justify-center text-sm" :class="{ 'pointer-events-none opacity-50': !canGenerate || downloadGate?.blocked }">
                            Download PDF ↓
                        </a>
                        <a v-if="cardScope === 'head'" :href="pdfAllHeadsUrl" class="btn-secondary w-full justify-center text-sm" :class="{ 'pointer-events-none opacity-50': downloadGate?.blocked }">
                            All heads PDF ↓
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
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import IdCardPreviewTile from '@/Components/fest/IdCardPreviewTile.vue';
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
    downloadGate: { type: Object, default: null },
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const itemId = ref('');
const headId = ref('');
const cardTemplate = ref('premium');
const cardScope = ref(props.event?.event_type === 'sports' ? 'head' : 'item');
const layout = ref('individual');
const previewCards = ref([]);
const loading = ref(false);

const templates = [
    { id: 'premium', label: 'Premium' },
    { id: 'standard', label: 'Standard' },
];

const isAllItems = computed(() => cardScope.value === 'item' && itemId.value === 'all');

const selectedItem = computed(() =>
    props.items.find((item) => String(item.id) === String(itemId.value)) ?? null,
);
const selectedItemSupportsTeam = computed(() =>
    !isAllItems.value && ['group', 'team'].includes(selectedItem.value?.participant_type),
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
    if (layout.value === 'team') params.set('layout', 'team');
    return `${programBase.value}/reports/${props.event.id}/id-cards/pdf-all-items?${params.toString()}`;
});

const previewUrl = computed(() => {
    const params = new URLSearchParams({ template: cardTemplate.value, scope: cardScope.value });
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
    if (cardScope.value === 'item' && itemId.value) params.set('item_id', itemId.value);
    if (cardScope.value === 'head' && headId.value) params.set('head_id', headId.value);
    if (cardScope.value === 'item' && layout.value === 'team' && selectedItemSupportsTeam.value) {
        params.set('layout', 'team');
    }
    return `${programBase.value}/reports/${props.event.id}/id-cards/pdf?${params.toString()}`;
});

const pdfAllHeadsUrl = computed(() => {
    const params = new URLSearchParams({ template: cardTemplate.value });
    return `${programBase.value}/reports/${props.event.id}/id-cards/pdf-all-heads?${params.toString()}`;
});

function itemCountLabel(item) {
    if (layout.value === 'team' && ['group', 'team'].includes(item.participant_type)) {
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
    if (props.downloadGate?.blocked) {
        previewCards.value = [];
        return;
    }

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
        if (cardScope.value === 'item' && itemId.value) params.set('item_id', itemId.value);
        if (cardScope.value === 'head' && headId.value) params.set('head_id', headId.value);
        if (cardScope.value === 'item' && layout.value === 'team' && selectedItemSupportsTeam.value) {
            params.set('layout', 'team');
        }
        const res = await fetch(`${cardsUrl.value}?${params.toString()}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        const data = await res.json();
        previewCards.value = data.cards ?? [];
    } catch {
        previewCards.value = [];
    } finally {
        loading.value = false;
    }
}
</script>
