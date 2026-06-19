<template>
    <SahodayaAdminLayout title="Website Layout & Sections" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingSchoolsCount="pendingSchoolsCount"
                         :pendingSubmissionsCount="pendingSubmissionsCount"
                         :pendingPaymentsCount="pendingPaymentsCount">
        <div class="space-y-5 max-w-5xl">

            <!-- Toolbar -->
            <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-5 py-3.5 flex items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h2 class="font-bold text-gray-900">Page Sections</h2>
                    <span class="text-xs text-gray-400">{{ sections.length }} total · {{ sections.filter(s => s.is_active).length }} active</span>
                </div>
                <div class="flex items-center gap-3">
                    <a v-if="publicUrl" :href="publicUrl" target="_blank"
                       class="text-xs text-purple-600 hover:text-purple-800 font-semibold flex items-center gap-1">
                        Preview site ↗
                    </a>
                    <button @click="openAddModal"
                            class="flex items-center gap-2 px-4 py-2 bg-[#1e1b4b] hover:bg-[#312e81] text-white text-sm font-bold rounded-xl transition">
                        + Add Section
                    </button>
                </div>
            </div>

            <!-- Empty state -->
            <div v-if="sections.length === 0"
                 class="bg-white rounded-2xl border border-dashed border-gray-200 p-16 text-center">
                <div class="text-5xl mb-3">🏗️</div>
                <p class="font-semibold text-gray-600">No sections yet</p>
                <p class="text-sm text-gray-400 mt-1 mb-4">Build your website layout by adding sections below.</p>
                <button @click="openAddModal"
                        class="px-5 py-2.5 bg-[#1e1b4b] text-white text-sm font-bold rounded-xl hover:bg-[#312e81] transition">
                    + Add First Section
                </button>
            </div>

            <!-- Section cards -->
            <div class="space-y-3">
                <div v-for="(section, idx) in sections" :key="section.id"
                     class="bg-white rounded-2xl border shadow-sm transition"
                     :class="section.is_active ? 'border-gray-100' : 'border-gray-100 opacity-60'">

                    <!-- Card header row -->
                    <div class="px-5 py-4 flex items-center gap-4">
                        <!-- Reorder handles -->
                        <div class="flex flex-col gap-0.5 shrink-0">
                            <button @click="moveUp(idx)" :disabled="idx === 0"
                                    class="w-6 h-5 flex items-center justify-center text-gray-300 hover:text-gray-600 disabled:opacity-20 rounded transition text-xs font-bold">▲</button>
                            <button @click="moveDown(idx)" :disabled="idx === sections.length - 1"
                                    class="w-6 h-5 flex items-center justify-center text-gray-300 hover:text-gray-600 disabled:opacity-20 rounded transition text-xs font-bold">▼</button>
                        </div>

                        <!-- Type icon + labels -->
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg shrink-0"
                             :style="{ background: sectionColor(section.section_type) }">
                            {{ sectionIcon(section.section_type) }}
                        </div>

                        <div class="flex-1 min-w-0">
                            <div class="flex items-center flex-wrap gap-2 mb-0.5">
                                <span class="text-sm font-bold text-gray-900 capitalize">
                                    {{ sectionTypeLabel(section.section_type) }}
                                </span>
                                <span class="text-[11px] font-mono bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">{{ section.variant }}</span>
                                <span v-if="!section.is_active" class="text-[11px] font-semibold bg-gray-100 text-gray-400 px-2 py-0.5 rounded-full">Hidden</span>
                            </div>
                            <p class="text-xs text-gray-400 truncate">
                                {{ sectionPreview(section) }}
                            </p>
                        </div>

                        <!-- Action buttons -->
                        <div class="flex items-center gap-2 shrink-0">
                            <button @click="toggleActive(section)"
                                    class="text-xs font-semibold px-3 py-1.5 rounded-xl border transition"
                                    :class="section.is_active
                                        ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100'
                                        : 'border-green-200 bg-green-50 text-green-700 hover:bg-green-100'">
                                {{ section.is_active ? 'Hide' : 'Show' }}
                            </button>
                            <button @click="toggleEdit(section)"
                                    class="text-xs font-semibold px-3 py-1.5 rounded-xl border transition"
                                    :class="expandedId === section.id
                                        ? 'border-[#1e1b4b] bg-[#1e1b4b] text-white'
                                        : 'border-gray-200 bg-white text-gray-600 hover:bg-gray-50'">
                                {{ expandedId === section.id ? '↑ Close' : '✏️ Edit' }}
                            </button>
                            <button @click="removeSection(section)"
                                    class="text-xs font-semibold px-3 py-1.5 rounded-xl border border-red-100 bg-red-50 text-red-500 hover:bg-red-100 transition">
                                Delete
                            </button>
                        </div>
                    </div>

                    <!-- Inline editor (accordion) -->
                    <div v-if="expandedId === section.id"
                         class="border-t border-gray-100 bg-gray-50/50 rounded-b-2xl px-5 py-5 space-y-5">

                        <!-- Variant selector -->
                        <div class="flex flex-wrap items-end gap-4 pb-4 border-b border-gray-100">
                            <div>
                                <label class="block text-xs font-bold text-gray-600 mb-1.5">Layout Variant</label>
                                <div class="flex flex-wrap gap-2">
                                    <button v-for="v in variantsFor(section.section_type)" :key="v"
                                            type="button"
                                            @click="switchVariant(section, v)"
                                            :class="[
                                                'px-3 py-1.5 rounded-xl text-xs font-semibold border transition',
                                                section.variant === v
                                                    ? 'bg-[#1e1b4b] text-white border-[#1e1b4b]'
                                                    : 'bg-white text-gray-600 border-gray-200 hover:border-purple-300'
                                            ]">
                                        {{ v }}
                                    </button>
                                </div>
                            </div>
                            <div v-if="(section.archived_configs || []).length" class="ml-auto">
                                <select @change="restoreArchived(section, $event.target.value)"
                                        class="text-xs border border-gray-200 rounded-xl px-3 py-2 text-gray-500 bg-white focus:ring-2 focus:ring-purple-200 focus:outline-none">
                                    <option value="">↩ Restore previous content…</option>
                                    <option v-for="(arc, ai) in section.archived_configs" :key="ai"
                                            :value="ai">
                                        {{ arc.variant }} — {{ formatDate(arc.archived_at) }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Content fields -->
                        <div v-if="fieldsFor(section.section_type, section.variant).length">
                            <SectionFieldEditor
                                :fields="fieldsFor(section.section_type, section.variant)"
                                :config="editConfigs[section.id] || section.config || {}"
                                @update="val => editConfigs[section.id] = val" />
                        </div>
                        <div v-else class="bg-amber-50 border border-amber-100 rounded-xl px-4 py-3 text-xs text-amber-700">
                            This section has no configurable fields — its content comes from your database
                            (office bearers, member schools, events, etc.).
                        </div>

                        <!-- Save row -->
                        <div class="flex items-center gap-3 pt-2 border-t border-gray-100">
                            <button @click="saveSection(section)"
                                    :disabled="saving[section.id]"
                                    class="px-5 py-2.5 bg-[#1e1b4b] hover:bg-[#312e81] text-white text-sm font-bold rounded-xl transition disabled:opacity-50">
                                {{ saving[section.id] ? 'Saving…' : 'Save Changes' }}
                            </button>
                            <button @click="expandedId = null"
                                    class="px-4 py-2.5 border border-gray-200 text-sm text-gray-500 rounded-xl hover:bg-gray-50 transition">
                                Cancel
                            </button>
                            <a v-if="publicUrl" :href="publicUrl" target="_blank"
                               class="ml-auto text-xs text-purple-600 hover:underline font-semibold">
                                Preview changes ↗
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add section modal -->
        <Teleport to="body">
            <div v-if="addModal.open" class="fixed inset-0 bg-black/50 z-50 flex items-end sm:items-center justify-center p-4">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                    <div class="sticky top-0 bg-white border-b border-gray-100 px-6 py-4 flex items-center justify-between z-10">
                        <h3 class="font-bold text-gray-900 text-lg">Add Section</h3>
                        <button @click="addModal.open = false"
                                class="w-8 h-8 flex items-center justify-center rounded-xl hover:bg-gray-100 transition text-gray-400 text-xl">×</button>
                    </div>

                    <!-- Section type grid -->
                    <div class="p-6 space-y-5">
                        <div v-if="!addModal.selectedType">
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-3">Choose a section type</p>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                <button v-for="(variants, type) in sectionTypes" :key="type"
                                        @click="addModal.selectedType = type; addModal.selectedVariant = variants[0]"
                                        class="flex items-center gap-3 p-3 rounded-xl border border-gray-100 hover:border-purple-200 hover:bg-purple-50/50 transition text-left group">
                                    <span class="text-xl w-9 h-9 rounded-lg flex items-center justify-center bg-gray-50 group-hover:bg-purple-100 transition">
                                        {{ sectionIcon(type) }}
                                    </span>
                                    <span class="text-sm font-semibold text-gray-700">{{ sectionTypeLabel(type) }}</span>
                                </button>
                            </div>
                        </div>

                        <div v-else class="space-y-5">
                            <button @click="addModal.selectedType = null" class="text-xs text-purple-600 hover:underline font-semibold flex items-center gap-1">
                                ← Back
                            </button>
                            <div>
                                <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-3">
                                    Choose a design for {{ sectionTypeLabel(addModal.selectedType) }}
                                </p>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                    <button v-for="v in (sectionTypes[addModal.selectedType] || [])" :key="v"
                                            @click="addModal.selectedVariant = v"
                                            :class="[
                                                'p-4 rounded-xl border-2 text-left transition space-y-1',
                                                addModal.selectedVariant === v
                                                    ? 'border-[#1e1b4b] bg-[#1e1b4b]/5'
                                                    : 'border-gray-100 hover:border-purple-200'
                                            ]">
                                        <p class="text-xs font-bold text-gray-700 capitalize">{{ v.replace(/-/g,' ') }}</p>
                                        <p class="text-[11px] text-gray-400">
                                            {{ fieldDefs[addModal.selectedType]?.[v]?.description || '' }}
                                        </p>
                                    </button>
                                </div>
                            </div>

                            <div class="flex gap-3 pt-2 border-t border-gray-100">
                                <button @click="createSection" :disabled="!addModal.selectedVariant || addModal.saving"
                                        class="flex-1 py-3 bg-[#1e1b4b] hover:bg-[#312e81] text-white font-bold rounded-xl transition disabled:opacity-50 text-sm">
                                    {{ addModal.saving ? 'Adding…' : `Add ${sectionTypeLabel(addModal.selectedType)}` }}
                                </button>
                                <button @click="addModal.open = false"
                                        class="px-6 py-3 border border-gray-200 text-gray-600 rounded-xl hover:bg-gray-50 transition text-sm">
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { ref, reactive, computed, defineComponent, h } from 'vue';

const props = defineProps({
    sahodaya:                Object,
    publicUrl:               { type: String, default: null },
    pendingSchoolsCount:     { type: Number, default: 0 },
    pendingSubmissionsCount: { type: Number, default: 0 },
    pendingPaymentsCount:    { type: Number, default: 0 },
    sections:                { type: Array,  default: () => [] },
    sectionTypes:            { type: Object, default: () => ({}) },
    fieldDefs:               { type: Object, default: () => ({}) },
});

const sections    = ref([...(props.sections ?? [])]);
const expandedId  = ref(null);
const editConfigs = reactive({});
const saving      = reactive({});

const addModal = reactive({
    open: false, selectedType: null, selectedVariant: null, saving: false,
});

// ── Helpers ───────────────────────────────────────────────────────────────────

const iconMap = {
    hero: '🖼️', about_sahodaya: '📖', office_bearers: '👥', member_schools: '🏫',
    news_circulars: '📰', events_programs: '📅', kalotsav: '🏆', sports_meet: '🏅',
    statistics: '📊', programmes: '🎓', academic_quicklinks: '🔗', downloads_sahodaya: '📥',
    circulars: '📄', testimonials_sahodaya: '💬', useful_links: '🌐', gallery: '🖼',
    contact: '📞', newsletter: '📧', sahodaya_home: '🏠',
};
const colorMap = {
    hero: '#f3f0ff', about_sahodaya: '#f0fdf4', office_bearers: '#fdf4ff',
    member_schools: '#eff6ff', news_circulars: '#fffbeb', events_programs: '#f0fdf4',
    kalotsav: '#fdf4ff', sports_meet: '#f0fdfa', statistics: '#eff6ff',
    programmes: '#faf5ff', circulars: '#fefce8', contact: '#f0fdf4',
    newsletter: '#fdf2f8', gallery: '#f5f3ff',
};

function sectionIcon(type)  { return iconMap[type] ?? '⚡'; }
function sectionColor(type) { return colorMap[type] ?? '#f9fafb'; }
function sectionTypeLabel(type) {
    return (type ?? '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}
function variantsFor(type) { return props.sectionTypes[type] ?? []; }
function fieldsFor(type, variant) { return props.fieldDefs?.[type]?.[variant]?.fields ?? []; }
function sectionPreview(s) {
    const cfg = s.config ?? {};
    return cfg.heading ?? cfg.title ?? cfg.tagline ?? (fieldsFor(s.section_type, s.variant).length ? 'Click Edit to configure content' : 'Data-driven section');
}
function formatDate(d) {
    if (!d) return '';
    try { return new Date(d).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }); } catch { return ''; }
}

// ── API calls (using fetch directly — same pattern as super-admin builder) ───

function csrf() { return document.querySelector('meta[name=csrf-token]')?.content ?? ''; }
const baseUrl = computed(() => `/admin/api/tenants/${props.sahodaya.id}`);

async function apiPatch(path, body) {
    const r = await fetch(`${baseUrl.value}${path}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(body),
    });
    return r.json();
}
async function apiPost(path, body) {
    const r = await fetch(`${baseUrl.value}${path}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify(body),
    });
    return r.json();
}
async function apiDelete(path) {
    await fetch(`${baseUrl.value}${path}`, {
        method: 'DELETE',
        headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
    });
}

// ── Actions ───────────────────────────────────────────────────────────────────

function toggleEdit(section) {
    if (expandedId.value === section.id) {
        expandedId.value = null;
    } else {
        expandedId.value = section.id;
        if (!editConfigs[section.id]) {
            editConfigs[section.id] = { ...(section.config ?? {}) };
        }
    }
}

async function toggleActive(section) {
    const updated = await apiPost(`/sections/${section.id}/toggle`, {});
    Object.assign(section, updated);
}

async function saveSection(section) {
    saving[section.id] = true;
    try {
        const config = editConfigs[section.id] ?? section.config ?? {};
        const updated = await apiPatch(`/sections/${section.id}`, { config });
        const idx = sections.value.findIndex(s => s.id === section.id);
        if (idx !== -1) Object.assign(sections.value[idx], updated);
        expandedId.value = null;
    } finally {
        saving[section.id] = false;
    }
}

async function switchVariant(section, newVariant) {
    if (newVariant === section.variant) return;
    const msg = `Switch from "${section.variant}" to "${newVariant}"?\n\nCurrent content will be archived and you can restore it any time.`;
    if (!confirm(msg)) return;
    const updated = await apiPatch(`/sections/${section.id}`, { variant: newVariant });
    const idx = sections.value.findIndex(s => s.id === section.id);
    if (idx !== -1) Object.assign(sections.value[idx], updated);
    editConfigs[section.id] = {};
}

function restoreArchived(section, archiveIdx) {
    if (archiveIdx === '') return;
    const arc = section.archived_configs?.[archiveIdx];
    if (!arc) return;
    editConfigs[section.id] = { ...(arc.config ?? {}) };
}

async function removeSection(section) {
    if (!confirm(`Delete the "${sectionTypeLabel(section.section_type)} / ${section.variant}" section?\n\nThis cannot be undone.`)) return;
    await apiDelete(`/sections/${section.id}`);
    sections.value = sections.value.filter(s => s.id !== section.id);
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
    await apiPost('/sections/reorder', { ids: sections.value.map(s => s.id) });
}

function openAddModal() {
    addModal.open = true;
    addModal.selectedType = null;
    addModal.selectedVariant = null;
    addModal.saving = false;
}

async function createSection() {
    if (!addModal.selectedType || !addModal.selectedVariant) return;
    addModal.saving = true;
    try {
        const r = await fetch(`${baseUrl.value}/sections`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({
                section_type: addModal.selectedType,
                variant:      addModal.selectedVariant,
                config:       {},
                is_active:    false,
            }),
        });
        const newSection = await r.json();
        sections.value.push(newSection);
        addModal.open = false;
        // Auto-open editor for the new section
        expandedId.value = newSection.id;
        editConfigs[newSection.id] = {};
    } finally {
        addModal.saving = false;
    }
}

// ── Inline field editor component ─────────────────────────────────────────────

const SectionFieldEditor = defineComponent({
    props: {
        fields: Array,
        config: Object,
    },
    emits: ['update'],
    setup(props, { emit }) {
        const local = reactive({ ...(props.config ?? {}) });

        function onInput(key, val) {
            local[key] = val;
            emit('update', { ...local });
        }

        function onRepeaterAdd(key, itemDef) {
            if (!Array.isArray(local[key])) local[key] = [];
            const blank = Object.fromEntries((itemDef ?? []).map(f => [f.key, '']));
            local[key] = [...local[key], blank];
            emit('update', { ...local });
        }

        function onRepeaterRemove(key, idx) {
            local[key] = local[key].filter((_, i) => i !== idx);
            emit('update', { ...local });
        }

        function onRepeaterField(key, idx, fieldKey, val) {
            const arr = [...(local[key] ?? [])];
            arr[idx] = { ...arr[idx], [fieldKey]: val };
            local[key] = arr;
            emit('update', { ...local });
        }

        return () => h('div', { class: 'space-y-4' }, (props.fields ?? []).map(field => {
            if (field.type === 'repeater') {
                return h('div', { key: field.key, class: 'space-y-2' }, [
                    h('div', { class: 'flex items-center justify-between' }, [
                        h('label', { class: 'text-xs font-bold text-gray-700' }, field.label),
                        h('button', {
                            type: 'button',
                            onClick: () => onRepeaterAdd(field.key, field.fields),
                            class: 'text-xs text-purple-600 hover:text-purple-800 font-semibold',
                        }, '+ Add'),
                    ]),
                    ...(local[field.key] ?? []).map((item, idx) =>
                        h('div', { key: idx, class: 'border border-gray-200 bg-white rounded-xl p-3 space-y-2' }, [
                            h('div', { class: 'grid grid-cols-2 gap-2' },
                                (field.fields ?? []).map(sub =>
                                    h('div', { key: sub.key }, [
                                        h('label', { class: 'text-[11px] text-gray-400 font-medium' }, sub.label),
                                        sub.type === 'textarea'
                                            ? h('textarea', {
                                                rows: 2,
                                                value: item[sub.key] ?? '',
                                                onInput: e => onRepeaterField(field.key, idx, sub.key, e.target.value),
                                                class: 'w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:ring-purple-200 focus:outline-none',
                                            })
                                            : h('input', {
                                                type: sub.type === 'url' ? 'url' : sub.type === 'color' ? 'color' : 'text',
                                                value: item[sub.key] ?? '',
                                                onInput: e => onRepeaterField(field.key, idx, sub.key, e.target.value),
                                                class: 'w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:ring-2 focus:ring-purple-200 focus:outline-none',
                                            }),
                                    ])
                                )
                            ),
                            h('button', {
                                type: 'button',
                                onClick: () => onRepeaterRemove(field.key, idx),
                                class: 'text-xs text-red-400 hover:text-red-600',
                            }, 'Remove'),
                        ])
                    ),
                ]);
            }

            if (field.type === 'switch') {
                return h('label', { key: field.key, class: 'flex items-center gap-3 cursor-pointer' }, [
                    h('input', {
                        type: 'checkbox',
                        checked: !!local[field.key],
                        onChange: e => onInput(field.key, e.target.checked),
                        class: 'w-4 h-4 rounded text-purple-600',
                    }),
                    h('span', { class: 'text-sm font-medium text-gray-700' }, field.label),
                ]);
            }

            if (field.type === 'textarea' || field.type === 'wysiwyg') {
                return h('div', { key: field.key }, [
                    h('label', { class: 'block text-xs font-bold text-gray-700 mb-1.5' }, field.label),
                    h('textarea', {
                        rows: 3,
                        value: local[field.key] ?? '',
                        onInput: e => onInput(field.key, e.target.value),
                        class: 'w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-200 focus:outline-none',
                    }),
                ]);
            }

            if (field.type === 'select') {
                return h('div', { key: field.key }, [
                    h('label', { class: 'block text-xs font-bold text-gray-700 mb-1.5' }, field.label),
                    h('select', {
                        value: local[field.key] ?? field.default ?? '',
                        onChange: e => onInput(field.key, e.target.value),
                        class: 'w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-200 focus:outline-none bg-white',
                    }, (field.options ?? []).map(opt =>
                        h('option', { value: opt.value ?? opt, key: opt.value ?? opt }, opt.label ?? opt)
                    )),
                ]);
            }

            if (field.type === 'color') {
                return h('div', { key: field.key, class: 'flex items-center gap-3' }, [
                    h('label', { class: 'text-xs font-bold text-gray-700 flex-1' }, field.label),
                    h('div', { class: 'flex items-center gap-2' }, [
                        h('input', {
                            type: 'color',
                            value: local[field.key] ?? field.default ?? '#5b21b6',
                            onInput: e => onInput(field.key, e.target.value),
                            class: 'w-8 h-8 rounded-lg border border-gray-200 cursor-pointer',
                        }),
                        h('input', {
                            type: 'text',
                            value: local[field.key] ?? field.default ?? '#5b21b6',
                            onInput: e => onInput(field.key, e.target.value),
                            class: 'w-28 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono focus:ring-2 focus:ring-purple-200 focus:outline-none',
                        }),
                    ]),
                ]);
            }

            // Default: text / number / url / email / media
            return h('div', { key: field.key }, [
                h('label', { class: 'block text-xs font-bold text-gray-700 mb-1.5' }, [
                    field.label,
                    field.required ? h('span', { class: 'text-red-500 ml-0.5' }, '*') : null,
                ]),
                h('input', {
                    type: field.type === 'number' ? 'number'
                        : field.type === 'url' ? 'url'
                        : field.type === 'email' ? 'email'
                        : 'text',
                    value: local[field.key] ?? field.default ?? '',
                    placeholder: field.placeholder ?? '',
                    onInput: e => onInput(field.key, e.target.value),
                    class: 'w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-200 focus:outline-none',
                }),
            ]);
        }));
    },
});
</script>
