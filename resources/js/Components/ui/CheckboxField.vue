<template>
    <label class="choice-chip" :class="{ 'choice-chip--checked': modelValue, 'choice-chip--disabled': disabled }">
        <input
            type="checkbox"
            class="choice-chip-input"
            :checked="modelValue"
            :value="value"
            :disabled="disabled"
            @change="onChange"
        >
        <span class="choice-chip-label"><slot>{{ label }}</slot></span>
    </label>
</template>

<script setup>
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    value: { type: [String, Number, Boolean], default: true },
    label: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

function onChange(e) {
    emit('update:modelValue', e.target.checked);
}
</script>
