<template>
    <SchoolAdminLayout :title="`Edit: ${news.title}`" :school="school" :show-header-title="false">
        <PageHeader title="`Edit: ${news.title}`" eyebrow="Website"
            description="School website content and public pages." />


        <div class="max-w-3xl">
            <form @submit.prevent="submit" class="space-y-5">
                <div class="card space-y-5">
                    <div>
                        <label class="form-label mb-1.5">Title *</label>
                        <input v-model="form.title" type="text" required
                               class="field">
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label mb-1.5">Category</label>
                            <input v-model="form.category" type="text"
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Publish Date</label>
                            <input v-model="form.published_at" type="datetime-local"
                                   class="field">
                        </div>
                    </div>

                    <div>
                        <label class="form-label mb-1.5">Content *</label>
                        <textarea v-model="form.body" rows="12" required
                                  class="field resize-y"></textarea>
                    </div>

                    <div v-if="news.image" class="flex items-center gap-3 text-sm text-gray-500">
                        <img :src="news.image" class="h-16 w-24 object-cover rounded-lg border border-gray-100">
                        <span>Current image</span>
                    </div>

                    <div>
                        <label class="form-label mb-1.5">Replace Image</label>
                        <input type="file" accept="image/*" @change="form.image = $event.target.files[0]"
                               class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="is_featured" v-model="form.is_featured" class="rounded">
                        <label for="is_featured" class="text-sm text-gray-700">Mark as featured</label>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <button type="submit" :disabled="form.processing"
                            class="btn-primary disabled:opacity-50">
                        Save Changes
                    </button>
                    <Link :href="`/school-admin/${school.id}/news`" class="text-sm text-gray-500 hover:text-gray-700">Cancel</Link>
                </div>
            </form>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({ school: Object, news: Object });

const form = useForm({
    title:        props.news.title,
    category:     props.news.category ?? '',
    body:         props.news.body,
    published_at: props.news.published_at?.slice(0, 16) ?? '',
    is_featured:  props.news.is_featured,
    image:        null,
    _method:      'PUT',
});

function submit() {
    form.post(`/school-admin/${props.school.id}/news/${props.news.id}`, {
        forceFormData: true,
    });
}
</script>
