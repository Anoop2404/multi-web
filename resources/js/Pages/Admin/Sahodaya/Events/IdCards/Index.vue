<template>
    <SahodayaEventsLayout :title="`${event.title} — ID Cards`" :sahodaya="sahodaya" :event="event"
                         :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — ID Card Creator`" eyebrow="Output"
                    description="Generate ID cards for approved participants, plus staff and volunteer lanyards. Four cards per A4 sheet.">
        </PageHeader>

        <!-- Region Switcher -->
        <div v-if="childEvents.length" class="card mb-6 !py-3.5 border-l-4 border-l-indigo-600 bg-gradient-to-r from-slate-50 to-white shadow-sm">
            <div class="flex flex-wrap gap-3 items-center justify-between">
                <div class="flex items-center gap-2.5">
                    <span class="p-1.5 rounded-md bg-indigo-50 text-indigo-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 002 2h1.5a2.5 2.5 0 002.5-2.5V7.865M19 12a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-600">
                        {{ event.event_type === 'sports' ? 'Select Sport Event / Region:' : 'Select Phase / Region:' }}
                    </label>
                </div>
                <SearchableSelect :model-value="String(event.id)" @update:model-value="switchSportEvent"
                                  :options="childEventOptions" :all-option="false"
                                  placeholder="Select event" class="w-72" />
            </div>
        </div>

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
                        <h3 class="section-title text-sm">1. Filters</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Generate ID lanyards for approved participants. All items for each participant are listed on the card.
                        </p>
                    </div>

                    <div class="grid sm:grid-cols-3 gap-3">
                        <FormField label="School filter">
                            <SearchableSelect v-model="filters.school_id" :options="schools"
                                :all-option="true" all-label="All schools"
                                search-placeholder="Type school name to search…"
                                @change="onSchoolChange" />
                        </FormField>
                        <FormField label="Student filter (optional)">
                            <SearchableSelect
                                v-model="filters.student_id"
                                :options="filteredStudents"
                                :all-option="true"
                                all-label="All students"
                                placeholder="All students"
                                search-placeholder="Type student name to search…"
                                @change="loadPreview"
                            />
                        </FormField>
                        <FormField label="Item filter (optional)">
                            <SearchableSelect
                                v-model="filters.item_id"
                                :options="itemOptions"
                                placeholder="All items"
                                search-placeholder="Type item name to search…"
                                all-label="All items"
                                @change="loadPreview"
                            />
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
                        <li>138 × 86 mm landscape cards</li>
                        <li><strong>4 cards per A4 page</strong> (2 × 2 grid)</li>
                        <li>Print on standard A4 paper</li>
                        <li>Cut along outer border guides</li>
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

                <div v-if="audience === 'head' || audience === 'participant'" class="card space-y-3">
                    <h3 class="section-title text-sm">Bulk downloads</h3>
                    <p class="text-xs text-slate-500">Generate bulk PDFs in the active template / die-cut layout. Each school starts on a separate page so sheets can be handed out cleanly.</p>
                    <Link :href="`${base}/die`" class="btn-primary w-full text-xs py-2 text-center block !bg-emerald-600 hover:!bg-emerald-700 text-white font-bold tracking-wide shadow-sm flex items-center justify-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"/></svg>
                        Dedicated Die Generator (500–1000 Pages) →
                    </Link>
                    <a :href="`${base}/pdf-all-schools?audience=student&template=pass&scope=event`" class="btn-primary w-full text-sm text-center block !bg-indigo-700 hover:!bg-indigo-800 text-white font-semibold">
                        All schools (die / separate pages) PDF ↓
                    </a>
                    <a :href="`${base}/pdf-all-items?audience=student&template=pass`" class="btn-secondary w-full text-sm text-center block">
                        All items — one PDF ↓
                    </a>
                    <a :href="`${base}/pdf-all-heads?audience=student&template=pass`" class="btn-secondary w-full text-sm text-center block">
                        All heads — one PDF ↓
                    </a>
                </div>
            </aside>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, reactive, ref, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import IdCardPreviewTile from '@/Components/fest/IdCardPreviewTile.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object, publicUrl: String, pendingPaymentsCount: Number,
    event: Object, items: { type: Array, default: () => [] }, meta: Object, schools: Array,
    students: { type: Array, default: () => [] },
    activityLogs: { type: Array, default: () => [] },
    childEvents: { type: Array, default: () => [] },
});

function switchSportEvent(value) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${value}/id-cards`);
}

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/id-cards`;
const audience = ref('head');
const cardTemplate = ref('pass');
const filters = reactive({ scope: 'event', school_id: '', student_id: '', item_id: '' });
const studentList = ref(props.students ?? []);
const previewCards = ref([]);
const loading = ref(false);

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

const itemOptions = computed(() => props.items.map(i => ({
    id: i.id,
    name: i.category_label ? `${i.title} — ${i.category_label}` : i.title,
})));

const childEventOptions = computed(() => props.childEvents.map(ev => ({
    value: String(ev.id),
    label: ev.short_title || ev.title,
})));

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

const filteredStudents = computed(() => studentList.value);

async function onSchoolChange() {
    filters.student_id = '';
    if (filters.school_id) {
        try {
            const res = await fetch(`${base}/students?school_id=${encodeURIComponent(filters.school_id)}`);
            const data = await res.json();
            studentList.value = data.students ?? [];
        } catch {
            studentList.value = (props.students ?? []).filter(s => String(s.school_id) === String(filters.school_id));
        }
    } else {
        studentList.value = props.students ?? [];
    }
    loadPreview();
}

function queryString() {
    const p = new URLSearchParams({ template: cardTemplate.value, audience: apiAudience() });
    if (audience.value === 'head' || audience.value === 'participant') {
        p.set('scope', 'event');
        if (filters.school_id) p.set('school_id', filters.school_id);
        if (filters.student_id) p.set('student_id', filters.student_id);
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
            if (filters.student_id) params.set('student_id', filters.student_id);
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
