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
                <Link v-if="previewHref" :href="previewHref" class="btn-secondary text-xs px-3 py-2">
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
                    <select :id="id" :value="paramValues.school_id" class="field text-sm"
                            @change="emitParam('school_id', $event.target.value)">
                        <option value="">All schools</option>
                        <option v-for="s in schools" :key="s.id" :value="s.id">{{ s.name }}</option>
                    </select>
                </template>
            </FormField>
            <FormField v-if="exp.params.includes('item_id')" label="Item" class-extra="min-w-[12rem] mb-0">
                <template #default="{ id }">
                    <select :id="id" :value="paramValues.item_id" class="field text-sm"
                            @change="emitParam('item_id', $event.target.value)">
                        <option value="">Select item</option>
                        <option v-for="i in items" :key="i.id" :value="i.id">{{ i.title }}</option>
                    </select>
                </template>
            </FormField>
            <FormField v-if="exp.params.includes('class_group')" label="Class" class-extra="min-w-[10rem] mb-0">
                <template #default="{ id }">
                    <select :id="id" :value="paramValues.class_group" class="field text-sm"
                            @change="emitParam('class_group', $event.target.value)">
                        <option value="">All</option>
                        <option v-for="(label, key) in classGroups" :key="key" :value="key">{{ label }}</option>
                    </select>
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
                    <select :id="id" :value="paramValues.head_id" class="field text-sm"
                            @change="emitParam('head_id', $event.target.value)">
                        <option value="">{{ isSports ? 'All sport events' : 'All heads' }}</option>
                        <option v-for="h in heads" :key="h.id" :value="h.id">{{ h.name }}</option>
                    </select>
                </template>
            </FormField>
            <FormField v-if="exp.params.includes('stage_id')" label="Stage" class-extra="min-w-[12rem] mb-0">
                <template #default="{ id }">
                    <select :id="id" :value="paramValues.stage_id" class="field text-sm"
                            @change="emitParam('stage_id', $event.target.value)">
                        <option value="">All stages</option>
                        <option v-for="st in stages" :key="st.id" :value="st.id">{{ st.name }}</option>
                    </select>
                </template>
            </FormField>
            <FormField v-if="exp.params.includes('audience')" label="Audience" class-extra="min-w-[10rem] mb-0">
                <template #default="{ id }">
                    <select :id="id" :value="paramValues.audience" class="field text-sm"
                            @change="emitParam('audience', $event.target.value)">
                        <option value="">Staff</option>
                        <option value="public">Public</option>
                    </select>
                </template>
            </FormField>
        </div>
    </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
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

const previewHref = computed(() =>
    props.exp.previewHref ?? previewHrefForExport(props.exp.id, props.reportsBase),
);

const formatLabel = computed(() => FORMAT_LABELS[props.exp.format] ?? props.exp.format?.toUpperCase() ?? 'FILE');
const formatClass = computed(() => `reports-format-badge--${props.exp.format ?? 'pdf'}`);

const requiredParams = computed(() =>
    (props.exp.params ?? []).filter((p) => ['item_id'].includes(p)),
);

const missingParams = computed(() =>
    requiredParams.value.some((p) => !props.paramValues[p]),
);

const downloadHref = computed(() => {
    const q = new URLSearchParams(
        Object.fromEntries(
            Object.entries(props.paramValues ?? {}).filter(([, v]) => v !== '' && v != null),
        ),
    );
    const qs = q.toString();
    return props.exp.href + (qs ? `?${qs}` : '');
});

function emitParam(key, value) {
    emit('update:param', { key, value });
}
</script>
