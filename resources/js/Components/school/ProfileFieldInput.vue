<template>
    <SearchableSelect v-if="field.key === 'highest_class'"
            :model-value="modelValue"
            :options="highestClassSelectOptions"
            :all-option="true"
            all-label="—"
            :required="field.required"
            :disabled="field.disabled"
            @update:model-value="emit('update:modelValue', $event)" />
    <textarea v-else-if="field.key === 'address'"
              :value="modelValue"
              rows="3"
              :required="field.required"
              :disabled="field.disabled"
              :placeholder="field.placeholder"
              class="field resize-none"
              @input="emit('update:modelValue', $event.target.value)"></textarea>
    <input v-else
           :value="modelValue"
           :type="inputType"
           :required="field.required"
           :disabled="field.disabled"
           :placeholder="field.placeholder"
           :class="['field', field.key === 'school_prefix' ? 'uppercase font-mono' : '']"
           @input="emit('update:modelValue', $event.target.value)">
</template>

<script setup>
import { computed } from 'vue';
import SearchableSelect from '@/Components/ui/SearchableSelect.vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: String, default: '' },
    highestClassOptions: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['update:modelValue']);

const highestClassSelectOptions = computed(() =>
    Object.entries(props.highestClassOptions).map(([value, label]) => ({ value, label }))
);

const inputType = computed(() => {
    const key = props.field.key;
    if (key.endsWith('_email')) return 'email';
    if (key.endsWith('_phone') || key === 'phone') return 'tel';
    if (key === 'website') return 'url';
    return 'text';
});
</script>
