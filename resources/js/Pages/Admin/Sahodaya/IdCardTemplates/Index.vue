<template>
    <SahodayaEventsLayout title="ID Card Templates" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="ID card templates" eyebrow="Tools"
                    description="Upload a custom ID card background and place fields (photo, QR, name, etc.) on it. Scope to a specific event/item/audience, or leave blank for a Sahodaya-wide default." />

        <form @submit.prevent="upload" class="card mb-4 space-y-4">
            <h3 class="section-title">{{ editingId ? 'Edit ID card template' : 'New ID card template' }}</h3>

            <FormGrid>
                <FormField label="Title">
                    <template #default="{ id }">
                        <input :id="id" v-model="form.title" class="field" placeholder="e.g. Sports Meet 2026 — Student card">
                    </template>
                </FormField>

                <FormField label="Audience" hint="Leave blank to apply to all audiences.">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.audience" class="field">
                            <option :value="null">All audiences</option>
                            <option value="student">Student</option>
                            <option value="volunteer">Volunteer</option>
                            <option value="staff">Staff</option>
                        </select>
                    </template>
                </FormField>

                <FormField label="Event" :hint="editingId ? 'Scope cannot be changed after creation — delete and recreate to change it.' : 'Leave blank for a Sahodaya-wide default.'">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.event_id" class="field" :disabled="!!editingId" @change="form.item_id = null">
                            <option :value="null">All events (default)</option>
                            <option v-for="e in festEvents" :key="e.id" :value="e.id">{{ e.title }}</option>
                        </select>
                    </template>
                </FormField>
                <FormField label="Item" hint="Leave blank to cover every item in the selected event.">
                    <template #default="{ id }">
                        <select :id="id" v-model="form.item_id" class="field" :disabled="!!editingId || !form.event_id">
                            <option :value="null">All items in event</option>
                            <option v-for="i in selectedEventItems" :key="i.id" :value="i.id">{{ i.title }}</option>
                        </select>
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
                        <input :id="id" type="file" accept=".pdf,.png,.jpg,.jpeg" class="field"
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
                <FormField label="Cards per A4 page" hint="Arranged 2 per row.">
                    <template #default="{ id }">
                        <input :id="id" v-model.number="form.cards_per_page" type="number" min="1" max="12" class="field">
                    </template>
                </FormField>

                <div class="sm:col-span-2 space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-slate-700">Fields on the card</p>
                        <button type="button" class="btn-secondary text-xs" @click="addField">+ Add field</button>
                    </div>
                    <p class="text-xs text-slate-500 -mt-1">
                        Position values are % of the card (0–100). Photo and QR fields also use width/height %.
                    </p>
                    <div v-for="(field, i) in form.fields" :key="i" class="grid gap-2 sm:grid-cols-6 border rounded-lg p-3 items-end">
                        <div class="sm:col-span-2">
                            <label class="text-[10px] uppercase text-slate-400">Type</label>
                            <select v-model="field.type" class="field text-sm">
                                <option value="text">Text</option>
                                <option value="photo">Photo</option>
                                <option value="qr">QR code</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="text-[10px] uppercase text-slate-400">Data source</label>
                            <select v-if="field.type === 'text'" v-model="field.source" class="field text-sm">
                                <option v-for="(label, key) in dataSourceOptions" :key="key" :value="key">{{ label }}</option>
                            </select>
                            <select v-else v-model="field.source" class="field text-sm">
                                <option :value="field.type === 'photo' ? 'photo_src' : 'qr_src'">
                                    {{ field.type === 'photo' ? 'Participant photo' : 'QR code' }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="text-[10px] uppercase text-slate-400">Top %</label>
                            <input v-model.number="field.top" type="number" min="0" max="100" class="field text-sm">
                        </div>
                        <div>
                            <label class="text-[10px] uppercase text-slate-400">Left %</label>
                            <input v-model.number="field.left" type="number" min="0" max="100" class="field text-sm">
                        </div>
                        <div>
                            <label class="text-[10px] uppercase text-slate-400">Width %</label>
                            <input v-model.number="field.width" type="number" min="1" max="100" class="field text-sm">
                        </div>
                        <div v-if="field.type !== 'text'">
                            <label class="text-[10px] uppercase text-slate-400">Height %</label>
                            <input v-model.number="field.height" type="number" min="1" max="100" class="field text-sm">
                        </div>
                        <div v-if="field.type === 'text'">
                            <label class="text-[10px] uppercase text-slate-400">Font size</label>
                            <input v-model.number="field.font_size" type="number" min="5" max="48" class="field text-sm">
                        </div>
                        <div v-if="field.type === 'text'" class="flex items-center gap-3 pt-4">
                            <label class="flex items-center gap-1 text-xs">
                                <input type="checkbox" :checked="field.font_weight === 'bold'"
                                       @change="field.font_weight = $event.target.checked ? 'bold' : 'normal'">
                                Bold
                            </label>
                        </div>
                        <div class="sm:col-span-6 text-right">
                            <button type="button" class="text-red-600 text-xs font-semibold" @click="removeField(i)">Remove field</button>
                        </div>
                    </div>
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
                <button v-if="editingId" type="button" class="btn-secondary" :disabled="form.processing" @click="cancelEdit">
                    Cancel edit
                </button>
            </FormActions>
        </form>

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
                            <td>{{ t.is_active ? 'Yes' : 'No' }}</td>
                            <td class="text-right space-x-3">
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
import { ref, computed } from 'vue';
import SahodayaEventsLayout from '@/Layouts/SahodayaEventsLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    templates: { type: Array, default: () => [] },
    festEvents: { type: Array, default: () => [] },
    dataSourceOptions: { type: Object, default: () => ({}) },
    defaultFields: { type: Array, default: () => [] },
});

const editingId = ref(null);
const editingTemplate = ref(null);

const selectedEventItems = computed(() => {
    const event = props.festEvents.find(e => e.id === form.event_id);
    return event?.items || [];
});

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
    fields: blankFields(),
    is_active: true,
});

function addField() {
    form.fields.push({ key: '', type: 'text', source: 'name', top: 10, left: 10, width: 50, height: 15, font_size: 10, font_weight: 'normal' });
}

function removeField(i) {
    form.fields.splice(i, 1);
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
    form.fields = Array.isArray(template.layout_json) && template.layout_json.length
        ? JSON.parse(JSON.stringify(template.layout_json))
        : blankFields();
    form.is_active = template.is_active ?? true;
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
    form.fields = blankFields();
    form.is_active = true;
    form.clearErrors();
}

function upload() {
    const options = {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('background');
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

function remove(template) {
    if (!confirm(`Delete ID card template "${template.title || 'Untitled'}"?`)) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/id-card-templates/${template.id}`, {
        preserveScroll: true,
    });
}
</script>
