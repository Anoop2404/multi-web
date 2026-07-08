<template>
    <div class="form-field" :class="classExtra">
        <label v-if="label" :id="labelId" :for="inputId" class="form-label">
            {{ label }}
            <span v-if="required" class="text-red-500" aria-hidden="true">*</span>
        </label>
        <div ref="controlRef" class="field-control" :class="{ 'field-control--error': error }">
            <slot :id="inputId" />
        </div>
        <p v-if="error" class="form-error" role="alert">{{ error }}</p>
        <p v-else-if="hint" class="form-hint">{{ hint }}</p>
    </div>
</template>

<script setup>
import { computed, useId, ref, onMounted, onUpdated, nextTick } from 'vue';

const props = defineProps({
    label: { type: String, default: '' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    classExtra: { type: String, default: '' },
    id: { type: String, default: '' },
});

const fallbackId = useId();
const inputId = computed(() => props.id || fallbackId);
const labelId = computed(() => `${inputId.value}-label`);
const controlRef = ref(null);

function bindControlIds() {
    if (!controlRef.value) return;
    const controls = controlRef.value.querySelectorAll('input, select, textarea');
    controls.forEach((el, index) => {
        if (!el.id) {
            el.id = index === 0 ? inputId.value : `${inputId.value}-${index}`;
        }
        if (props.label) {
            el.setAttribute('aria-labelledby', labelId.value);
        }
        if (!el.getAttribute('aria-label') && !el.labels?.length) {
            const labelText = props.label || props.hint || el.getAttribute('placeholder');
            if (labelText) {
                el.setAttribute('aria-label', labelText);
            }
        }
    });
}

onMounted(() => nextTick(bindControlIds));
onUpdated(() => nextTick(bindControlIds));
</script>
