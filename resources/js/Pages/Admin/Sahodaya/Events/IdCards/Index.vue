<template>
    <SahodayaEventsLayout :title="`${event.title} — ID Cards`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — ID Card Creator`" eyebrow="Output"
                    description="Generate ID cards for approved participants, plus staff and volunteer lanyards. Four cards per A4 sheet.">
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
                                @click="selectAudience(t.id)">
                            {{ t.label }}
                        </button>
                    </div>
                    <p class="text-xs text-slate-500">{{ activeType.hint }}</p>
                </div>

                <div v-if="audience === 'head' || audience === 'participant'" class="card space-y-4">
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

                    <div class="grid sm:grid-cols-2 gap-3">
                        <FormField label="School filter">
                            <select v-model="filters.school_id" class="field text-sm" @change="loadPreview">
                                <option value="">All schools</option>
                                <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </FormField>
                        <FormField label="Item filter (optional)">
                            <select v-model="filters.item_id" class="field text-sm" @change="loadPreview">
                                <option value="">All items</option>
                                <option v-for="item in items" :key="item.id" :value="item.id">{{ item.title }}</option>
                            </select>
                        </FormField>
                    </div>

                    <div v-if="loading" class="text-sm text-slate-500 py-6 text-center">Loading preview…</div>

                    <div v-else-if="previewCards.length" class="space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="section-title text-sm">2. Preview ({{ previewCards.length }} cards)</h3>
                            <p class="text-xs text-slate-500">Approved registrations only</p>
                        </div>
                        <div class="grid sm:grid-cols-2 gap-3 max-h-[32rem] overflow-y-auto pr-1">
                            <IdCardPreviewTile v-for="card in previewCards" :key="card.entity_id"
                                               :card="card" :cluster-name="sahodaya.name"
                                               :cluster-logo-url="sahodaya.logo_url"
                                               :event-title="event.title" :variant="cardTemplate" />
                        </div>
                    </div>

                    <EmptyState v-else title="No approved participants"
                                description="No approved participants found for the selected school or item filter." icon="🪪" class="py-8" />
                </div>

                <div v-else-if="audience === 'volunteer'" class="card space-y-4">
                    <p class="text-sm text-slate-600">
                        All volunteers from
                        <Link :href="settingsVolunteersUrl" class="link-brand font-semibold">Event settings → Volunteers</Link>.
                    </p>
                    <div v-if="loading" class="text-sm text-slate-500 py-6 text-center">Loading preview…</div>
                    <div v-else-if="previewCards.length" class="space-y-3">
                        <h3 class="section-title text-sm">Preview ({{ previewCards.length }} cards)</h3>
                        <div class="grid sm:grid-cols-2 gap-3 max-h-[32rem] overflow-y-auto pr-1">
                            <IdCardPreviewTile v-for="card in previewCards" :key="card.entity_id"
                                               :card="card" :cluster-name="sahodaya.name"
                                               :cluster-logo-url="sahodaya.logo_url"
                                               :event-title="event.title" :variant="cardTemplate" />
                        </div>
                    </div>
                    <EmptyState v-else title="No volunteers"
                                description="Add volunteers under Event settings first." icon="🪪" class="py-8" />
                </div>

                <div v-else class="card space-y-4">
                    <p class="text-sm text-slate-600">
                        All users assigned under
                        <Link :href="eventStaffUrl" class="link-brand font-semibold">Event staff</Link>.
                    </p>
                    <div v-if="loading" class="text-sm text-slate-500 py-6 text-center">Loading preview…</div>
                    <div v-else-if="previewCards.length" class="space-y-3">
                        <h3 class="section-title text-sm">Preview ({{ previewCards.length }} cards)</h3>
                        <div class="grid sm:grid-cols-2 gap-3 max-h-[32rem] overflow-y-auto pr-1">
                            <IdCardPreviewTile v-for="card in previewCards" :key="card.entity_id"
                                               :card="card" :cluster-name="sahodaya.name"
                                               :cluster-logo-url="sahodaya.logo_url"
                                               :event-title="event.title" :variant="cardTemplate" />
                        </div>
                    </div>
                    <EmptyState v-else title="No staff"
                                description="Assign event staff before generating cards." icon="🪪" class="py-8" />
                </div>
            </div>

            <aside class="space-y-4">
                <div class="card space-y-3">
                    <h3 class="section-title text-sm">Layout</h3>
                    <ul class="text-xs text-slate-600 space-y-1.5 list-disc pl-4">
                        <li><strong>Approved Participants</strong> — one card per student; items listed on card</li>
                        <li><strong>Volunteers</strong> — event-day volunteer passes</li>
                        <li><strong>Staff</strong> — portal users on event staff duty</li>
                        <li>99 × 85 mm landscape cards</li>
                        <li><strong>4 cards per A4 page</strong> (2 × 2 grid)</li>
                        <li>QR code for gate verification</li>
                    </ul>
                </div>

                <div class="card space-y-3">
                    <h3 class="section-title text-sm">Generate</h3>
                    <a :href="previewUrl" target="_blank" rel="noopener"
                       class="btn-secondary w-full text-sm text-center block"
                       :class="{ 'pointer-events-none opacity-50': !canGenerate }">
                        Preview in browser ↗
                    </a>
                    <a :href="pdfUrl" class="btn-primary w-full text-sm text-center block"
                       :class="{ 'pointer-events-none opacity-50': !canGenerate }">
                        Download PDF ↓
                    </a>
                </div>
            </aside>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import IdCardPreviewTile from '@/Components/fest/IdCardPreviewTile.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, items: { type: Array, default: () => [] }, meta: Object, schools: Array,
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/id-cards`;
const audience = ref('head');
const cardTemplate = ref('premium');
const filters = reactive({ school_id: '', item_id: '' });
const previewCards = ref([]);
const loading = ref(false);

const templates = [
    { id: 'premium', label: 'Premium' },
    { id: 'standard', label: 'Standard' },
];

const types = computed(() => [
    {
        id: 'head',
        label: 'Approved Participants',
        hint: 'Event participant passes for approved students/teachers. Four cards per A4 sheet.',
    },
    {
        id: 'volunteer',
        label: 'Volunteers',
        hint: 'Event-day volunteers from settings.',
    },
    {
        id: 'staff',
        label: 'Staff',
        hint: 'Portal users assigned as event staff.',
    },
]);

const activeType = computed(() => types.value.find(t => t.id === audience.value) ?? types.value[0]);

const canGenerate = computed(() => {
    if (audience.value === 'head' || audience.value === 'participant') {
        return true;
    }
    return (props.meta?.[audience.value === 'volunteer' ? 'volunteers' : 'staff'] ?? 0) > 0;
});

const settingsVolunteersUrl = computed(() =>
    `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/settings/volunteers`);
const eventStaffUrl = computed(() =>
    `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/event-staff`);

function apiAudience() {
    return audience.value === 'head' ? 'student' : audience.value;
}

function queryString() {
    const p = new URLSearchParams({ template: cardTemplate.value, audience: apiAudience() });
    if (audience.value === 'head' || audience.value === 'participant') {
        p.set('scope', 'event');
        if (filters.school_id) p.set('school_id', filters.school_id);
        if (filters.item_id) p.set('item_id', filters.item_id);
    }
    return p.toString();
}

const previewUrl = computed(() => `${base}/preview?${queryString()}`);
const pdfUrl = computed(() => `${base}/pdf?${queryString()}`);

function selectAudience(id) {
    audience.value = id;
    loadPreview();
}

async function loadPreview() {
    loading.value = true;
    try {
        const params = new URLSearchParams({ audience: apiAudience() });
        if (audience.value === 'head' || audience.value === 'participant') {
            params.set('scope', 'event');
            if (filters.school_id) params.set('school_id', filters.school_id);
            if (filters.item_id) params.set('item_id', filters.item_id);
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

onMounted(() => {
    loadPreview();
});
</script>
