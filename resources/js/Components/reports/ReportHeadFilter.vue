<template>
    <form class="card !p-4 mb-4 flex flex-wrap gap-3 items-end" @submit.prevent="$emit('apply')">
        <FormField :label="resolvedLabel" class-extra="mb-0 min-w-[12rem]">
            <template #default="{ id }">
                <select :id="id" :value="modelValue" class="field text-sm" @change="onChange">
                    <option value="">{{ resolvedAllLabel }}</option>
                    <option v-for="h in heads" :key="h.id" :value="h.id">{{ h.name }}</option>
                </select>
            </template>
        </FormField>
        <FormField v-if="items.length" label="Item" class-extra="mb-0 min-w-[14rem]">
            <template #default="{ id }">
                <select :id="id" :value="itemId" class="field text-sm" @change="onItemChange">
                    <option value="">All items{{ modelValue ? ' in head' : '' }}</option>
                    <option v-for="item in items" :key="item.id" :value="item.id">{{ item.title }}</option>
                </select>
            </template>
        </FormField>
        <button type="submit" class="btn-primary text-sm">Apply</button>
        <slot name="extra" />
    </form>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    itemId: { type: [String, Number], default: '' },
    heads: { type: Array, default: () => [] },
    headItemGroups: { type: Array, default: () => [] },
    label: { type: String, default: null },
    allLabel: { type: String, default: null },
    isSports: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'update:itemId', 'apply']);

const resolvedLabel = computed(() => props.label ?? (props.isSports ? 'Sport Event' : 'Item head'));
const resolvedAllLabel = computed(() => props.allLabel ?? (props.isSports ? 'All sport events' : 'All heads'));

const items = computed(() => {
    if (!props.modelValue) {
        return props.headItemGroups.flatMap((g) => g.items ?? []);
    }
    const group = props.headItemGroups.find((g) => String(g.head_id) === String(props.modelValue));
    return group?.items ?? [];
});

function onChange(e) {
    emit('update:modelValue', e.target.value);
    emit('update:itemId', '');
}

function onItemChange(e) {
    emit('update:itemId', e.target.value);
}
</script>
