<template>
    <SahodayaEventsLayout :title="`${event.title} — ID Cards`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — ID Card Creator`" eyebrow="Output"
                    description="Pick an item, preview participants, then print lanyard-ready ID cards.">
        </PageHeader>

        <div class="grid lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-4">
                <div class="card space-y-4">
                    <h3 class="section-title">Card type</h3>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="t in types" :key="t.id" type="button"
                                class="px-4 py-2 rounded-xl text-sm font-semibold border transition"
                                :class="audience === t.id
                                    ? 'bg-[#0f3d7a] text-white border-[#0f3d7a]'
                                    : 'bg-white border-slate-200 text-slate-700 hover:border-[#0f3d7a]/40'"
                                @click="audience = t.id">
                            {{ t.label }}
                            <span class="ml-1 opacity-75">({{ meta[t.countKey] ?? 0 }})</span>
                        </button>
                    </div>
                    <p class="text-xs text-slate-500">{{ activeType.hint }}</p>
                </div>

                <div v-if="audience === 'student'" class="card space-y-4">
                    <div>
                        <h3 class="section-title text-sm">1. Card design & scope</h3>
                        <p class="text-xs text-slate-500 mt-1">Premium layout adds gold accents, item badges, and event branding. Choose item cards (one per fest item) or a single event participant pass.</p>
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
                                :class="cardScope === 'event'
                                    ? 'bg-emerald-700 text-white border-emerald-700'
                                    : 'bg-white border-slate-200 text-slate-700'"
                                @click="setScope('event')">
                            Event participant pass
                        </button>
                    </div>

                    <div v-if="cardScope === 'item'" class="grid sm:grid-cols-2 gap-3">
                        <FormField label="Fest item" required>
                            <select v-model="filters.item_id" class="field text-sm" required @change="onItemChange">
                                <option value="">Select item…</option>
                                <option v-for="item in items" :key="item.id" :value="String(item.id)">
                                    {{ item.title }} ({{ itemCountLabel(item) }})
                                </option>
                            </select>
                        </FormField>
                        <FormField label="School filter">
                            <select v-model="filters.school_id" class="field text-sm" @change="loadPreview">
                                <option value="">All schools</option>
                                <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </FormField>
                    </div>

                    <div v-if="cardScope === 'event'" class="grid sm:grid-cols-2 gap-3">
                        <FormField label="School filter">
                            <select v-model="filters.school_id" class="field text-sm" @change="loadPreview">
                                <option value="">All schools</option>
                                <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </FormField>
                    </div>

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

                    <div v-if="cardScope === 'item' && !filters.item_id" class="rounded-lg border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                        Select an item above to load the participant preview grid.
                    </div>

                    <div v-else-if="loading" class="text-sm text-slate-500 py-6 text-center">Loading preview…</div>

                    <div v-else-if="previewCards.length" class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="section-title text-sm">2. Preview ({{ previewCards.length }} cards)</h3>
                            <p class="text-xs text-slate-500">Approved registrations only</p>
                        </div>
                        <div class="grid sm:grid-cols-2 xl:grid-cols-3 gap-3 max-h-[32rem] overflow-y-auto pr-1">
                            <IdCardPreviewTile v-for="card in previewCards" :key="card.entity_id"
                                               :card="card" :cluster-name="sahodaya.name"
                                               :event-title="event.title" :variant="cardTemplate" />
                        </div>
                    </div>

                    <EmptyState v-else title="No participants"
                                description="No approved participants match this item and school filter." icon="🪪" class="py-8" />
                </div>

                <div v-else-if="audience === 'volunteer'" class="card">
                    <p class="text-sm text-slate-600">
                        Prints all volunteers from <Link :href="settingsVolunteersUrl" class="link-brand font-semibold">Event settings → Volunteers</Link>.
                    </p>
                </div>

                <div v-else class="card">
                    <p class="text-sm text-slate-600">
                        Prints all users assigned under <Link :href="eventStaffUrl" class="link-brand font-semibold">Event staff</Link>.
                    </p>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="card space-y-3">
                    <h3 class="section-title text-sm">Layout</h3>
                    <ul class="text-xs text-slate-600 space-y-1.5 list-disc pl-4">
                        <li>Standard or premium (gold-accent) template</li>
                        <li>Item cards — one card per participant per fest item</li>
                        <li>Event pass — one card per student/teacher for the whole event</li>
                        <li>Standard CR80 size (85.6 × 54 mm)</li>
                        <li>10 cards per A4 page (2 × 5 grid)</li>
                        <li>QR code for gate verification</li>
                    </ul>
                </div>

                <div class="card space-y-3">
                    <h3 class="section-title text-sm">Generate</h3>
                    <p v-if="audience === 'student' && cardScope === 'item' && !filters.item_id" class="text-xs text-amber-700">
                        Select an item before generating PDF.
                    </p>
                    <a :href="previewUrl" target="_blank" rel="noopener"
                       class="btn-secondary w-full text-sm text-center block"
                       :class="{ 'pointer-events-none opacity-50': !canGenerate }">
                        Preview in browser ↗
                    </a>
                    <a :href="pdfUrl" class="btn-primary w-full text-sm text-center block"
                       :class="{ 'pointer-events-none opacity-50': !canGenerate }">
                        Download PDF ↓
                    </a>
                    <a v-if="audience === 'student' && cardScope === 'item'"
                       :href="pdfAllItemsUrl"
                       class="btn-secondary w-full text-sm text-center block">
                        Download all items (PDF) ↓
                    </a>
                </div>
            </aside>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import IdCardPreviewTile from '@/Components/fest/IdCardPreviewTile.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, items: Array, meta: Object, schools: Array,
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/id-cards`;
const audience = ref('student');
const cardTemplate = ref('premium');
const cardScope = ref('item');
const filters = reactive({ school_id: '', item_id: '' });
const layout = ref('individual');
const previewCards = ref([]);
const loading = ref(false);

const selectedItem = computed(() =>
    props.items.find((item) => String(item.id) === String(filters.item_id)) ?? null,
);
const selectedItemSupportsTeam = computed(() =>
    ['group', 'team'].includes(selectedItem.value?.participant_type),
);

function itemCountLabel(item) {
    if (layout.value === 'team' && ['group', 'team'].includes(item.participant_type)) {
        return `${item.registration_count ?? 0} teams`;
    }
    return `${item.count ?? 0} cards`;
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

const templates = [
    { id: 'premium', label: 'Premium' },
    { id: 'standard', label: 'Standard' },
];

function setScope(scope) {
    cardScope.value = scope;
    if (scope === 'event') {
        layout.value = 'individual';
    }
    loadPreview();
}

const types = [
    { id: 'student', label: 'Students', countKey: 'students', hint: 'Item-first workflow — Fest ID, school, chest number, schedule.' },
    { id: 'volunteer', label: 'Volunteers', countKey: 'volunteers', hint: 'Event-day volunteers from settings.' },
    { id: 'staff', label: 'Staff', countKey: 'staff', hint: 'Portal users assigned as event staff.' },
];

const activeType = computed(() => types.find(t => t.id === audience.value) ?? types[0]);
const canGenerate = computed(() => {
    if (audience.value !== 'student') return true;
    if (cardScope.value === 'event') return true;
    return Boolean(filters.item_id);
});

const settingsVolunteersUrl = computed(() =>
    `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/settings/volunteers`);
const eventStaffUrl = computed(() =>
    `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/event-staff`);

function queryString() {
    const p = new URLSearchParams({ audience: audience.value, template: cardTemplate.value });
    if (audience.value === 'student') {
        p.set('scope', cardScope.value);
        if (filters.school_id) p.set('school_id', filters.school_id);
        if (cardScope.value === 'item' && filters.item_id) p.set('item_id', filters.item_id);
        if (cardScope.value === 'item' && layout.value === 'team' && selectedItemSupportsTeam.value) {
            p.set('layout', 'team');
        }
    }
    return p.toString();
}

const previewUrl = computed(() => `${base}/preview?${queryString()}`);
const pdfUrl = computed(() => `${base}/pdf?${queryString()}`);
const pdfAllItemsUrl = computed(() => {
    const p = new URLSearchParams({ audience: 'student', template: cardTemplate.value });
    if (filters.school_id) p.set('school_id', filters.school_id);
    if (layout.value === 'team') p.set('layout', layout.value);
    return `${base}/pdf-all-items?${p.toString()}`;
});

async function loadPreview() {
    if (audience.value !== 'student') {
        previewCards.value = [];
        return;
    }

    if (cardScope.value === 'item' && !filters.item_id) {
        previewCards.value = [];
        return;
    }

    loading.value = true;
    try {
        const params = new URLSearchParams({ audience: 'student', scope: cardScope.value });
        if (filters.school_id) params.set('school_id', filters.school_id);
        if (cardScope.value === 'item' && filters.item_id) params.set('item_id', filters.item_id);
        if (cardScope.value === 'item' && layout.value === 'team' && selectedItemSupportsTeam.value) {
            params.set('layout', 'team');
        }
        const res = await fetch(`${base}/cards?${params.toString()}`, {
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

watch(audience, () => {
    if (audience.value === 'student') {
        loadPreview();
    } else {
        previewCards.value = [];
    }
});

watch(cardScope, () => {
    if (audience.value === 'student') {
        loadPreview();
    }
});
</script>
