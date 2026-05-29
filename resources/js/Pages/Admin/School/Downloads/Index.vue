<template>
    <SchoolAdminLayout title="Downloads" :school="school">
        <div class="space-y-6">
            <!-- Upload form -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="font-bold text-gray-800 mb-4">Upload New File</h3>
                <form @submit.prevent="upload" class="space-y-4">
                    <div class="grid sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">File Title *</label>
                            <input v-model="form.title" type="text" required
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Category *</label>
                            <select v-model="form.category" required
                                    class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 bg-white">
                                <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4 items-end">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Academic Year</label>
                            <input v-model="form.academic_year" type="text" placeholder="2025-26"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">File (PDF / DOC / XLS) *</label>
                            <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx" required
                                   @change="form.file = $event.target.files[0]"
                                   class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                        </div>
                    </div>
                    <button type="submit" :disabled="form.processing"
                            class="bg-purple-600 text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-purple-700 transition disabled:opacity-50">
                        Upload File
                    </button>
                </form>
            </div>

            <!-- Files list -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Title</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Category</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Year</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Active</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="dl in downloads" :key="dl.id" class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-medium text-gray-800">{{ dl.title }}</td>
                            <td class="px-5 py-3 text-gray-500 capitalize">{{ dl.category.replace(/_/g,' ') }}</td>
                            <td class="px-5 py-3 text-gray-400 text-xs">{{ dl.academic_year || '—' }}</td>
                            <td class="px-5 py-3">
                                <span :class="dl.is_active ? 'text-green-600' : 'text-gray-300'" class="text-xs font-medium">
                                    {{ dl.is_active ? '● Active' : '○ Hidden' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <button @click="remove(dl)" class="text-xs text-red-400 hover:underline">Remove</button>
                            </td>
                        </tr>
                        <tr v-if="!downloads.length">
                            <td colspan="5" class="px-5 py-10 text-center text-gray-400">No files yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    school:    Object,
    downloads: { type: Array, default: () => [] },
});

const categories = [
    { value: 'booklist',       label: 'Book List' },
    { value: 'calendar',       label: 'Academic Calendar' },
    { value: 'circular',       label: 'Circular' },
    { value: 'question_paper', label: 'Question Paper' },
    { value: 'annual_report',  label: 'Annual Report' },
    { value: 'form',           label: 'Form' },
    { value: 'minutes',        label: 'Meeting Minutes' },
    { value: 'other',          label: 'Other' },
];

const form = useForm({
    title:         '',
    category:      'circular',
    academic_year: '',
    file:          null,
});

function upload() {
    form.post(`/school-admin/${props.school.id}/downloads`, {
        forceFormData: true,
        onSuccess: () => form.reset(),
    });
}

function remove(dl) {
    if (!confirm(`Remove "${dl.title}"?`)) return;
    router.delete(`/school-admin/${props.school.id}/downloads/${dl.id}`);
}
</script>
