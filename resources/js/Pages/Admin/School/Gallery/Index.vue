<template>
    <SchoolAdminLayout title="Gallery" :school="school" :show-header-title="false">
        <PageHeader title="Gallery" eyebrow="Website"
            description="School website content and public pages." />


        <div class="space-y-6">
            <!-- Create Album -->
            <div class="card">
                <h3 class="font-bold text-gray-800 mb-4">Create New Album</h3>
                <form @submit.prevent="createAlbum" class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="form-label mb-1.5">Album Name *</label>
                        <input v-model="albumForm.title" type="text" required
                               class="field">
                    </div>
                    <div>
                        <label class="form-label mb-1.5">Cover Image</label>
                        <input type="file" accept="image/*" @change="albumForm.cover_image = $event.target.files[0]"
                               class="text-sm text-gray-500 file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700">
                    </div>
                    <div class="md:col-span-2">
                        <label class="form-label mb-1.5">Description</label>
                        <textarea v-model="albumForm.description" rows="2" class="field" placeholder="What visitors will see in this album"></textarea>
                    </div>
                    <button type="submit" :disabled="albumForm.processing" class="btn-primary disabled:opacity-50 md:justify-self-start">
                        Create Album
                    </button>
                </form>
            </div>

            <!-- Albums list -->
            <div v-for="album in albums" :key="album.id"
                 class="card card--flush">
                <!-- Album header -->
                <div class="p-5 border-b border-gray-100 space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h4 class="font-bold text-gray-800">{{ album.title }}</h4>
                            <p class="text-xs text-gray-400 mt-0.5">{{ album.items_count }} photos</p>
                        </div>
                        <div class="flex items-center gap-2">
                        <!-- Upload photos -->
                        <label class="cursor-pointer bg-blue-50 text-blue-700 text-xs font-semibold px-3 py-1.5 rounded-lg hover:bg-blue-100 transition">
                            + Upload Photos
                            <input type="file" accept="image/*" multiple class="sr-only"
                                   @change="uploadPhotos(album, $event)">
                        </label>
                        <button @click="deleteAlbum(album)"
                                class="text-xs text-red-400 hover:text-red-600 transition px-2">Delete album</button>
                        </div>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_2fr_7rem]">
                        <label class="form-label">Album name<input v-model="albumDrafts[album.id].title" class="field mt-1"></label>
                        <label class="form-label">Description<input v-model="albumDrafts[album.id].description" class="field mt-1"></label>
                        <label class="form-label">Order<input v-model.number="albumDrafts[album.id].display_order" type="number" min="0" class="field mt-1"></label>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <img v-if="album.cover_url" :src="album.cover_url" :alt="album.title" class="h-14 w-20 object-cover rounded-lg border border-gray-100">
                        <label class="text-xs text-gray-600">Replace cover<input type="file" accept="image/*" class="block mt-1 text-xs" @change="albumDrafts[album.id].cover_image = $event.target.files[0]"></label>
                        <button type="button" @click="updateAlbum(album)" class="btn-secondary ml-auto">Save album details</button>
                    </div>
                </div>

                <!-- Photo grid preview -->
                <div v-if="album.items?.length" class="p-4 grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-8 gap-2">
                    <div v-for="photo in album.items" :key="photo.id" class="relative group space-y-1">
                        <div class="relative aspect-square">
                        <img :src="photo.image_url || photo.image_path" :alt="photo.caption || album.title" class="w-full h-full object-cover rounded-lg">
                        <button @click="deletePhoto(photo)"
                                class="absolute top-1 right-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs hidden group-hover:flex items-center justify-center">
                            ×
                        </button>
                        </div>
                        <input v-model="photo.caption" @change="updatePhoto(photo)" class="w-full rounded border border-gray-200 px-2 py-1 text-[11px]" placeholder="Photo caption">
                    </div>
                </div>
                <div v-else class="px-5 py-6 text-center text-sm text-gray-400">
                    No photos yet. Upload photos to this album.
                </div>
            </div>

            <div v-if="!albums.length" class="card card--dashed p-12 text-center text-slate-400">
                No albums yet. Create your first album above.
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useForm, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { useConfirm } from '@/composables/useConfirm';
const { confirm } = useConfirm();

const props = defineProps({
    school: Object,
    albums: { type: Array, default: () => [] },
});

const albumForm = useForm({ title: '', description: '', cover_image: null });
const albumDrafts = reactive(Object.fromEntries(props.albums.map(album => [album.id, {
    title: album.title,
    description: album.description || '',
    display_order: album.display_order || 0,
    cover_image: null,
}])));

function createAlbum() {
    albumForm.post(`/school-admin/${props.school.id}/gallery/albums`, {
        forceFormData: true,
        onSuccess: () => albumForm.reset(),
    });
}

function updateAlbum(album) {
    const draft = albumDrafts[album.id];
    const form = new FormData();
    form.append('_method', 'put');
    form.append('title', draft.title);
    form.append('description', draft.description || '');
    form.append('display_order', String(draft.display_order || 0));
    if (draft.cover_image) form.append('cover_image', draft.cover_image);

    router.post(`/school-admin/${props.school.id}/gallery/albums/${album.id}`, form, {
        forceFormData: true,
    });
}

function updatePhoto(photo) {
    router.patch(`/school-admin/${props.school.id}/gallery/photos/${photo.id}`, {
        caption: photo.caption || '',
        display_order: photo.display_order || 0,
    }, { preserveScroll: true });
}

function uploadPhotos(album, event) {
    const formData = new FormData();
    Array.from(event.target.files).forEach(f => formData.append('photos[]', f));
    router.post(`/school-admin/${props.school.id}/gallery/albums/${album.id}/photos`, formData, {
        forceFormData: true,
    });
}

async function deleteAlbum(album) {
    if (!(await confirm({ message: `Delete album "${album.title}" and all its photos?`, destructive: true }))) return;
    router.delete(`/school-admin/${props.school.id}/gallery/albums/${album.id}`);
}

function deletePhoto(photo) {
    router.delete(`/school-admin/${props.school.id}/gallery/photos/${photo.id}`);
}
</script>
