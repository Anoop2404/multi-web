<template>
    <SchoolAdminLayout title="Gallery" :school="school">
        <div class="space-y-6">
            <!-- Create Album -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="font-bold text-gray-800 mb-4">Create New Album</h3>
                <form @submit.prevent="createAlbum" class="flex flex-wrap items-end gap-4">
                    <div class="flex-1 min-w-48">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Album Name *</label>
                        <input v-model="albumForm.title" type="text" required
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                    </div>
                    <div class="min-w-48">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Cover Image</label>
                        <input type="file" accept="image/*" @change="albumForm.cover_image = $event.target.files[0]"
                               class="text-sm text-gray-500 file:mr-2 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700">
                    </div>
                    <button type="submit" :disabled="albumForm.processing"
                            class="bg-blue-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-blue-700 transition disabled:opacity-50 shrink-0">
                        Create Album
                    </button>
                </form>
            </div>

            <!-- Albums list -->
            <div v-for="album in albums" :key="album.id"
                 class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <!-- Album header -->
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-50">
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

                <!-- Photo grid preview -->
                <div v-if="album.items?.length" class="p-4 grid grid-cols-4 sm:grid-cols-6 lg:grid-cols-8 gap-2">
                    <div v-for="photo in album.items" :key="photo.id" class="relative group aspect-square">
                        <img :src="photo.image_path" class="w-full h-full object-cover rounded-lg">
                        <button @click="deletePhoto(photo)"
                                class="absolute top-1 right-1 w-5 h-5 bg-red-500 text-white rounded-full text-xs hidden group-hover:flex items-center justify-center">
                            ×
                        </button>
                    </div>
                </div>
                <div v-else class="px-5 py-6 text-center text-sm text-gray-400">
                    No photos yet. Upload photos to this album.
                </div>
            </div>

            <div v-if="!albums.length" class="bg-white rounded-xl border border-dashed border-gray-200 p-12 text-center text-gray-400">
                No albums yet. Create your first album above.
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    school: Object,
    albums: { type: Array, default: () => [] },
});

const albumForm = useForm({ title: '', cover_image: null });

function createAlbum() {
    albumForm.post(`/school-admin/${props.school.id}/gallery/albums`, {
        forceFormData: true,
        onSuccess: () => albumForm.reset(),
    });
}

function uploadPhotos(album, event) {
    const formData = new FormData();
    Array.from(event.target.files).forEach(f => formData.append('photos[]', f));
    router.post(`/school-admin/${props.school.id}/gallery/albums/${album.id}/photos`, formData, {
        forceFormData: true,
    });
}

function deleteAlbum(album) {
    if (!confirm(`Delete album "${album.title}" and all its photos?`)) return;
    router.delete(`/school-admin/${props.school.id}/gallery/albums/${album.id}`);
}

function deletePhoto(photo) {
    router.delete(`/school-admin/${props.school.id}/gallery/photos/${photo.id}`);
}
</script>
