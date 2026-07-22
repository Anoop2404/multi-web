<template>
    <AdminLayout title="Section Manager">
        <div class="space-y-6">
            <!-- Tenant selector -->
            <div class="card flex items-center gap-4">
                <label class="text-sm font-semibold text-gray-600">Editing site:</label>
                <select v-model="selectedTenantId" @change="loadSections"
                        class="border border-gray-200 rounded-lg px-3 py-2 text-sm flex-1 max-w-xs focus:outline-none focus:ring-2">
                    <option value="">— Select a tenant —</option>
                    <option v-for="t in tenants" :key="t.id" :value="t.id">{{ t.name }} ({{ t.type }})</option>
                </select>
                <button v-if="selectedTenantId" @click="addSection"
                        class="btn-primary ml-auto">
                    + Add Section
                </button>
            </div>

            <!-- Section list -->
            <div v-if="selectedTenantId" class="space-y-3">
                <div v-if="loading" class="text-center text-gray-400 py-8">Loading sections…</div>

                <div v-else-if="sections.length === 0"
                     class="card card--dashed p-10 text-center text-slate-400">
                    No sections yet. Click "Add Section" to begin building this site.
                </div>

                <div v-else>
                    <div v-for="(section, idx) in sections" :key="section.id"
                         class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-center gap-4 group hover:border-indigo-200 transition">
                        <!-- Drag handle -->
                        <div class="text-gray-300 cursor-grab select-none text-lg">⠿</div>

                        <!-- Section info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded text-gray-600">
                                    {{ section.section_type }}/{{ section.variant }}
                                </span>
                                <span :class="section.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                                      class="text-xs px-2 py-0.5 rounded-full font-medium">
                                    {{ section.is_active ? 'Active' : 'Hidden' }}
                                </span>
                            </div>
                            <p class="text-sm text-gray-500 truncate">
                                {{ sectionLabel(section) }}
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2 opacity-0 group-hover:opacity-100 transition">
                            <button @click="editSection(section)"
                                    class="text-xs px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium transition">
                                Edit
                            </button>
                            <button @click="toggleSection(section)"
                                    class="text-xs px-3 py-1.5 rounded-lg font-medium transition"
                                    :class="section.is_active
                                        ? 'bg-amber-50 text-amber-700 hover:bg-amber-100'
                                        : 'bg-green-50 text-green-700 hover:bg-green-100'">
                                {{ section.is_active ? 'Hide' : 'Show' }}
                            </button>
                            <button @click="deleteSection(section)"
                                    class="text-xs px-3 py-1.5 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 font-medium transition">
                                Delete
                            </button>
                        </div>

                        <!-- Order controls -->
                        <div class="flex flex-col gap-0.5">
                            <button @click="moveUp(idx)" :disabled="idx === 0"
                                    class="text-gray-300 hover:text-gray-600 disabled:opacity-20 transition text-xs">▲</button>
                            <button @click="moveDown(idx)" :disabled="idx === sections.length - 1"
                                    class="text-gray-300 hover:text-gray-600 disabled:opacity-20 transition text-xs">▼</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit / Add Modal -->
        <div v-if="modal.open" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
                <div class="p-6 border-b flex items-center justify-between">
                    <h3 class="font-bold text-gray-900">{{ modal.editing ? 'Edit Section' : 'Add Section' }}</h3>
                    <button @click="modal.open = false" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
                </div>
                <form @submit.prevent="saveSection" class="p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="form-label mb-1">Section Type</label>
                            <select v-model="modal.form.section_type"
                                    class="field">
                                <option v-for="t in sectionTypes" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label mb-1">Variant</label>
                            <select v-model="modal.form.variant"
                                    class="field">
                                <option v-for="v in variantsFor(modal.form.section_type)" :key="v" :value="v">{{ v }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label mb-1">Config</label>
                        <div class="flex gap-2 mb-2">
                            <button type="button" @click="modal.editMode = 'form'"
                                    :class="modal.editMode === 'form' ? ' text-white' : 'bg-gray-100 text-gray-600'"
                                    class="text-xs px-3 py-1 rounded-lg font-medium transition">Form</button>
                            <button type="button" @click="modal.editMode = 'json'"
                                    :class="modal.editMode === 'json' ? ' text-white' : 'bg-gray-100 text-gray-600'"
                                    class="text-xs px-3 py-1 rounded-lg font-medium transition">JSON</button>
                        </div>
                        <SectionConfigForm v-if="modal.editMode === 'form' && modal.fieldDefs.length"
                                           :fields="modal.fieldDefs"
                                           :config="modal.configObj"
                                           @update:config="onConfigFormUpdate" />
                        <p v-else-if="modal.editMode === 'form' && !modal.fieldDefs.length"
                           class="text-xs text-gray-400 bg-gray-50 rounded-lg p-3 mb-2">No form fields for this section type — use JSON.</p>
                        <textarea v-if="modal.editMode === 'json'" v-model="modal.configRaw" rows="10"
                                  class="w-full font-mono text-xs border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 resize-none">
                        </textarea>
                        <p v-if="modal.jsonError" class="text-xs text-red-500 mt-1">{{ modal.jsonError }}</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="is_active" v-model="modal.form.is_active" class="rounded">
                        <label for="is_active" class="text-sm text-gray-600">Active (visible on site)</label>
                    </div>

                    <div class="flex gap-3 pt-2">
                        <button type="submit"
                                class="btn-primary flex-1">
                            {{ modal.editing ? 'Save Changes' : 'Add Section' }}
                        </button>
                        <button type="button" @click="modal.open = false"
                                class="px-6 py-2.5 rounded-lg border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref, reactive, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import SectionConfigForm from '@/Components/SectionConfigForm.vue';

const props = defineProps({
    tenants: { type: Array, default: () => [] },
});

const selectedTenantId = ref('');
const sections = ref([]);
const loading = ref(false);
const sectionDefinitions = ref({});

fetch('/admin/api/section-definitions', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
    .then(r => r.json())
    .then(data => { sectionDefinitions.value = data; })
    .catch(() => {});

const sectionTypeMap = {
    hero:                 ['centered', 'split-image', 'video-bg', 'minimal', 'with-quicklinks', 'sahodaya-centered', 'event-promo', 'gradient-split', 'full-bleed', 'full-slider', 'cksc-slider'],
    about:               ['text-left', 'text-right', 'two-column', 'with-motto'],
    about_sahodaya:      ['single-column', 'with-stats', 'motto-hero'],
    principal_message:   ['card-style', 'full-width', 'with-management'],
    management:          ['photo-cards', 'table-list'],
    statistics:          ['counter-cards', 'horizontal-strip', 'with-achievements'],
    facilities:          ['icon-grid', 'image-cards', 'with-virtual-tour'],
    academic_programmes: ['tabs', 'cards', 'with-results'],
    staff:               ['photo-grid', 'table-list', 'department-tabs'],
    news:                ['grid', 'list', 'ticker', 'featured-plus-list'],
    events:              ['card-grid', 'timeline', 'list'],
    gallery:             ['masonry-grid', 'carousel', 'album-based'],
    video_gallery:       ['youtube-grid', 'featured-embed'],
    board_results:       ['toppers-cards', 'stats-plus-toppers', 'year-tabs'],
    achievements:        ['cards', 'timeline', 'badge-wall'],
    mandatory_disclosure:['structured', 'accordion'],
    admissions:          ['info-block', 'with-form', 'fee-structure'],
    downloads:           ['card-grid', 'category-tabs'],
    alumni:              ['registration-form', 'featured-grid'],
    house_system:        ['color-cards', 'with-points'],
    clubs:               ['icon-grid', 'with-photos'],
    portals:             ['quick-links'],
    testimonials:        ['carousel', 'card-grid'],
    career_guidance:     ['info-block'],
    publications:        ['download-cards'],
    atl:                 ['feature-block'],
    custom_page:         ['freeform'],
    contact:             ['side-by-side', 'stacked', 'with-whatsapp'],
    office_bearers:      ['photo-cards', 'table-list'],
    member_schools:      ['card-grid', 'table-list', 'map-view'],
    news_circulars:      ['grid', 'list'],
    events_programs:     ['cards', 'upcoming-cards', 'timeline'],
    kalotsav:            ['scoreboard', 'results-tabs', 'registration-cta'],
    circulars:           ['category-filter', 'accordion'],
    downloads_sahodaya:  ['sahodaya-grid'],
    governance:          ['structure'],
    timeline:            ['milestone'],
    testimonials_sahodaya:['principal-quotes'],
    job_vacancies:       ['listing'],
    sahodaya_home:       ['dashboard'],
    academic_quicklinks: ['year-tabs'],
    useful_links:        ['icon-grid'],
    programmes:          ['service-grid'],
};

const sectionTypes = Object.keys(sectionTypeMap).map(k => ({
    value: k,
    label: k.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
}));

function variantsFor(type) {
    return sectionTypeMap[type] ?? [];
}

function sectionLabel(section) {
    const cfg = section.config ?? {};
    return cfg.heading ?? cfg.title ?? section.section_type;
}

async function loadSections() {
    if (!selectedTenantId.value) { sections.value = []; return; }
    loading.value = true;
    try {
        const res = await fetch(`/admin/api/tenants/${selectedTenantId.value}/sections`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        sections.value = await res.json();
    } finally {
        loading.value = false;
    }
}

const modal = reactive({
    open: false,
    editing: null,
    form: { section_type: 'hero', variant: 'centered', is_active: true },
    configRaw: '{}',
    configObj: {},
    fieldDefs: [],
    editMode: 'form',
    jsonError: '',
});

function fieldsFor(type, variant) {
    return sectionDefinitions.value?.[type]?.[variant]?.fields ?? [];
}

function onConfigFormUpdate(config) {
    modal.configObj = config;
    modal.configRaw = JSON.stringify(config, null, 2);
}

watch(() => [modal.form.section_type, modal.form.variant], () => {
    modal.fieldDefs = fieldsFor(modal.form.section_type, modal.form.variant);
    if (modal.fieldDefs.length) {
        modal.editMode = 'form';
    }
});

function addSection() {
    modal.editing = null;
    modal.form = { section_type: 'hero', variant: 'centered', is_active: true };
    modal.configObj = { heading: '', tagline: '' };
    modal.configRaw = '{\n  "heading": "",\n  "tagline": ""\n}';
    modal.fieldDefs = fieldsFor(modal.form.section_type, modal.form.variant);
    modal.editMode = modal.fieldDefs.length ? 'form' : 'json';
    modal.jsonError = '';
    modal.open = true;
}

function editSection(section) {
    modal.editing = section;
    modal.form = { section_type: section.section_type, variant: section.variant, is_active: section.is_active };
    modal.configObj = { ...(section.config ?? {}) };
    modal.configRaw = JSON.stringify(section.config ?? {}, null, 2);
    modal.fieldDefs = fieldsFor(section.section_type, section.variant);
    modal.editMode = modal.fieldDefs.length ? 'form' : 'json';
    modal.jsonError = '';
    modal.open = true;
}

async function saveSection() {
    let config;
    if (modal.editMode === 'form' && modal.fieldDefs.length) {
        config = modal.configObj;
    } else {
        try {
            config = JSON.parse(modal.configRaw);
            modal.jsonError = '';
        } catch (e) {
            modal.jsonError = 'Invalid JSON: ' + e.message;
            return;
        }
    }

    const payload = { ...modal.form, config };

    const url = modal.editing
        ? `/admin/api/tenants/${selectedTenantId.value}/sections/${modal.editing.id}`
        : `/admin/api/tenants/${selectedTenantId.value}/sections`;

    const method = modal.editing ? 'PATCH' : 'POST';

    await fetch(url, {
        method,
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' },
        body: JSON.stringify(payload),
    });

    modal.open = false;
    await loadSections();
}

async function toggleSection(section) {
    await fetch(`/admin/api/tenants/${selectedTenantId.value}/sections/${section.id}/toggle`, {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' },
    });
    await loadSections();
}

async function deleteSection(section) {
    if (!confirm(`Delete "${sectionLabel(section)}"?`)) return;
    await fetch(`/admin/api/tenants/${selectedTenantId.value}/sections/${section.id}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' },
    });
    await loadSections();
}

async function moveUp(idx) {
    if (idx === 0) return;
    [sections.value[idx - 1], sections.value[idx]] = [sections.value[idx], sections.value[idx - 1]];
    await saveOrder();
}

async function moveDown(idx) {
    if (idx === sections.value.length - 1) return;
    [sections.value[idx], sections.value[idx + 1]] = [sections.value[idx + 1], sections.value[idx]];
    await saveOrder();
}

async function saveOrder() {
    const ids = sections.value.map(s => s.id);
    await fetch(`/admin/api/tenants/${selectedTenantId.value}/sections/reorder`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '' },
        body: JSON.stringify({ ids }),
    });
}
</script>
