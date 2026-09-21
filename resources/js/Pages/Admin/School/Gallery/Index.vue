<template>
    <SchoolAdminLayout title="Gallery" :school="school" :show-header-title="false">
        <PageHeader title="Gallery" eyebrow="Website"
            description="Create public photo albums, choose their covers, and add captions." />

        <div class="space-y-6">
            <div class="grid gap-3 rounded-2xl border border-sky-100 bg-sky-50/70 p-4 text-sm text-sky-950 md:grid-cols-3">
                <div><span class="font-extrabold">1.</span> Create an album and add a clear title.</div>
                <div><span class="font-extrabold">2.</span> Select several photos and preview them before upload.</div>
                <div><span class="font-extrabold">3.</span> Choose any uploaded photo as the album cover.</div>
            </div>

            <div class="card">
                <div class="mb-5">
                    <h3 class="font-bold text-gray-900">Create a new album</h3>
                    <p class="mt-1 text-sm text-gray-500">The cover is optional. If omitted, the first uploaded photo becomes the cover automatically.</p>
                </div>
                <form @submit.prevent="createAlbum" class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
                    <div class="space-y-4">
                        <div>
                            <label class="form-label mb-1.5">Album name <span class="text-red-500">*</span></label>
                            <input v-model="albumForm.title" type="text" required class="field" placeholder="e.g. Annual Day 2026">
                            <p v-if="albumForm.errors.title" class="mt-1 text-xs font-medium text-red-600">{{ albumForm.errors.title }}</p>
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Description</label>
                            <textarea v-model="albumForm.description" rows="4" class="field" placeholder="Briefly describe the event or activity visitors will see"></textarea>
                            <p v-if="albumForm.errors.description" class="mt-1 text-xs font-medium text-red-600">{{ albumForm.errors.description }}</p>
                        </div>
                        <button type="submit" :disabled="albumForm.processing" class="btn-primary w-full justify-center disabled:opacity-50 sm:w-auto">
                            {{ albumForm.processing ? 'Creating album…' : 'Create album' }}
                        </button>
                    </div>
                    <ImageUploadField v-model="albumForm.cover_image" label="Optional cover image"
                        :error="albumForm.errors.cover_image" :max-size-mb="5"
                        help="Landscape photo recommended · JPG, PNG, WebP or GIF · up to 5 MB" />
                </form>
            </div>

            <div v-if="albums.length" class="space-y-5">
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Your albums</h3>
                    <p class="text-sm text-gray-500">{{ albums.length }} album{{ albums.length === 1 ? '' : 's' }} · all uploaded photos are shown below.</p>
                </div>

                <section v-for="album in albums" :key="album.id" class="card card--flush overflow-hidden">
                    <div class="border-b border-gray-100 p-4 sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <img v-if="album.cover_url" :src="album.cover_url" :alt="`${album.title} cover`" loading="lazy"
                                     class="h-16 w-24 shrink-0 rounded-xl border border-gray-100 object-cover">
                                <div v-else class="flex h-16 w-24 shrink-0 items-center justify-center rounded-xl border border-dashed border-gray-200 bg-gray-50 text-2xl">🖼️</div>
                                <div class="min-w-0">
                                    <h4 class="truncate font-bold text-gray-900">{{ album.title }}</h4>
                                    <p class="mt-0.5 text-xs text-gray-500">{{ album.items_count }} photo{{ album.items_count === 1 ? '' : 's' }}</p>
                                </div>
                            </div>
                            <button type="button" @click="deleteAlbum(album)" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-red-500 hover:bg-red-50 hover:text-red-700">
                                Delete album
                            </button>
                        </div>

                        <div class="mt-5 grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)_7rem]">
                            <label class="form-label">Album name
                                <input v-model="albumDrafts[album.id].title" class="field mt-1">
                            </label>
                            <label class="form-label">Description
                                <input v-model="albumDrafts[album.id].description" class="field mt-1" placeholder="Optional description">
                            </label>
                            <label class="form-label">Position
                                <input v-model.number="albumDrafts[album.id].display_order" type="number" min="0" class="field mt-1">
                            </label>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,28rem)_auto] lg:items-end">
                            <ImageUploadField :model-value="albumDrafts[album.id].cover_image"
                                :preview-url="albumDrafts[album.id].remove_cover ? '' : (album.cover_url || '')"
                                label="Album cover" :max-size-mb="5"
                                help="Upload a different cover, or choose an existing photo below"
                                @update:model-value="value => onCoverChange(album, value)" />
                            <button type="button" :disabled="albumSaving[album.id]" @click="updateAlbum(album)"
                                    class="btn-secondary justify-center disabled:opacity-50">
                                {{ albumSaving[album.id] ? 'Saving…' : 'Save album details' }}
                            </button>
                        </div>
                        <p v-if="albumErrors[album.id]" class="mt-3 text-xs font-medium text-red-600">{{ albumErrors[album.id] }}</p>
                    </div>

                    <div class="space-y-5 p-4 sm:p-5">
                        <div>
                            <h5 class="text-sm font-bold text-gray-900">Add photos</h5>
                            <p class="mt-1 text-xs text-gray-500">Preview the complete selection before uploading. The first photo becomes the cover when the album has no cover.</p>
                        </div>
                        <MultiImageUploader :upload="files => uploadPhotos(album, files)"
                            :uploading="photoUploading[album.id]" :error="photoErrors[album.id]" />

                        <div v-if="album.items?.length" class="border-t border-gray-100 pt-5">
                            <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                                <h5 class="text-sm font-bold text-gray-900">Uploaded photos</h5>
                                <p class="text-xs text-gray-400">Add a caption to improve accessibility and search.</p>
                            </div>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4">
                                <article v-for="photo in album.items" :key="photo.id" class="overflow-hidden rounded-xl border border-gray-200 bg-white">
                                    <div class="relative aspect-[4/3] bg-gray-100">
                                        <img :src="photo.image_url || photo.image_path" :alt="photo.caption || album.title" loading="lazy" class="h-full w-full object-cover">
                                        <span v-if="album.cover_image === photo.image_path" class="absolute left-2 top-2 rounded-full bg-emerald-600 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-white shadow">Cover</span>
                                        <button type="button" aria-label="Delete photo" @click="deletePhoto(photo)"
                                                class="absolute right-2 top-2 flex h-8 w-8 items-center justify-center rounded-full bg-black/70 text-lg font-bold text-white shadow hover:bg-red-600">×</button>
                                    </div>
                                    <div class="space-y-2 p-3">
                                        <label class="block text-[11px] font-bold text-gray-500">Caption
                                            <input v-model="photo.caption" class="mt-1 w-full rounded-lg border border-gray-200 px-2.5 py-2 text-xs focus:border-sky-400 focus:outline-none" placeholder="Describe this photo" @blur="updatePhoto(photo)">
                                        </label>
                                        <button v-if="album.cover_image !== photo.image_path" type="button" @click="setCover(album, photo)"
                                                class="w-full rounded-lg border border-gray-200 px-2.5 py-2 text-xs font-bold text-gray-600 hover:border-sky-200 hover:bg-sky-50 hover:text-sky-800">
                                            Use as album cover
                                        </button>
                                    </div>
                                </article>
                            </div>
                        </div>
                        <div v-else class="rounded-xl border border-dashed border-gray-200 bg-gray-50 px-5 py-8 text-center text-sm text-gray-400">
                            No photos yet. Add the first photos above.
                        </div>
                    </div>
                </section>
            </div>

            <div v-else class="card card--dashed p-12 text-center">
                <div class="text-4xl">🖼️</div>
                <p class="mt-3 font-bold text-gray-700">No albums yet</p>
                <p class="mt-1 text-sm text-gray-400">Create your first album using the form above.</p>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import ImageUploadField from '@/Components/Website/ImageUploadField.vue';
import MultiImageUploader from '@/Components/Website/MultiImageUploader.vue';
import { useForm, router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();

const props = defineProps({
    school: Object,
    albums: { type: Array, default: () => [] },
});

const albumForm = useForm({ title: '', description: '', cover_image: null });
const albumDrafts = reactive({});
const albumSaving = reactive({});
const albumErrors = reactive({});
const photoUploading = reactive({});
const photoErrors = reactive({});

watch(() => props.albums, albums => {
    const activeIds = new Set(albums.map(album => String(album.id)));
    for (const album of albums) {
        if (albumDrafts[album.id]) continue;
        albumDrafts[album.id] = {
            title: album.title,
            description: album.description || '',
            display_order: album.display_order || 0,
            cover_image: '',
            remove_cover: false,
        };
    }
    for (const id of Object.keys(albumDrafts)) {
        if (!activeIds.has(String(id))) delete albumDrafts[id];
    }
}, { immediate: true });

function firstError(errors, fallback) {
    return Object.values(errors || {}).flat().find(Boolean) || fallback;
}

function createAlbum() {
    albumForm.post(`/school-admin/${props.school.id}/gallery/albums`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => albumForm.reset(),
    });
}

function onCoverChange(album, value) {
    const draft = albumDrafts[album.id];
    draft.cover_image = value || '';
    draft.remove_cover = !value && !!album.cover_image;
}

function updateAlbum(album) {
    const draft = albumDrafts[album.id];
    const form = new FormData();
    form.append('_method', 'put');
    form.append('title', draft.title);
    form.append('description', draft.description || '');
    form.append('display_order', String(draft.display_order || 0));
    form.append('remove_cover', draft.remove_cover ? '1' : '0');
    if (draft.cover_image instanceof File) form.append('cover_image', draft.cover_image);

    albumSaving[album.id] = true;
    albumErrors[album.id] = '';
    router.post(`/school-admin/${props.school.id}/gallery/albums/${album.id}`, form, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            draft.cover_image = '';
            draft.remove_cover = false;
        },
        onError: errors => { albumErrors[album.id] = firstError(errors, 'Album details could not be saved.'); },
        onFinish: () => { albumSaving[album.id] = false; },
    });
}

function updatePhoto(photo) {
    router.patch(`/school-admin/${props.school.id}/gallery/photos/${photo.id}`, {
        caption: photo.caption || '',
        display_order: photo.display_order || 0,
    }, { preserveScroll: true });
}

function uploadPhotos(album, files) {
    photoUploading[album.id] = true;
    photoErrors[album.id] = '';

    return new Promise((resolve, reject) => {
        router.post(`/school-admin/${props.school.id}/gallery/albums/${album.id}/photos`, { photos: files }, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => resolve(),
            onError: errors => {
                const message = firstError(errors, 'The selected photos could not be uploaded.');
                photoErrors[album.id] = message;
                reject(new Error(message));
            },
            onFinish: () => { photoUploading[album.id] = false; },
        });
    });
}

function setCover(album, photo) {
    router.patch(`/school-admin/${props.school.id}/gallery/albums/${album.id}/cover/${photo.id}`, {}, { preserveScroll: true });
}

async function deleteAlbum(album) {
    if (!(await confirm({ message: `Delete album “${album.title}” and all its photos?`, destructive: true }))) return;
    router.delete(`/school-admin/${props.school.id}/gallery/albums/${album.id}`, { preserveScroll: true });
}

async function deletePhoto(photo) {
    if (!(await confirm({ message: 'Remove this photo from the album?', destructive: true }))) return;
    router.delete(`/school-admin/${props.school.id}/gallery/photos/${photo.id}`, { preserveScroll: true });
}
</script>
