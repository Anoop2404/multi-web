<template>
    <SchoolAdminLayout title="News Articles" :school="school">
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">{{ articles.total }} articles</p>
                <Link :href="`/school-admin/${school.id}/news/create`"
                      class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-blue-700 transition">
                    + New Article
                </Link>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Title</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Category</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Published</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wide">Featured</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="article in articles.data" :key="article.id" class="hover:bg-gray-50 transition">
                            <td class="px-5 py-3 font-medium text-gray-800 max-w-xs truncate">{{ article.title }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ article.category || '—' }}</td>
                            <td class="px-5 py-3 text-gray-400 text-xs">
                                {{ article.published_at ? new Date(article.published_at).toLocaleDateString('en-IN') : 'Draft' }}
                            </td>
                            <td class="px-5 py-3">
                                <span v-if="article.is_featured" class="text-amber-500 text-xs font-semibold">★ Featured</span>
                            </td>
                            <td class="px-5 py-3 text-right space-x-2">
                                <Link :href="`/school-admin/${school.id}/news/${article.id}/edit`"
                                      class="text-xs text-blue-600 hover:underline">Edit</Link>
                                <Link :href="`/school-admin/${school.id}/news/${article.id}`"
                                      method="delete" as="button"
                                      @click.prevent="destroy(article)"
                                      class="text-xs text-red-400 hover:underline">Delete</Link>
                            </td>
                        </tr>
                        <tr v-if="!articles.data.length">
                            <td colspan="5" class="px-5 py-10 text-center text-gray-400">
                                No articles yet. <Link :href="`/school-admin/${school.id}/news/create`" class="text-blue-600 hover:underline">Create one</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
    school:   Object,
    articles: Object,
});

function destroy(article) {
    if (!confirm(`Delete "${article.title}"?`)) return;
    router.delete(`/school-admin/${props.school.id}/news/${article.id}`);
}
</script>
