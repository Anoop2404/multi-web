<template>
    <div class="flex items-center gap-2">
        <div class="w-10 h-10 rounded-full overflow-hidden border border-gray-200 bg-gray-100 shrink-0 flex items-center justify-center">
            <img v-if="previewUrl" :src="previewUrl" alt="" class="w-full h-full object-cover">
            <span v-else class="text-[10px] text-gray-400 font-semibold">Photo</span>
        </div>
        <label class="cursor-pointer text-[10px] font-semibold text-[#0f3d7a] hover:underline whitespace-nowrap">
            {{ modelValue ? 'Change' : 'Upload' }}
            <input type="file" accept="image/*" class="sr-only" @change="onPick">
        </label>
    </div>
</template>

<script setup>
import { onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: File, default: null },
});

const emit = defineEmits(['update:modelValue']);

const previewUrl = ref(null);

watch(
    () => props.modelValue,
    (file) => {
        if (previewUrl.value) {
            URL.revokeObjectURL(previewUrl.value);
            previewUrl.value = null;
        }
        if (file instanceof File) {
            previewUrl.value = URL.createObjectURL(file);
        }
    },
    { immediate: true },
);

onUnmounted(() => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value);
});

function onPick(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (file) emit('update:modelValue', file);
}
</script>
