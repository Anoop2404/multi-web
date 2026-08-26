<template>
    <div class="reports-export-card mb-4">
        <div class="reports-export-card__head">
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="font-semibold text-sm text-slate-900">{{ exp.label }}</p>
                    <span class="reports-format-badge" :class="formatClass">{{ formatLabel }}</span>
                </div>
                <p v-if="missingParams" class="text-xs text-amber-700 mt-1">Select required filters below before downloading.</p>
            </div>
            <div class="flex flex-wrap gap-2 shrink-0">
                <a v-if="pdfPreviewHref" :href="pdfPreviewHref"
                   target="_blank" rel="noopener"
                   class="btn-secondary text-xs px-3 py-2">
                    Preview PDF ↗
                </a>
                <Link v-else-if="previewHref" :href="previewHref" class="btn-secondary text-xs px-3 py-2">
                    Preview
                </Link>
                <a :href="downloadHref"
                   target="_blank"
                   rel="noopener"
                   class="btn-primary text-xs px-3 py-2"
                   :class="{ 'opacity-50 pointer-events-none': missingParams }"
                   @click="missingParams && $event.preventDefault()">
                    {{ downloadLabel }}
                </a>
            </div>
        </div>
        <div v-if="exp.params?.length" class="reports-export-card__filters">
            <FormField v-if="exp.params.includes('school_id')" label="School" class-extra="min-w-[12rem] mb-0">
                <template #default="{ id }">
                    <SearchableSelect
                        :id="id"
                        :model-value="paramValues.school_id"
                        :options="schools"
                        all-label="All schools"
                        @update:model-value="value => emitParam('school_id', value)"
                    />
                </template>
            </FormField>
            <FormField v-if="exp.params.includes('item_id')" label="Item" class-extra="min-w-[12rem] mb-0">
                <template #default>
                    <SearchableSelect
                        :model-value="paramValues.item_id"
                        :options="itemOptions"
                        placeholder="Select item"
                        search-placeholder="Type item name to search…"
                        all-label="Select item"
                        @update:model-value="value => emitParam('item_id', value)"
                    />
                </template>
            </FormField>
            <FormField v-if="exp.params.includes('class_group')" label="Class" class-extra="min-w-[10rem] mb-0">
                <template #default="{ id }">
                    <SearchableSelect
                        :id="id"
                        :model-value="paramValues.class_group"
                        :options="classGroupOptions"
                        all-label="All"
                        @update:model-value="value => emitParam('class_group', value)"
                    />
                </template>
            </FormField>
            <FormField v-if="exp.params.includes('date')" label="Date" class-extra="mb-0">
                <template #default="{ id }">
                    <input :id="id" :value="paramValues.date" type="date" class="field text-sm"
                           @input="emitParam('date', $event.target.value)">
                </template>
            </FormField>
            <FormField v-if="exp.params.includes('top_n')" label="Top N" class-extra="mb-0">
                <template #default="{ id }">
                    <input :id="id" :value="paramValues.top_n" type="number" min="1" max="50" class="field w-24 text-sm"
                           @input="emitParam('top_n', $event.target.value)">
                </template>
            </FormField>
            <FormField v-if="exp.params.includes('head_id')" :label="isSports ? 'Sport Event' : 'Item head'" class-extra="min-w-[12rem] mb-0">
                <template #default="{ id }">
                    <SearchableSelect
                        :id="id"
                        :model-value="paramValues.head_id"
                        :options="heads"
                        :all-label="isSports ? 'All sport events' : 'All heads'"
                        @update:model-value="value => emitParam('head_id', value)"
                    />
                </template>
            </FormField>
            <FormField v-if="exp.params.includes('stage_id')" label="Stage" class-extra="min-w-[12rem] mb-0">
                <template #default="{ id }">
                    <SearchableSelect
                        :id="id"
                        :model-value="paramValues.stage_id"
                        :options="stages"
                        all-label="All stages"
                        @update:model-value="value => emitParam('stage_id', value)"
                    />
                </template>
            </FormField>
            <FormField v-if="exp.params.includes('audience')" label="Audience" class-extra="min-w-[10rem] mb-0">
                <template #default="{ id }">
                    <SearchableSelect
                        :id="id"
                        :model-value="paramValues.audience"
                        :options="[{ value: 'public', label: 'Public' }]"
                        all-label="Staff"
                        @update:model-value="value => emitParam('audience', value)"
                    />
                </template>
            </FormField>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';
import { previewHrefForExport, FORMAT_LABELS } from '@/support/festReportCatalog.js';

const props = defineProps({
    exp: { type: Object, required: true },
    reportsBase: { type: String, required: true },
    paramValues: { type: Object, default: () => ({}) },
    schools: { type: Array, default: () => [] },
    items: { type: Array, default: () => [] },
    heads: { type: Array, default: () => [] },
    stages: { type: Array, default: () => [] },
    classGroups: { type: Object, default: () => ({}) },
    downloadLabel: { type: String, default: 'Download' },
    isSports: { type: Boolean, default: false },
});

const emit = defineEmits(['update:param']);

function appendParams(href) {
    if (!href) return href;
    const [path, existing = ''] = href.split('?');
    const query = new URLSearchParams(existing);
    Object.entries(props.paramValues ?? {})
        .filter(([, value]) => value !== '' && value != null)
        .forEach(([key, value]) => query.set(key, value));
    const suffix = query.toString();
    return path + (suffix ? `?${suffix}` : '');
}

const previewHref = computed(() => appendParams(
    props.exp.previewHref ?? previewHrefForExport(props.exp.id, props.reportsBase),
));

const pdfPreviewHref = computed(() => {
    if (previewHref.value) return null;
    if (props.exp.format !== 'pdf') return null;
    return downloadHref.value + (downloadHref.value.includes('?') ? '&' : '?') + 'preview=1';
});

const itemOptions = computed(() => props.items.map(i => ({
    id: i.id,
    name: i.category_label ? `${i.title} — ${i.category_label}` : i.title,
})));

const classGroupOptions = computed(() => Object.entries(props.classGroups ?? {}).map(([key, label]) => ({
    value: key,
    label,
})));

const formatLabel = computed(() => FORMAT_LABELS[props.exp.format] ?? props.exp.format?.toUpperCase() ?? 'FILE');
const formatClass = computed(() => `reports-format-badge--${props.exp.format ?? 'pdf'}`);

const requiredParams = computed(() =>
    (props.exp.params ?? []).filter((p) => ['item_id'].includes(p)),
);

const missingParams = computed(() =>
    requiredParams.value.some((p) => !props.paramValues[p]),
);

const downloadHref = computed(() => {
    return appendParams(props.exp.href);
});

function emitParam(key, value) {
    emit('update:param', { key, value });
}
</script>
