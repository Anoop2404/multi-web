<template>
    <SchoolAdminLayout :title="`Student ID Cards — ${event.title}`" :school="school" :show-header-title="false">
        <PageHeader
            :title="`Student ID Cards — ${event.title}`"
            :eyebrow="programLabel"
            description="Item cards (one per fest item) or a single event participant pass for your school."
        >
            <template #actions>
                <Link :href="`${programBase}/reports`" class="btn-secondary text-sm">← Reports</Link>
                <a :href="pdfUrl" class="btn-primary text-sm" :class="{ 'pointer-events-none opacity-50': !canGenerate }">
                    Download PDF ↓
                </a>
            </template>
        </PageHeader>

        <div class="notice-banner notice-banner--info mb-6 max-w-3xl text-sm">
            <strong>Premium</strong> adds gold accents and item badges. <strong>Event pass</strong> gives one lanyard card per student listing all their items.
        </div>

        <div class="card max-w-3xl space-y-4 mb-6">
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
                    <option v-for="item in items" :key="item.id" :value="String(item.id)">
                        {{ item.title }} ({{ itemCountLabel(item) }})
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

            <div v-else-if="loading" class="text-sm text-slate-500 py-4">Loading preview…</div>

            <div v-else-if="previewCards.length" class="space-y-3">
                <p class="text-sm font-semibold text-slate-800">{{ previewCards.length }} card{{ previewCards.length === 1 ? '' : 's' }}</p>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    <IdCardPreviewTile v-for="card in previewCards" :key="card.entity_id"
                                       :card="card" :cluster-name="clusterName"
                                       :event-title="event.title" :variant="cardTemplate" />
                </div>
            </div>

            <EmptyState v-else title="No participants"
                        description="No approved participants from your school for this selection." icon="🪪" class="py-6" />
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
    meta: Object,
    clusterName: { type: String, default: 'Sahodaya' },
});

const { programLabel, programBase } = useSchoolProgramContext(props);
const itemId = ref('');
const cardTemplate = ref('premium');
const cardScope = ref('item');
const layout = ref('individual');
const previewCards = ref([]);
const loading = ref(false);

const templates = [
    { id: 'premium', label: 'Premium' },
    { id: 'standard', label: 'Standard' },
];

const selectedItem = computed(() =>
    props.items.find((item) => String(item.id) === String(itemId.value)) ?? null,
);
const selectedItemSupportsTeam = computed(() =>
    ['group', 'team'].includes(selectedItem.value?.participant_type),
);
const canGenerate = computed(() => cardScope.value === 'event' || Boolean(itemId.value));

const cardsUrl = computed(() => `${programBase.value}/reports/${props.event.id}/id-cards/cards`);

const pdfUrl = computed(() => {
    const params = new URLSearchParams({ template: cardTemplate.value, scope: cardScope.value });
    if (cardScope.value === 'item' && itemId.value) params.set('item_id', itemId.value);
    if (cardScope.value === 'item' && layout.value === 'team' && selectedItemSupportsTeam.value) {
        params.set('layout', 'team');
    }
    return `${programBase.value}/reports/${props.event.id}/id-cards/pdf?${params.toString()}`;
});

function itemCountLabel(item) {
    if (layout.value === 'team' && ['group', 'team'].includes(item.participant_type)) {
        return `${item.registration_count ?? 0} teams`;
    }
    return `${item.count ?? 0} cards`;
}

function setScope(scope) {
    cardScope.value = scope;
    if (scope === 'event') {
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

    loading.value = true;
    try {
        const params = new URLSearchParams({ scope: cardScope.value });
        if (cardScope.value === 'item' && itemId.value) params.set('item_id', itemId.value);
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
