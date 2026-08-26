<template>
    <form class="card !p-4 mb-4 flex flex-wrap gap-3 items-end" @submit.prevent="$emit('apply')">
        <FormField :label="resolvedLabel" class-extra="mb-0 min-w-[12rem]">
            <template #default="{ id }">
                <SearchableSelect
                    :id="id"
                    :model-value="modelValue"
                    :options="heads"
                    :all-option="true"
                    :all-label="resolvedAllLabel"
                    @update:model-value="onChange"
                />
            </template>
        </FormField>
        <FormField v-if="items.length" label="Item" class-extra="mb-0 min-w-[14rem]">
            <template #default="{ id }">
                <SearchableSelect
                    :id="id"
                    :model-value="itemId"
                    :options="items"
                    :all-option="true"
                    :all-label="itemAllLabel"
                    @update:model-value="onItemChange"
                />
            </template>
        </FormField>
        <button type="submit" class="btn-primary text-sm">Apply</button>
        <slot name="extra" />
    </form>
</template>

<script setup>
import { computed } from 'vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

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
const itemAllLabel = computed(() => `All items${props.modelValue ? ' in head' : ''}`);

const items = computed(() => {
    if (!props.modelValue) {
        return props.headItemGroups.flatMap((g) => g.items ?? []);
    }
    const group = props.headItemGroups.find((g) => String(g.head_id) === String(props.modelValue));
    return group?.items ?? [];
});

function onChange(value) {
    emit('update:modelValue', value);
    emit('update:itemId', '');
}

function onItemChange(value) {
    emit('update:itemId', value);
}
</script>
