<template>
    <div class="flex items-center gap-3">
        <div v-if="previewUrl" class="relative shrink-0">
            <img :src="previewUrl" alt="" class="h-16 w-16 rounded-lg border border-slate-200 object-contain bg-white">
        </div>
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-slate-200 bg-white px-2.5 py-1 font-semibold text-indigo-700 hover:border-indigo-300 hover:bg-indigo-50">
                <span aria-hidden="true">🖼</span> {{ previewUrl ? 'Replace image' : label }}
                <!-- sr-only, not hidden: the app's global file-input styles override display:none. -->
                <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="sr-only" @change="onPick">
            </label>
            <button v-if="previewUrl" type="button" class="font-semibold text-red-600 hover:underline" @click="remove">Remove</button>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/**
 * Picks one image. `modelValue` is the new File (or null); `existingUrl` is the stored image,
 * hidden once `removed` is set. The parent sends the file and the removed flag with the form.
 */
const props = defineProps({
    modelValue: { default: null },
    existingUrl: { type: String, default: null },
    removed: { type: Boolean, default: false },
    label: { type: String, default: 'Add image' },
});
const emit = defineEmits(['update:modelValue', 'update:removed']);

const objectUrl = ref(null);

watch(() => props.modelValue, (file) => {
    if (objectUrl.value) URL.revokeObjectURL(objectUrl.value);
    objectUrl.value = file ? URL.createObjectURL(file) : null;
}, { immediate: true });

onBeforeUnmount(() => {
    if (objectUrl.value) URL.revokeObjectURL(objectUrl.value);
});

const previewUrl = computed(() => objectUrl.value || (props.removed ? null : props.existingUrl));

function onPick(event) {
    const file = event.target.files?.[0] ?? null;
    event.target.value = '';
    if (!file) return;
    emit('update:modelValue', file);
    emit('update:removed', false);
}

function remove() {
    emit('update:modelValue', null);
    if (props.existingUrl) emit('update:removed', true);
}
</script>
