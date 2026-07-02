<template>
    <div class="space-y-3">
        <div class="flex items-center gap-4">
            <div class="w-16 h-16 rounded-full overflow-hidden border border-gray-200 bg-gray-100 shrink-0 flex items-center justify-center">
                <img v-if="displayPreview" :src="displayPreview" alt="" class="w-full h-full object-cover">
                <span v-else class="text-xs text-gray-400 font-semibold">Photo</span>
            </div>
            <div class="min-w-0 flex-1">
                <label class="form-label mb-1.5">
                    Profile photo
                    <span v-if="required" class="text-red-500">*</span>
                    <span v-else class="font-normal text-gray-400">(optional)</span>
                </label>
                <input ref="fileInputRef" type="file" accept="image/*" class="w-full text-sm text-gray-500 file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#f0f9ff] file:text-[#0f3d7a]"
                       @change="onPickFile">
                <p class="text-xs text-gray-400 mt-1">JPG or PNG, max 2 MB. Crop to a square profile photo, or use the full image.</p>
                <button v-if="modelValue" type="button" class="text-xs text-red-600 mt-1 hover:underline" @click="clearPhoto">Remove photo</button>
            </div>
        </div>

        <div v-if="cropOpen" class="rounded-xl border border-slate-200 bg-slate-50 p-4 space-y-3">
            <p class="text-xs font-semibold text-slate-700">Crop to a square profile photo, or upload the full image as-is.</p>
            <div class="max-h-[360px] overflow-hidden rounded-lg bg-[#0b1220]">
                <img ref="imageRef" :src="sourceUrl" alt="" class="block max-w-full">
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" class="btn-primary text-xs !min-h-0 !py-1.5" @click="applyCrop">Apply crop</button>
                <button type="button" class="btn-secondary text-xs !min-h-0 !py-1.5" @click="useFullImage">Use full image</button>
                <button type="button" class="btn-ghost text-xs" @click="cancelCrop">Cancel</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import Cropper from 'cropperjs';
import 'cropperjs/dist/cropper.css';
import { nextTick, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    modelValue: { type: File, default: null },
    existingUrl: { type: String, default: null },
    required: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const fileInputRef = ref(null);
const imageRef = ref(null);
const cropOpen = ref(false);
const sourceUrl = ref(null);
const pickedFile = ref(null);
const displayPreview = ref(null);
const objectUrls = ref([]);
let cropper = null;

watch(
    () => [props.modelValue, props.existingUrl],
    ([file, existing]) => {
        revokeTracked();
        if (file instanceof File) {
            displayPreview.value = trackUrl(URL.createObjectURL(file));
        } else {
            displayPreview.value = existing || null;
        }
    },
    { immediate: true },
);

function trackUrl(url) {
    objectUrls.value.push(url);
    return url;
}

function revokeTracked() {
    objectUrls.value.forEach((url) => URL.revokeObjectURL(url));
    objectUrls.value = [];
}

onUnmounted(() => {
    destroyCropper();
    revokeTracked();
    if (sourceUrl.value) URL.revokeObjectURL(sourceUrl.value);
});

function destroyCropper() {
    cropper?.destroy();
    cropper = null;
}

function onPickFile(event) {
    const file = event.target.files?.[0];
    event.target.value = '';
    if (!file) return;

    destroyCropper();
    if (sourceUrl.value) URL.revokeObjectURL(sourceUrl.value);
    pickedFile.value = file;
    sourceUrl.value = URL.createObjectURL(file);
    cropOpen.value = true;

    nextTick(() => {
        destroyCropper();
        if (!imageRef.value) return;
        cropper = new Cropper(imageRef.value, {
            aspectRatio: 1,
            viewMode: 1,
            dragMode: 'move',
            autoCropArea: 0.85,
            responsive: true,
            background: false,
            guides: true,
            center: true,
            highlight: false,
            cropBoxMovable: true,
            cropBoxResizable: true,
            toggleDragModeOnDblclick: false,
        });
    });
}

function applyCrop() {
    if (!cropper) return;

    const canvas = cropper.getCroppedCanvas({
        width: 512,
        height: 512,
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high',
    });

    canvas.toBlob((blob) => {
        if (!blob) return;
        emit('update:modelValue', new File([blob], 'student-photo.jpg', { type: 'image/jpeg' }));
        cancelCrop();
    }, 'image/jpeg', 0.92);
}

function useFullImage() {
    if (!pickedFile.value) return;
    emit('update:modelValue', pickedFile.value);
    cancelCrop();
}

function cancelCrop() {
    destroyCropper();
    cropOpen.value = false;
    pickedFile.value = null;
    if (sourceUrl.value) {
        URL.revokeObjectURL(sourceUrl.value);
        sourceUrl.value = null;
    }
}

function clearPhoto() {
    emit('update:modelValue', null);
    cancelCrop();
    if (fileInputRef.value) fileInputRef.value.value = '';
}
</script>

<style scoped>
:deep(.cropper-view-box),
:deep(.cropper-face) {
    border-radius: 50%;
}
</style>
