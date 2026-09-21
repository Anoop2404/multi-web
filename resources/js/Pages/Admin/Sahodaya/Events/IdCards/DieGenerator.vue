<template>
    <SahodayaEventsLayout :title="`${event.title} — Die Cut ID Cards`" :sahodaya="sahodaya" :event="event"
                          :publicUrl="publicUrl" :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader :title="`${event.title} — Die Cut ID Card Generator`" eyebrow="Bulk Die Output"
                    description="High-capacity bulk generator for die-cut card sheets. Built for 500–1,000 pages and 4,000+ students with volume batching and school-by-school downloads that never time out.">
            <template #actions>
                <div class="flex items-center gap-2">
                    <Link :href="standardIdCardsUrl" class="btn-secondary text-xs flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        Standard ID Cards
                    </Link>
                    <a :href="templatesUrl" target="_blank" class="btn-secondary text-xs flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Die Template Editor ↗
                    </a>
                </div>
            </template>
        </PageHeader>

        <!-- Region / Phase Switcher -->
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

        <!-- Metric Ribbon -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="card !p-4 bg-gradient-to-br from-indigo-50/50 to-white border-slate-200">
                <span class="text-xs font-semibold uppercase tracking-wider text-indigo-600">Total Participants</span>
                <div class="text-2xl font-black text-slate-800 mt-1">{{ totalParticipants.toLocaleString() }}</div>
                <span class="text-[11px] text-slate-500">Approved student entries</span>
            </div>
            <div class="card !p-4 bg-gradient-to-br from-emerald-50/50 to-white border-slate-200">
                <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Participating Schools</span>
                <div class="text-2xl font-black text-slate-800 mt-1">{{ totalSchools }}</div>
                <span class="text-[11px] text-slate-500">Separate page per school</span>
            </div>
            <div class="card !p-4 bg-gradient-to-br from-purple-50/50 to-white border-slate-200">
                <span class="text-xs font-semibold uppercase tracking-wider text-purple-600">Cards per Sheet</span>
                <div class="text-2xl font-black text-slate-800 mt-1">{{ perPage }} <span class="text-xs font-normal text-slate-500">cards / page</span></div>
                <span class="text-[11px] text-slate-500">{{ activeTemplate ? (activeTemplate.name || 'Custom die grid') : '2 × 2 landscape grid' }}</span>
            </div>
            <div class="card !p-4 bg-gradient-to-br from-amber-50/50 to-white border-slate-200">
                <span class="text-xs font-semibold uppercase tracking-wider text-amber-600">Estimated Die Sheets</span>
                <div class="text-2xl font-black text-slate-800 mt-1">{{ totalEstimatedPages.toLocaleString() }} <span class="text-xs font-normal text-slate-500">pages</span></div>
                <span class="text-[11px] text-slate-500">Total print volume</span>
            </div>
        </div>

        <!-- Volume Downloads for 500-1000 Pages -->
        <div class="card mb-6 border-2 border-indigo-100 shadow-sm overflow-hidden">
            <div class="p-5 bg-gradient-to-r from-indigo-900 to-slate-900 text-white flex flex-wrap items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-400 text-slate-950 uppercase tracking-wider">Zero-Timeout Architecture</span>
                        <h2 class="text-base font-bold">Volume Downloads (Recommended for Bulk Print Runs)</h2>
                    </div>
                    <p class="text-xs text-indigo-200 mt-1 max-w-3xl">
                        With 4,000+ students ({{ totalEstimatedPages }} pages), single-file downloads can hit Cloudflare's 100-second network limit. Volumes divide your schools into ~100-page batches that download cleanly in 12–18 seconds each.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="`${base}/die/pdf`" class="btn-secondary !bg-white/10 hover:!bg-white/20 !text-white !border-white/20 text-xs flex items-center gap-1.5" title="Generate one single PDF containing all schools">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        Full Run PDF (All {{ totalEstimatedPages }} Pages)
                    </a>
                </div>
            </div>

            <div v-if="volumes.length" class="p-5 grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 bg-slate-50/50">
                <div v-for="vol in volumes" :key="vol.volume"
                     class="p-4 rounded-xl border border-slate-200 bg-white hover:border-indigo-300 hover:shadow-md transition flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-2">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-extrabold bg-indigo-50 text-indigo-700">
                                Volume {{ vol.volume }}
                            </span>
                            <span class="text-xs font-bold text-slate-700">
                                {{ vol.page_count }} sheets
                            </span>
                        </div>
                        <div class="text-sm font-bold text-slate-900 line-clamp-1" :title="`${vol.school_from} → ${vol.school_to}`">
                            {{ vol.school_count }} Schools
                        </div>
                        <p class="text-[11px] text-slate-500 mt-1 line-clamp-2" :title="`${vol.school_from} → ${vol.school_to}`">
                            {{ vol.school_from }} … {{ vol.school_to }}
                        </p>
                        <div class="mt-3 flex items-center justify-between text-[11px] text-slate-600 bg-slate-50 p-2 rounded-lg">
                            <span>Students: <strong>{{ vol.student_count }}</strong></span>
                            <span>Sheets: <strong>{{ vol.page_count }}</strong></span>
                        </div>
                    </div>
                    <div class="mt-4 pt-3 border-t border-slate-100">
                        <a :href="volumeDownloadUrl(vol)"
                           class="btn-primary w-full text-xs py-2 text-center flex items-center justify-center gap-1.5 !bg-indigo-700 hover:!bg-indigo-800">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                            Download Vol. {{ vol.volume }} PDF
                        </a>
                    </div>
                </div>
            </div>
            <div v-else class="p-8 text-center text-sm text-slate-500">
                No approved participants found to construct volumes.
            </div>
        </div>

        <div class="grid lg:grid-cols-3 gap-6">
            <!-- Left: Live Die Sheet Preview -->
            <div class="lg:col-span-2 space-y-4">
                <div class="card space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-3">
                        <div>
                            <h3 class="section-title text-base">Die Sheet Visual Preview</h3>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Showing sample layout with {{ activePreviewCards.length }} cards placed in die cut slots for <strong>{{ currentSchoolName }}</strong>.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a :href="fullHtmlPreviewUrl" target="_blank" rel="noopener" class="btn-secondary text-xs flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                Full HTML Sheet ↗
                            </a>
                        </div>
                    </div>

                    <!-- School Picker for Preview -->
                    <div class="flex flex-wrap items-center justify-between gap-3 bg-slate-50 p-3 rounded-xl">
                        <div class="flex items-center gap-2">
                            <label class="text-xs font-bold uppercase tracking-wider text-slate-600">Preview School:</label>
                            <select v-model="selectedSchoolId" @change="loadSchoolPreview"
                                    class="text-xs font-semibold rounded-lg border-slate-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 max-w-xs">
                                <option v-for="s in schools" :key="s.school_id" :value="s.school_id">
                                    {{ s.school_code ? `[${s.school_code}] ` : '' }}{{ s.school_name }} ({{ s.participant_count }} cards)
                                </option>
                            </select>
                        </div>
                        <div class="flex items-center gap-1 text-xs text-slate-500">
                            <span>Sheet {{ currentSheetIndex + 1 }} of {{ totalSchoolSheets }}</span>
                            <button type="button" @click="prevSheet" :disabled="currentSheetIndex === 0"
                                    class="p-1 rounded border border-slate-200 bg-white hover:bg-slate-50 disabled:opacity-40">
                                ◀
                            </button>
                            <button type="button" @click="nextSheet" :disabled="currentSheetIndex >= totalSchoolSheets - 1"
                                    class="p-1 rounded border border-slate-200 bg-white hover:bg-slate-50 disabled:opacity-40">
                                ▶
                            </button>
                        </div>
                    </div>

                    <!-- Die Cut Sheet Mockup Canvas -->
                    <div class="relative w-full rounded-xl border border-slate-300 bg-slate-100 p-4 overflow-hidden shadow-inner flex flex-col items-center">
                        <div class="w-full text-center text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2 flex items-center justify-between px-2">
                            <span>Die Cut Paper Boundary</span>
                            <span>{{ activeTemplate ? `${activeTemplate.page_width_mm || 297} × ${activeTemplate.page_height_mm || 210} mm` : 'A4 Landscape (297 × 210 mm)' }}</span>
                        </div>

                        <!-- The A4 Sheet Box (2x2 Grid) -->
                        <div class="w-full bg-white rounded shadow-md border border-slate-200 p-4 transition-all"
                             :style="{ minHeight: '400px' }">
                            <div v-if="loadingPreview" class="py-24 text-center text-sm text-slate-400">
                                Loading cards for selected school…
                            </div>
                            <div v-else-if="activeSheetCards.length"
                                 class="grid sm:grid-cols-2 gap-4">
                                <div v-for="(card, index) in activeSheetCards" :key="index" class="relative group">
                                    <div class="absolute -top-2 -left-2 z-10 px-2 py-0.5 rounded-full text-[10px] font-extrabold bg-indigo-600 text-white shadow">
                                        Slot {{ index + 1 }}
                                    </div>
                                    <IdCardPreviewTile :card="card" :cluster-name="sahodaya.name"
                                                       :cluster-logo-url="sahodaya.logo_url"
                                                       :event-title="event.title" variant="pass" />
                                </div>
                            </div>
                            <div v-else class="py-24 text-center text-sm text-slate-400">
                                No cards available for this school.
                            </div>
                        </div>

                        <div class="mt-3 text-[11px] text-slate-500 flex items-center justify-between w-full px-2">
                            <span class="flex items-center gap-1 text-emerald-600 font-semibold">
                                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                                Die Grid Active ({{ perPage }} slots per sheet)
                            </span>
                            <span>Scale-adjusted for browser view</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: School-wise List & Downloads -->
            <div class="space-y-4">
                <div class="card space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="section-title text-sm">School-Wise Downloads</h3>
                        <span class="text-xs font-semibold text-slate-500">{{ filteredSchools.length }} schools</span>
                    </div>
                    <p class="text-xs text-slate-500">
                        Print individual school packets separately. Each download generates in 2–5 seconds with no timeout risk.
                    </p>

                    <!-- Search filter -->
                    <div>
                        <input v-model="schoolSearch" type="search" placeholder="Search school name or code…"
                               class="w-full text-xs rounded-lg border-slate-200 focus:border-indigo-500 focus:ring-indigo-500" />
                    </div>

                    <!-- School list -->
                    <div class="max-h-[36rem] overflow-y-auto divide-y divide-slate-100 pr-1 space-y-1">
                        <div v-for="s in filteredSchools" :key="s.school_id"
                             class="py-2.5 px-3 rounded-lg hover:bg-slate-50 flex items-center justify-between gap-3 transition">
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-slate-900 truncate" :title="s.school_name">
                                    {{ s.school_name }}
                                </div>
                                <div class="text-[11px] text-slate-500 flex items-center gap-2 mt-0.5">
                                    <span v-if="s.school_code" class="px-1.5 py-0.2 rounded bg-slate-100 font-mono text-[10px] text-slate-700 font-semibold">
                                        {{ s.school_code }}
                                    </span>
                                    <span>{{ s.participant_count }} cards</span>
                                    <span>·</span>
                                    <span>{{ s.page_count }} sheets</span>
                                </div>
                            </div>
                            <a :href="schoolDownloadUrl(s.school_id)"
                               class="btn-secondary !px-2.5 !py-1 text-xs whitespace-nowrap flex items-center gap-1 font-semibold text-indigo-700 hover:text-indigo-800">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                PDF
                            </a>
                        </div>
                        <div v-if="!filteredSchools.length" class="text-center py-6 text-xs text-slate-400">
                            No schools match your search.
                        </div>
                    </div>
                </div>

                <!-- Template info card -->
                <div class="card space-y-3 bg-slate-50 border-slate-200">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-700">Die Grid Specifications</h4>
                    <ul class="text-xs text-slate-600 space-y-1.5 list-disc pl-4">
                        <li><strong>Format:</strong> Landscape A4 / Custom Die Sheet</li>
                        <li><strong>Capacity:</strong> {{ perPage }} cards per page</li>
                        <li><strong>Separators:</strong> Every school starts on a clean sheet</li>
                        <li><strong>Back-end Engine:</strong> Chromium Headless PDF Converter</li>
                    </ul>
                    <div class="pt-2 border-t border-slate-200">
                        <Link :href="standardIdCardsUrl" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                            Switch to Standard Lanyard ID Cards →
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <EventPageActivityLog :logs="activityLogs" class="mt-8" />
    </SahodayaEventsLayout>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import EventPageActivityLog from '@/Components/sahodaya/EventPageActivityLog.vue';
import IdCardPreviewTile from '@/Components/fest/IdCardPreviewTile.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    sahodaya: Object,
    event: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    childEvents: { type: Array, default: () => [] },
    schools: { type: Array, default: () => [] },
    volumes: { type: Array, default: () => [] },
    totalParticipants: { type: Number, default: 0 },
    totalSchools: { type: Number, default: 0 },
    totalEstimatedPages: { type: Number, default: 0 },
    perPage: { type: Number, default: 4 },
    activeTemplate: { type: Object, default: null },
    previewCards: { type: Array, default: () => [] },
    previewSchoolName: { type: String, default: null },
    activityLogs: { type: Array, default: () => [] },
});

const base = `/sahodaya-admin/${props.sahodaya.id}/events/${props.event.id}/id-cards`;
const standardIdCardsUrl = base;
const templatesUrl = `/sahodaya-admin/${props.sahodaya.id}/id-card-templates`;

const schoolSearch = ref('');
const selectedSchoolId = ref(props.schools[0]?.school_id || '');
const currentSchoolName = ref(props.previewSchoolName || props.schools[0]?.school_name || 'School');
const activePreviewCards = ref([...props.previewCards]);
const currentSheetIndex = ref(0);
const loadingPreview = ref(false);

const filteredSchools = computed(() => {
    if (!schoolSearch.value.trim()) {
        return props.schools;
    }
    const q = schoolSearch.value.toLowerCase();
    return props.schools.filter(s =>
        (s.school_name && s.school_name.toLowerCase().includes(q)) ||
        (s.school_code && s.school_code.toLowerCase().includes(q))
    );
});

const totalSchoolSheets = computed(() => {
    return Math.max(1, Math.ceil(activePreviewCards.value.length / props.perPage));
});

const activeSheetCards = computed(() => {
    const start = currentSheetIndex.value * props.perPage;
    return activePreviewCards.value.slice(start, start + props.perPage);
});

const childEventOptions = computed(() => {
    return props.childEvents.map(e => ({ value: String(e.id), label: e.title }));
});

function switchSportEvent(value) {
    router.get(`/sahodaya-admin/${props.sahodaya.id}/events/${value}/id-cards/die`);
}

function prevSheet() {
    if (currentSheetIndex.value > 0) {
        currentSheetIndex.value--;
    }
}

function nextSheet() {
    if (currentSheetIndex.value < totalSchoolSheets.value - 1) {
        currentSheetIndex.value++;
    }
}

async function loadSchoolPreview() {
    const school = props.schools.find(s => s.school_id === selectedSchoolId.value);
    if (school) {
        currentSchoolName.value = school.school_name;
    }
    loadingPreview.value = true;
    currentSheetIndex.value = 0;
    try {
        const res = await fetch(`${base}/cards?audience=student&scope=event&school_id=${selectedSchoolId.value}`);
        if (res.ok) {
            const data = await res.json();
            activePreviewCards.value = data.cards || [];
        }
    } catch (e) {
        console.error('Failed to load cards for school preview', e);
    } finally {
        loadingPreview.value = false;
    }
}

onMounted(() => {
    if (activePreviewCards.value.length === 0 && selectedSchoolId.value) {
        loadSchoolPreview();
    }
});

function volumeDownloadUrl(vol) {
    const ids = (vol.school_ids || []).map(id => `school_ids[]=${encodeURIComponent(id)}`).join('&');
    return `${base}/die/pdf?volume=${vol.volume}&${ids}`;
}

function schoolDownloadUrl(schoolId) {
    return `${base}/die/pdf?school_id=${encodeURIComponent(schoolId)}`;
}

const fullHtmlPreviewUrl = computed(() => {
    const schoolParam = selectedSchoolId.value ? `&school_id=${encodeURIComponent(selectedSchoolId.value)}` : '';
    return `${base}/preview?template=pass&scope=event${schoolParam}`;
});
</script>
