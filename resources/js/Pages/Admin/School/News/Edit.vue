<template>
    <SchoolAdminLayout :title="`Edit: ${news.title}`" :school="school" :show-header-title="false">
        <PageHeader :title="`Edit: ${news.title}`" eyebrow="Website"
            description="School website content and public pages." />


        <div class="max-w-3xl">
            <form @submit.prevent="submit" class="space-y-5">
                <div class="card space-y-5">
                    <div>
                        <label class="form-label mb-1.5">Title *</label>
                        <input v-model="form.title" type="text" required
                               class="field">
                        <InputError :message="form.errors.title" class="mt-1" />
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label mb-1.5">Category</label>
                            <input v-model="form.category" type="text"
                                   class="field">
                            <InputError :message="form.errors.category" class="mt-1" />
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Publish Date</label>
                            <input v-model="form.published_at" type="datetime-local"
                                   class="field">
                            <InputError :message="form.errors.published_at" class="mt-1" />
                        </div>
                    </div>

                    <RichTextEditor v-model="form.body" label="Content" required
                        placeholder="Write the article content…" :error="form.errors.body"
                        help="Use headings, lists and links to make the article easy to read." />

                    <ImageUploadField v-model="form.image" label="Featured image" :preview-url="news.image_url || ''"
                        :max-size-mb="4" :error="form.errors.image" :allow-remove="false"
                        help="Choose a new file only when replacing the current image" />

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
import InputError from '@/Components/ui/InputError.vue';
import ImageUploadField from '@/Components/Website/ImageUploadField.vue';
import RichTextEditor from '@/Components/ui/RichTextEditor.vue';
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
