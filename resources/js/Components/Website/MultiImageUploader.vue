<template>
    <div class="space-y-3">
        <label class="flex min-h-28 cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed px-4 py-4 text-center transition"
               :class="dragging ? 'border-sky-500 bg-sky-50' : 'border-gray-200 bg-gray-50/60 hover:border-sky-300 hover:bg-sky-50/40'"
               @dragenter.prevent="dragging = true" @dragover.prevent="dragging = true"
               @dragleave.prevent="dragging = false" @drop.prevent="onDrop">
            <input hidden type="file" multiple :accept="accept" :disabled="uploading" @change="onInput">
            <span class="text-sm font-bold text-sky-800">Add gallery photos</span>
            <span class="mt-1 text-xs text-gray-500">Choose multiple images or drag them here</span>
            <span class="mt-1 text-[11px] text-gray-400">Up to {{ maxFiles }} images at once · {{ maxSizeMb }} MB each</span>
        </label>

        <div v-if="previews.length" class="space-y-3 rounded-xl border border-gray-200 bg-white p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-bold text-gray-700">{{ previews.length }} photo{{ previews.length === 1 ? '' : 's' }} ready</p>
                <button type="button" class="text-xs font-semibold text-gray-500 hover:text-red-600" @click="clear">Clear selection</button>
            </div>
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-8">
                <div v-for="(item, index) in previews" :key="item.url" class="group relative aspect-square overflow-hidden rounded-lg bg-gray-100">
                    <img :src="item.url" :alt="item.file.name" class="h-full w-full object-cover">
                    <button type="button" aria-label="Remove selected photo"
                            class="absolute right-1 top-1 flex h-6 w-6 items-center justify-center rounded-full bg-black/70 text-sm font-bold text-white"
                            @click="remove(index)">×</button>
                </div>
            </div>
            <button type="button" :disabled="uploading" class="btn-primary w-full justify-center disabled:opacity-50" @click="submit">
                {{ uploading ? `Uploading ${previews.length} photos…` : `Upload ${previews.length} photo${previews.length === 1 ? '' : 's'}` }}
            </button>
        </div>

        <p v-if="localError || error" class="text-xs font-medium text-red-600">{{ localError || error }}</p>
    </div>
</template>

<script setup>
import { onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    upload: { type: Function, required: true },
    uploading: { type: Boolean, default: false },
    error: { type: String, default: '' },
    maxFiles: { type: Number, default: 30 },
    maxSizeMb: { type: Number, default: 8 },
    accept: { type: String, default: 'image/jpeg,image/png,image/webp,image/gif' },
});

const files = ref([]);
const previews = ref([]);
const dragging = ref(false);
const localError = ref('');

function addFiles(incoming) {
    localError.value = '';
    const candidates = Array.from(incoming || []);
    if (files.value.length + candidates.length > props.maxFiles) {
        localError.value = `Choose no more than ${props.maxFiles} photos at once.`;
        return;
    }
    const allowedTypes = props.accept.split(',').map(value => value.trim()).filter(Boolean);
    const invalid = candidates.find(file => {
        const accepted = allowedTypes.some(type => type === 'image/*' ? file.type.startsWith('image/') : file.type === type);
        return !accepted || file.size > props.maxSizeMb * 1024 * 1024;
    });
    if (invalid) {
        localError.value = `“${invalid.name}” is not a supported image or is larger than ${props.maxSizeMb} MB.`;
        return;
    }
    for (const file of candidates) {
        files.value.push(file);
        previews.value.push({ file, url: URL.createObjectURL(file) });
    }
}

function onInput(event) {
    addFiles(event.target.files);
    event.target.value = '';
}

function onDrop(event) {
    dragging.value = false;
    addFiles(event.dataTransfer?.files);
}

function remove(index) {
    URL.revokeObjectURL(previews.value[index].url);
    previews.value.splice(index, 1);
    files.value.splice(index, 1);
}

function clear() {
    previews.value.forEach(item => URL.revokeObjectURL(item.url));
    previews.value = [];
    files.value = [];
    localError.value = '';
}

async function submit() {
    if (!files.value.length || props.uploading) return;
    localError.value = '';
    try {
        await props.upload([...files.value]);
        clear();
    } catch (error) {
        localError.value = error?.message || 'Photos could not be uploaded. Please try again.';
    }
}

onBeforeUnmount(clear);
</script>
