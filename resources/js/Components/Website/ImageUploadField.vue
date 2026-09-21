<template>
    <div class="space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <label class="block text-xs font-bold text-gray-700">
                {{ label }}<span v-if="required" class="text-red-500 ml-0.5">*</span>
            </label>
            <span v-if="uploading" class="text-xs font-semibold text-sky-700">Uploading…</span>
        </div>

        <div v-if="resolvedPreview && !previewFailed"
             class="relative overflow-hidden rounded-xl border border-gray-200 bg-slate-100 aspect-[16/7]">
            <img :src="resolvedPreview" :alt="label" loading="lazy"
                 class="h-full w-full object-cover" @error="previewFailed = true">
            <div class="absolute inset-x-0 bottom-0 flex items-center justify-between gap-3 bg-gradient-to-t from-black/70 to-transparent px-3 pb-2 pt-8">
                <span class="truncate text-[11px] font-medium text-white">{{ selectedName || 'Current image' }}</span>
                <button v-if="allowRemove" type="button" class="rounded-lg bg-white/95 px-2.5 py-1 text-[11px] font-bold text-red-600 shadow"
                        @click="clearImage">
                    Remove
                </button>
            </div>
        </div>

        <div v-else-if="modelValue && previewFailed"
             class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            This image cannot be previewed. Replace it or check the image URL.
        </div>

        <label class="group flex min-h-24 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-4 py-4 text-center transition"
               :class="dragging ? 'border-sky-500 bg-sky-50' : 'border-gray-200 bg-white hover:border-sky-300 hover:bg-sky-50/40'"
               @dragenter.prevent="dragging = true" @dragover.prevent="dragging = true"
               @dragleave.prevent="dragging = false" @drop.prevent="onDrop">
            <input :id="inputId" hidden type="file" :accept="accept" :disabled="uploading" @change="onFileInput">
            <span class="text-sm font-bold text-sky-800">{{ modelValue ? 'Replace image' : 'Choose image' }}</span>
            <span class="mt-1 text-xs text-gray-500">or drag and drop here</span>
            <span class="mt-1 text-[11px] text-gray-400">{{ helpText }}</span>
        </label>

        <details v-if="allowUrl" class="rounded-lg border border-gray-100 bg-gray-50 px-3 py-2">
            <summary class="cursor-pointer text-xs font-semibold text-gray-600">Use an image URL instead</summary>
            <input :value="typeof modelValue === 'string' ? modelValue : ''" type="url"
                   placeholder="https://example.com/photo.jpg"
                   class="mt-2 w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs focus:border-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-100"
                   @input="setUrl">
        </details>

        <p v-if="displayError" class="text-xs font-medium text-red-600">{{ displayError }}</p>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Object], default: '' },
    label: { type: String, default: 'Image' },
    inputId: { type: String, default: undefined },
    previewUrl: { type: String, default: '' },
    upload: { type: Function, default: null },
    required: { type: Boolean, default: false },
    allowUrl: { type: Boolean, default: false },
    allowRemove: { type: Boolean, default: true },
    accept: { type: String, default: 'image/jpeg,image/png,image/webp,image/gif' },
    maxSizeMb: { type: Number, default: 5 },
    help: { type: String, default: '' },
    error: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue', 'uploaded']);
const uploading = ref(false);
const dragging = ref(false);
const localError = ref('');
const localPreview = ref('');
const selectedName = ref('');
const previewFailed = ref(false);

const displayError = computed(() => localError.value || props.error);
const resolvedPreview = computed(() => localPreview.value || props.previewUrl || (typeof props.modelValue === 'string' ? props.modelValue : ''));
const helpText = computed(() => props.help || `JPG, PNG, WebP or GIF · up to ${props.maxSizeMb} MB`);

watch(() => props.modelValue, value => {
    previewFailed.value = false;
    if (!value) {
        revokePreview();
        selectedName.value = '';
    }
});

function revokePreview() {
    if (localPreview.value.startsWith('blob:')) URL.revokeObjectURL(localPreview.value);
    localPreview.value = '';
}

function validateFile(file) {
    const allowedTypes = props.accept.split(',').map(value => value.trim()).filter(Boolean);
    const accepted = allowedTypes.some(type => type === 'image/*' ? file?.type?.startsWith('image/') : file?.type === type);
    if (!accepted) return 'Please choose a supported image file.';
    if (file.size > props.maxSizeMb * 1024 * 1024) return `Image must be ${props.maxSizeMb} MB or smaller.`;
    return '';
}

async function chooseFile(file) {
    localError.value = validateFile(file);
    if (localError.value) return;

    revokePreview();
    localPreview.value = URL.createObjectURL(file);
    selectedName.value = file.name;
    previewFailed.value = false;

    if (!props.upload) {
        emit('update:modelValue', file);
        return;
    }

    uploading.value = true;
    try {
        const value = await props.upload(file);
        emit('update:modelValue', value);
        emit('uploaded', value);
    } catch (error) {
        revokePreview();
        selectedName.value = '';
        localError.value = error?.message || 'Image upload failed. Please try again.';
    } finally {
        uploading.value = false;
    }
}

function onFileInput(event) {
    const file = event.target.files?.[0];
    if (file) chooseFile(file);
    event.target.value = '';
}

function onDrop(event) {
    dragging.value = false;
    const file = event.dataTransfer?.files?.[0];
    if (file) chooseFile(file);
}

function clearImage() {
    revokePreview();
    selectedName.value = '';
    previewFailed.value = false;
    localError.value = '';
    emit('update:modelValue', (props.upload || typeof props.modelValue === 'string') ? '' : null);
}

function setUrl(event) {
    revokePreview();
    selectedName.value = '';
    previewFailed.value = false;
    localError.value = '';
    emit('update:modelValue', event.target.value);
}

onBeforeUnmount(revokePreview);
</script>
