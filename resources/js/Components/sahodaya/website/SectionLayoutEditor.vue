<template>
    <fieldset class="rounded-xl border border-gray-200 bg-white p-4"><legend class="px-2 text-xs font-bold uppercase tracking-wider text-gray-500">Section presentation</legend><div class="grid sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <label v-for="control in controls" :key="control.key" class="text-xs font-semibold text-gray-600">{{ control.label }}<select :value="modelValue?.[control.key] || control.default" @change="update(control.key, $event.target.value)" class="mt-1 block w-full rounded-lg border-gray-200 text-xs"><option v-for="option in control.options" :key="option" :value="option">{{ title(option) }}</option></select></label>
    </div></fieldset>
</template>
<script setup>
const props = defineProps({ modelValue: { type: Object, default: () => ({}) } });
const emit = defineEmits(['update:modelValue']);
const controls = [
    { key: 'width', label: 'Width', default: 'standard', options: ['narrow','standard','wide','full'] },
    { key: 'spacing', label: 'Spacing', default: 'standard', options: ['compact','standard','spacious'] },
    { key: 'surface', label: 'Surface', default: 'canvas', options: ['canvas','muted','primary','dark','image'] },
    { key: 'heading_alignment', label: 'Heading', default: 'left', options: ['left','center'] },
    { key: 'media_treatment', label: 'Media', default: 'natural', options: ['natural','framed','editorial','edge-to-edge'] },
];
function update(key, value) { emit('update:modelValue', { ...(props.modelValue || {}), [key]: value }); }
const title = value => value.replace(/-/g, ' ').replace(/\b\w/g, char => char.toUpperCase());
</script>
