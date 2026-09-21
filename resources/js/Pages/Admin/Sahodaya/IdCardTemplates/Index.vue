<template>
    <SahodayaEventsLayout title="ID Card Templates" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="ID card templates" eyebrow="Tools"
                    description="Upload a custom ID card background and place fields (photo, QR, name, etc.) on it. Scope to a specific event/item/audience, or leave blank for a Sahodaya-wide default." />

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mb-4 items-start">
        <form @submit.prevent="upload" class="card space-y-4 lg:col-span-7">
            <h3 class="section-title">{{ editingId ? 'Edit ID card template' : 'New ID card template' }}</h3>

            <FormGrid>
                <FormField label="Title">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.title" class="field" placeholder="e.g. Sports Meet 2026 — Student card">
                    </template>
                </FormField>

                <FormField label="Audience" hint="Leave blank to apply to all audiences.">
                    <template #default="{ id }">
                        <SearchableSelect
                            :id="id"
                            v-model="form.audience"
                            :options="[{ value: 'student', label: 'Student' }, { value: 'volunteer', label: 'Volunteer' }, { value: 'staff', label: 'Staff' }]"
                            :all-option="true"
                            all-label="All audiences"
                        />
                    </template>
                </FormField>

                <FormField label="Event" :hint="editingId ? 'Scope cannot be changed after creation — delete and recreate to change it.' : 'Leave blank for a Sahodaya-wide default.'">
                    <template #default="{ id }">
                        <SearchableSelect
                            :id="id"
                            v-model="form.event_id"
                            :options="festEventOptions"
                            :disabled="!!editingId"
                            :all-option="true"
                            all-label="All events (default)"
                            @change="form.item_id = null"
                        />
                    </template>
                </FormField>
                <FormField label="Item" hint="Leave blank to cover every item in the selected event.">
                    <template #default>
                        <SearchableSelect
                            v-model="form.item_id"
                            :options="selectedEventItemOptions"
                            :disabled="!!editingId || !form.event_id"
                            placeholder="All items in event"
                            search-placeholder="Type item name to search…"
                            all-label="All items in event"
                        />
                    </template>
                </FormField>

                <FormField label="Background (PDF or image)" class-extra="sm:col-span-2"
                           :hint="editingId ? 'Leave blank to keep the current background — only choose a file to replace it.' : 'PDF first page is converted to an image and used as the card backdrop.'">
                    <template #default="{ id }">
                        <div v-if="editingId && editingTemplate?.background_url" class="mb-2 flex items-center gap-2">
                            <img :src="editingTemplate.background_url" alt="Current background"
                                 class="h-16 w-auto rounded border border-slate-200 object-contain bg-slate-50">
                            <span class="text-xs text-slate-500">Current background</span>
                        </div>
                        <input :id="id" :key="fileInputKey" type="file" accept=".pdf,.png,.jpg,.jpeg" class="field"
                               @change="e => form.background = e.target.files[0]">
                    </template>
                </FormField>

                <FormField label="Card width (mm)">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="form.card_width_mm" type="number" min="40" max="150" class="field">
                    </template>
                </FormField>
                <FormField label="Card height (mm)">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="form.card_height_mm" type="number" min="40" max="150" class="field">
                    </template>
                </FormField>
                <FormField label="Cards per page (standard flow)" hint="Used only when no die-cut grid is set. A die-cut grid uses Columns × Rows instead.">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="form.cards_per_page" type="number" min="1" max="12" class="field">
                    </template>
                </FormField>

                <FormField label="Print page width (mm)" hint="Leave blank for A4 portrait. Set both to print on a custom sheet size.">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="form.page_width_mm" type="number" min="50" max="2000" step="0.01" placeholder="A4 (210)" class="field">
                    </template>
                </FormField>
                <FormField label="Print page height (mm)" hint="Leave blank for A4 portrait.">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="form.page_height_mm" type="number" min="50" max="2000" step="0.01" placeholder="A4 (297)" class="field">
                    </template>
                </FormField>

                <div class="sm:col-span-2 space-y-2 border rounded-lg p-3">
                    <p class="text-sm font-semibold text-slate-700">Die-cut grid (optional)</p>
                    <p class="text-xs text-slate-500">
                        Exact card placement for a physical die-cut sheet — overrides "cards per A4 page" above
                        with precise positions instead of a plain 2-per-row flow. Get these numbers from whoever
                        supplies the die-cutting template (or ask me to work them out from the die file). Leave
                        all six blank for the normal flow.
                    </p>
                    <div class="grid gap-2 sm:grid-cols-3">
                        <div>
                            <label class="text-[10px] uppercase text-slate-400">Columns</label>
                            <input v-model.number="form.grid_cols" type="number" min="1" max="20" class="field text-sm">
                        </div>
                        <div>
                            <label class="text-[10px] uppercase text-slate-400">Rows</label>
                            <input v-model.number="form.grid_rows" type="number" min="1" max="20" class="field text-sm">
                        </div>
                        <div></div>
                        <div>
                            <label class="text-[10px] uppercase text-slate-400">1st column center (mm)</label>
                            <input v-model.number="form.grid_first_col_center_mm" type="number" step="0.001" class="field text-sm">
                        </div>
                        <div>
                            <label class="text-[10px] uppercase text-slate-400">1st row center (mm)</label>
                            <input v-model.number="form.grid_first_row_center_mm" type="number" step="0.001" class="field text-sm">
                        </div>
                        <div></div>
                        <div>
                            <label class="text-[10px] uppercase text-slate-400">Column pitch (mm)</label>
                            <input v-model.number="form.grid_col_pitch_mm" type="number" step="0.001" class="field text-sm">
                        </div>
                        <div>
                            <label class="text-[10px] uppercase text-slate-400">Row pitch (mm)</label>
                            <input v-model.number="form.grid_row_pitch_mm" type="number" step="0.001" class="field text-sm">
                        </div>
                    </div>
                </div>

                <div class="sm:col-span-2 space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-700">Fields on the card</p>
                        <button type="button" class="btn-secondary text-xs" @click="addField">+ Add field</button>
                    </div>
                    <p class="text-xs text-slate-500 -mt-1">
                        Every design value below is stored with this template. Position and size values are percentages of the card.
                    </p>
                    <details v-for="(field, i) in form.fields" :key="`${field.key || 'field'}-${i}`"
                             class="group rounded-lg border border-slate-200 bg-white open:border-indigo-200 open:shadow-sm">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2.5">
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-slate-700">{{ field.key || `Field ${i + 1}` }}</span>
                                <span class="text-[11px] text-slate-400">{{ fieldTypeLabel(field.type) }}<template v-if="field.source"> · {{ field.source }}</template></span>
                            </span>
                            <span class="text-xs font-semibold text-indigo-600 group-open:rotate-180">⌄</span>
                        </summary>

                        <div class="grid gap-3 border-t border-slate-100 p-3 sm:grid-cols-6">
                            <div class="sm:col-span-2">
                                <label class="text-[10px] uppercase text-slate-400">Field type</label>
                                <SearchableSelect
                                    :model-value="field.type"
                                    :options="fieldTypeOptions"
                                    :all-option="false"
                                    placeholder="Select type"
                                    @update:model-value="value => changeFieldType(field, value)"
                                />
                            </div>
                            <div class="sm:col-span-2">
                                <label class="text-[10px] uppercase text-slate-400">Field key</label>
                                <input v-model.trim="field.key" type="text" maxlength="60" class="field text-sm" placeholder="e.g. school_code">
                            </div>
                            <div v-if="['text', 'item_row'].includes(field.type)" class="sm:col-span-2">
                                <label class="text-[10px] uppercase text-slate-400">Data source</label>
                                <SearchableSelect v-model="field.source" :options="dataSourceSelectOptions" :all-option="false" placeholder="Select data source" />
                            </div>
                            <div v-else-if="['photo', 'qr'].includes(field.type)" class="sm:col-span-2">
                                <label class="text-[10px] uppercase text-slate-400">Data source</label>
                                <input :value="field.type === 'photo' ? 'Participant photo' : 'QR code'" disabled class="field text-sm bg-slate-50">
                            </div>
                            <div v-else-if="field.type === 'static_text'" class="sm:col-span-2">
                                <label class="text-[10px] uppercase text-slate-400">Displayed text</label>
                                <input v-model="field.text" type="text" maxlength="120" class="field text-sm" placeholder="Static label">
                            </div>
                            <div v-if="field.type === 'item_row'">
                                <label class="text-[10px] uppercase text-slate-400">Item number</label>
                                <input v-model.number="field.row" type="number" min="1" max="20" class="field text-sm">
                            </div>

                            <div>
                                <label class="text-[10px] uppercase text-slate-400">Top %</label>
                                <input v-model.number="field.top" type="number" min="0" max="100" step="0.01" class="field text-sm">
                            </div>
                            <div>
                                <label class="text-[10px] uppercase text-slate-400">Left %</label>
                                <input v-model.number="field.left" type="number" min="0" max="100" step="0.01" class="field text-sm">
                            </div>
                            <div>
                                <label class="text-[10px] uppercase text-slate-400">Width %</label>
                                <input v-model.number="field.width" type="number" min="1" max="100" step="0.01" class="field text-sm">
                            </div>
                            <div>
                                <label class="text-[10px] uppercase text-slate-400">Height %</label>
                                <input v-model.number="field.height" type="number" min="1" max="100" step="0.01" class="field text-sm" placeholder="Auto">
                            </div>
                            <div>
                                <label class="text-[10px] uppercase text-slate-400">Rotation °</label>
                                <input v-model.number="field.rotation" type="number" min="-360" max="360" step="1" class="field text-sm" placeholder="0">
                            </div>

                            <template v-if="isTypographyField(field)">
                                <div>
                                    <label class="text-[10px] uppercase text-slate-400">Font size</label>
                                    <input v-model.number="field.font_size" type="number" min="5" max="48" class="field text-sm">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="text-[10px] uppercase text-slate-400">Font family</label>
                                    <SearchableSelect v-model="field.font_family" :options="fontFamilySelectOptions" :all-option="false" placeholder="Select font" />
                                </div>
                                <div>
                                    <label class="text-[10px] uppercase text-slate-400">Alignment</label>
                                    <SearchableSelect v-model="field.align" :options="alignmentOptions" :all-option="false" placeholder="Default" />
                                </div>
                                <div>
                                    <label class="text-[10px] uppercase text-slate-400">Line height</label>
                                    <input v-model.number="field.line_height" type="number" min="0.8" max="2" step="0.05" class="field text-sm" placeholder="1.25">
                                </div>
                                <div>
                                    <label class="text-[10px] uppercase text-slate-400">Text color</label>
                                    <div class="flex gap-2">
                                        <input :value="validHexColor(field.color) ? field.color : '#12345a'" type="color" class="h-10 w-10 rounded border border-slate-200 bg-white p-1"
                                               @input="field.color = $event.target.value">
                                        <input v-model.trim="field.color" type="text" maxlength="9" class="field min-w-0 text-sm" placeholder="#12345a">
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 sm:col-span-6">
                                    <label class="flex items-center gap-1.5 text-xs text-slate-600">
                                        <input type="checkbox" :checked="field.font_weight === 'bold'"
                                               @change="field.font_weight = $event.target.checked ? 'bold' : 'normal'">
                                        Bold
                                    </label>
                                    <label class="flex items-center gap-1.5 text-xs text-slate-600">
                                        <input type="checkbox" :checked="field.font_style === 'italic'"
                                               @change="field.font_style = $event.target.checked ? 'italic' : 'normal'">
                                        Italic
                                    </label>
                                    <label v-if="['text', 'static_text'].includes(field.type)" class="flex items-center gap-1.5 text-xs text-slate-600">
                                        <input v-model="field.wrap" type="checkbox">
                                        Allow multiple lines
                                    </label>
                                </div>
                            </template>

                            <template v-if="field.type === 'shape'">
                                <div>
                                    <label class="text-[10px] uppercase text-slate-400">Solid color</label>
                                    <input v-model.trim="field.color" type="text" maxlength="9" class="field text-sm" placeholder="#DCEBFB">
                                </div>
                                <div>
                                    <label class="text-[10px] uppercase text-slate-400">Gradient start</label>
                                    <input v-model.trim="field.gradient_from" type="text" maxlength="9" class="field text-sm" placeholder="#20B6F6">
                                </div>
                                <div>
                                    <label class="text-[10px] uppercase text-slate-400">Gradient end</label>
                                    <input v-model.trim="field.gradient_to" type="text" maxlength="9" class="field text-sm" placeholder="#7C3CF0">
                                </div>
                                <div>
                                    <label class="text-[10px] uppercase text-slate-400">Corner radius mm</label>
                                    <input v-model.number="field.radius" type="number" min="0" max="50" step="0.1" class="field text-sm">
                                </div>
                            </template>

                            <template v-if="field.type === 'divider'">
                                <div class="sm:col-span-2">
                                    <label class="text-[10px] uppercase text-slate-400">Orientation</label>
                                    <SearchableSelect v-model="field.orientation" :options="orientationOptions" :all-option="false" placeholder="Vertical" />
                                </div>
                                <div>
                                    <label class="text-[10px] uppercase text-slate-400">Line color</label>
                                    <input v-model.trim="field.color" type="text" maxlength="9" class="field text-sm" placeholder="#cbd5e1">
                                </div>
                            </template>

                            <div class="flex flex-wrap justify-end gap-3 border-t border-slate-100 pt-3 sm:col-span-6">
                                <button type="button" class="text-xs font-semibold text-slate-600 disabled:opacity-30" :disabled="i === 0" @click="moveField(i, -1)">Move up</button>
                                <button type="button" class="text-xs font-semibold text-slate-600 disabled:opacity-30" :disabled="i === form.fields.length - 1" @click="moveField(i, 1)">Move down</button>
                                <button type="button" class="text-xs font-semibold text-indigo-600" @click="duplicateField(i)">Duplicate</button>
                                <button type="button" class="text-xs font-semibold text-red-600" @click="removeField(i)">Remove</button>
                            </div>
                        </div>
                    </details>
                    <p v-if="!form.fields.length" class="text-xs text-slate-400">No fields yet — add at least a name and photo/QR field.</p>
                </div>

                <div class="sm:col-span-2">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input v-model="form.is_active" type="checkbox" class="rounded" :true-value="true" :false-value="false">
                        Active — use this template for this event + item + audience combination
                    </label>
                    <p class="text-xs text-slate-500 mt-1">
                        Only one template can be active per event + item + audience scope. Activating this one automatically deactivates any other matching template.
                    </p>
                </div>
            </FormGrid>

            <FormActions>
                <button type="submit" class="btn-primary" :disabled="form.processing">
                    {{ form.processing ? 'Saving…' : (editingId ? 'Update template' : 'Save template') }}
                </button>
                <button type="button" class="btn-secondary" @click="previewDraft('single')">
                    Preview card
                </button>
                <button v-if="form.grid_cols && form.grid_rows" type="button" class="btn-secondary" @click="previewDraft('die')">
                    Preview die sheet
                </button>
                <button v-if="editingId" type="button" class="btn-secondary" :disabled="form.processing" @click="cancelEdit">
                    Cancel edit
                </button>
            </FormActions>
            <p class="text-xs text-slate-500 -mt-2">
                "Preview card" / "Preview die sheet" opens the exact print output (server-rendered) in a new tab — use the live canvas on the right for instant feedback while you type.
            </p>
        </form>

        <div class="lg:col-span-5">
            <div class="sticky top-6 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-800 flex items-center gap-1.5">
                        <span>👁️</span> Live Visual Preview
                    </h3>
                    <span class="text-xs text-emerald-700 font-semibold bg-emerald-50 border border-emerald-200 px-2 py-0.5 rounded-full">
                        Updates as you type
                    </span>
                </div>
                <IdCardLiveCanvas
                    :background-url="editingTemplate?.background_url"
                    :local-file-url="localFileUrl"
                    :fields="form.fields"
                    :card-width-mm="form.card_width_mm"
                    :card-height-mm="form.card_height_mm"
                />
                <div class="p-3 bg-slate-50 border border-slate-200/90 rounded-xl space-y-1.5 text-xs text-slate-600">
                    <p class="font-bold text-slate-800">💡 Tip</p>
                    <p>Expand any field to adjust its content, layer, position, size, typography, colors, wrapping, or rotation. The canvas updates instantly.</p>
                </div>
            </div>
        </div>
        </div>

        <div class="form-section overflow-hidden !p-0">
            <div class="overflow-x-auto">
                <table class="data-table min-w-[720px]">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Scope</th>
                            <th>Audience</th>
                            <th>Background</th>
                            <th>Cards/page</th>
                            <th>Page size</th>
                            <th>Active</th>
                            <th class="w-32"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in templates" :key="t.id">
                            <td>{{ t.title || '—' }}</td>
                            <td class="text-xs text-slate-600">{{ scopeLabel(t) }}</td>
                            <td class="capitalize">{{ t.audience || 'All' }}</td>
                            <td>
                                <img v-if="t.background_url" :src="t.background_url" alt=""
                                     class="h-12 w-auto rounded border border-slate-200 object-contain bg-slate-50">
                                <span v-else class="text-slate-400 text-xs">None</span>
                            </td>
                            <td>{{ t.cards_per_page }}</td>
                            <td class="text-xs text-slate-600">
                                <span v-if="t.grid_json">{{ t.grid_json.cols }}×{{ t.grid_json.rows }} die grid</span>
                                <span v-else>{{ t.page_width_mm && t.page_height_mm ? `${t.page_width_mm}×${t.page_height_mm}mm` : 'A4' }}</span>
                            </td>
                            <td>{{ t.is_active ? 'Yes' : 'No' }}</td>
                            <td class="text-right space-x-3 whitespace-nowrap">
                                <a :href="previewUrl(t, 'single')" target="_blank" class="text-indigo-700 text-xs font-semibold hover:text-indigo-900">
                                    Preview card
                                </a>
                                <a v-if="t.grid_json" :href="previewUrl(t, 'die')" target="_blank" class="text-indigo-700 text-xs font-semibold hover:text-indigo-900">
                                    Preview die sheet
                                </a>
                                <button type="button" class="text-slate-700 text-xs font-semibold hover:text-slate-900" @click="editTemplate(t)">
                                    Edit
                                </button>
                                <button type="button" @click="remove(t)" class="text-red-600 text-xs font-semibold hover:text-red-800">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        <tr v-if="!templates.length">
                            <td colspan="7" class="p-6 text-center text-slate-400">No ID card templates yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaEventsLayout>
</template>

<script setup>
import { useForm, router } from '@inertiajs/vue3';
import { ref, computed, watch, onUnmounted } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import IdCardLiveCanvas from '@/Components/idcards/IdCardLiveCanvas.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    templates: { type: Array, default: () => [] },
    festEvents: { type: Array, default: () => [] },
    dataSourceOptions: { type: Object, default: () => ({}) },
    fontFamilyOptions: { type: Array, default: () => [] },
    defaultFields: { type: Array, default: () => [] },
});

const editingId = ref(null);
const { confirm } = useConfirm();
const editingTemplate = ref(null);
// Bumped whenever the background <input type=file> must forget its current
// selection — browsers refuse to let JS clear a file input's .files, so
// form.background = null alone leaves the native picker (and a since-stale
// File object) in place across edits/cancel/save; re-keying forces Vue to
// throw the old <input> away and mount a genuinely empty one.
const fileInputKey = ref(0);

function previewUrl(template, mode) {
    return `/sahodaya-admin/${props.sahodaya.id}/id-card-templates/${template.id}/preview?mode=${mode}`;
}

// A native <form target="_blank"> submit (not window.open, which popup blockers
// reject unless it happens perfectly synchronously inside the click) is the
// reliable way to open a new tab with a POST/multipart response — this posts the
// current, possibly-unsaved form state (including any newly-picked background
// file) and lets the browser open the rendered preview itself.
function previewDraft(mode) {
    const formEl = document.createElement('form');
    formEl.method = 'POST';
    formEl.action = `/sahodaya-admin/${props.sahodaya.id}/id-card-templates/preview-draft?mode=${mode}`;
    formEl.target = '_blank';
    formEl.enctype = 'multipart/form-data';
    formEl.style.display = 'none';

    const addField = (name, value) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = typeof value === 'boolean' ? (value ? '1' : '0') : (value ?? '');
        formEl.appendChild(input);
    };

    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    addField('_token', token);
    addField('title', form.title || '');
    if (editingId.value) addField('template_id', editingId.value);
    if (form.audience) addField('audience', form.audience);
    addField('card_width_mm', form.card_width_mm);
    addField('card_height_mm', form.card_height_mm);
    addField('page_width_mm', form.page_width_mm);
    addField('page_height_mm', form.page_height_mm);
    addField('grid_cols', form.grid_cols);
    addField('grid_rows', form.grid_rows);
    addField('grid_first_col_center_mm', form.grid_first_col_center_mm);
    addField('grid_first_row_center_mm', form.grid_first_row_center_mm);
    addField('grid_col_pitch_mm', form.grid_col_pitch_mm);
    addField('grid_row_pitch_mm', form.grid_row_pitch_mm);
    form.fields.forEach((field, i) => {
        Object.entries(field).forEach(([key, value]) => addField(`fields[${i}][${key}]`, value));
    });

    if (form.background) {
        const dt = new DataTransfer();
        dt.items.add(form.background);
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.name = 'background';
        fileInput.style.display = 'none';
        fileInput.files = dt.files;
        formEl.appendChild(fileInput);
    }

    document.body.appendChild(formEl);
    formEl.submit();
    document.body.removeChild(formEl);
}

const selectedEventItems = computed(() => {
    const event = props.festEvents.find(e => e.id === form.event_id);
    return event?.items || [];
});

const selectedEventItemOptions = computed(() => selectedEventItems.value.map(i => ({
    id: i.id,
    name: i.category_label ? `${i.title} — ${i.category_label}` : i.title,
})));

const festEventOptions = computed(() => props.festEvents.map(e => ({
    value: e.id,
    label: e.title,
})));

const dataSourceSelectOptions = computed(() => Object.entries(props.dataSourceOptions).map(([key, label]) => ({
    value: key,
    label,
})));

const fieldTypeOptions = [
    { value: 'text', label: 'Dynamic text' },
    { value: 'static_text', label: 'Static label' },
    { value: 'photo', label: 'Participant photo' },
    { value: 'qr', label: 'QR code' },
    { value: 'item_row', label: 'Participating-item row' },
    { value: 'shape', label: 'Shape / ribbon' },
    { value: 'divider', label: 'Divider line' },
];

const alignmentOptions = [
    { value: 'left', label: 'Left' },
    { value: 'center', label: 'Center' },
    { value: 'right', label: 'Right' },
];

const orientationOptions = [
    { value: 'vertical', label: 'Vertical' },
    { value: 'horizontal', label: 'Horizontal' },
];

const fontFamilySelectOptions = computed(() => props.fontFamilyOptions.map(font => ({
    value: font,
    label: font,
})));

function fieldTypeLabel(type) {
    return fieldTypeOptions.find(option => option.value === type)?.label || type || 'Unconfigured field';
}

function isTypographyField(field) {
    return ['text', 'static_text', 'item_row'].includes(field.type);
}

function validHexColor(color) {
    return /^#[0-9a-fA-F]{6}$/.test(color || '');
}

function changeFieldType(field, type) {
    field.type = type;

    if (type === 'photo') {
        field.source = 'photo_src';
        field.height ??= 26;
    } else if (type === 'qr') {
        field.source = 'qr_src';
        field.height ??= 14;
    } else if (type === 'text') {
        field.source = props.dataSourceOptions[field.source] ? field.source : 'name';
        field.font_size ??= 10;
        field.font_family ??= 'Arial';
    } else if (type === 'item_row') {
        field.row ??= 1;
        field.source = String(field.source || '').startsWith('item_row_') ? field.source : `item_row_${field.row}`;
        field.font_size ??= 8;
        field.font_family ??= 'Arial';
        field.height ??= 2.4;
    } else if (type === 'static_text') {
        field.text ??= 'Label';
        field.font_size ??= 9;
        field.font_family ??= 'Arial';
    } else if (type === 'shape') {
        field.height ??= 10;
        field.color ??= '#DCEBFB';
        field.radius ??= 0;
    } else if (type === 'divider') {
        field.orientation ??= 'vertical';
        field.height ??= 10;
        field.color ??= '#cbd5e1';
    }
}

function scopeLabel(t) {
    const event = props.festEvents.find(e => e.id === t.event_id);
    if (!event) return 'All events (default)';
    const item = event.items?.find(i => i.id === t.item_id);
    return item ? `${event.title} — ${item.title}` : `${event.title} (all items)`;
}

function blankFields() {
    return JSON.parse(JSON.stringify(props.defaultFields.length ? props.defaultFields : [
        { key: 'photo', type: 'photo', source: 'photo_src', top: 8, left: 4, width: 22, height: 26 },
        { key: 'qr', type: 'qr', source: 'qr_src', top: 4, left: 82, width: 14, height: 14 },
        { key: 'name', type: 'text', source: 'name', top: 10, left: 30, width: 65, font_size: 13, font_weight: 'bold' },
        { key: 'subtitle', type: 'text', source: 'subtitle', top: 22, left: 30, width: 65, font_size: 9 },
        { key: 'detail', type: 'text', source: 'detail', top: 30, left: 30, width: 65, font_size: 8 },
        { key: 'id_number', type: 'text', source: 'id_number', top: 80, left: 4, width: 45, font_size: 10, font_weight: 'bold' },
    ]));
}

const form = useForm({
    title: '',
    audience: null,
    event_id: null,
    item_id: null,
    background: null,
    card_width_mm: 96,
    card_height_mm: 72,
    cards_per_page: 4,
    page_width_mm: null,
    page_height_mm: null,
    grid_cols: null,
    grid_rows: null,
    grid_first_col_center_mm: null,
    grid_first_row_center_mm: null,
    grid_col_pitch_mm: null,
    grid_row_pitch_mm: null,
    fields: blankFields(),
    is_active: true,
});

// Object URL for a newly-picked (not yet uploaded) background file, so the live
// canvas can show it immediately — revoked whenever it's replaced or cleared to
// avoid leaking blob URLs across edits.
const localFileUrl = ref(null);
watch(() => form.background, (file) => {
    if (localFileUrl.value) URL.revokeObjectURL(localFileUrl.value);
    localFileUrl.value = file ? URL.createObjectURL(file) : null;
});
onUnmounted(() => {
    if (localFileUrl.value) URL.revokeObjectURL(localFileUrl.value);
});

function addField() {
    form.fields.push({
        key: `field_${form.fields.length + 1}`,
        type: 'text',
        source: 'name',
        top: 10,
        left: 10,
        width: 50,
        font_size: 10,
        font_family: 'Arial',
        font_weight: 'normal',
        font_style: 'normal',
        align: 'left',
        color: '#12345a',
        rotation: 0,
    });
}

function removeField(i) {
    form.fields.splice(i, 1);
}

function moveField(index, direction) {
    const destination = index + direction;
    if (destination < 0 || destination >= form.fields.length) return;
    const [field] = form.fields.splice(index, 1);
    form.fields.splice(destination, 0, field);
}

function duplicateField(index) {
    const copy = JSON.parse(JSON.stringify(form.fields[index]));
    copy.key = `${copy.key || 'field'}_copy`;
    form.fields.splice(index + 1, 0, copy);
}

function editTemplate(template) {
    editingId.value = template.id;
    editingTemplate.value = template;
    form.title = template.title || '';
    form.audience = template.audience || null;
    form.event_id = template.event_id || null;
    form.item_id = template.item_id || null;
    form.background = null;
    form.card_width_mm = template.card_width_mm || 96;
    form.card_height_mm = template.card_height_mm || 72;
    form.cards_per_page = template.cards_per_page || 4;
    form.page_width_mm = template.page_width_mm ?? null;
    form.page_height_mm = template.page_height_mm ?? null;
    form.grid_cols = template.grid_json?.cols ?? null;
    form.grid_rows = template.grid_json?.rows ?? null;
    form.grid_first_col_center_mm = template.grid_json?.first_col_center_mm ?? null;
    form.grid_first_row_center_mm = template.grid_json?.first_row_center_mm ?? null;
    form.grid_col_pitch_mm = template.grid_json?.col_pitch_mm ?? null;
    form.grid_row_pitch_mm = template.grid_json?.row_pitch_mm ?? null;
    form.fields = Array.isArray(template.layout_json) && template.layout_json.length
        ? JSON.parse(JSON.stringify(template.layout_json))
        : blankFields();
    form.is_active = template.is_active ?? true;
    fileInputKey.value++;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function cancelEdit() {
    editingId.value = null;
    editingTemplate.value = null;
    form.title = '';
    form.audience = null;
    form.event_id = null;
    form.item_id = null;
    form.background = null;
    form.card_width_mm = 96;
    form.card_height_mm = 72;
    form.cards_per_page = 4;
    form.page_width_mm = null;
    form.page_height_mm = null;
    form.grid_cols = null;
    form.grid_rows = null;
    form.grid_first_col_center_mm = null;
    form.grid_first_row_center_mm = null;
    form.grid_col_pitch_mm = null;
    form.grid_row_pitch_mm = null;
    form.fields = blankFields();
    form.is_active = true;
    form.clearErrors();
    fileInputKey.value++;
}

function upload() {
    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('background');
            fileInputKey.value++;
            if (editingId.value) cancelEdit();
        },
    };

    if (editingId.value) {
        form.transform((data) => ({ ...data, _method: 'put' }))
            .post(`/sahodaya-admin/${props.sahodaya.id}/id-card-templates/${editingId.value}`, options);
        return;
    }

    form.post(`/sahodaya-admin/${props.sahodaya.id}/id-card-templates`, options);
}

async function remove(template) {
    if (!(await confirm({ message: `Delete ID card template "${template.title || 'Untitled'}"?`, destructive: true }))) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/id-card-templates/${template.id}`, {
        preserveScroll: true,
    });
}
</script>
