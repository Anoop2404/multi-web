<template>
    <SahodayaAdminLayout title="Circulars" :sahodaya="sahodaya">
        <div class="space-y-6">
            <!-- Upload form -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="font-bold text-gray-800 mb-4">Upload New Circular</h3>
                <form @submit.prevent="upload" class="space-y-4">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Circular Title *</label>
                            <input v-model="form.title" type="text" required
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Circular Number</label>
                            <input v-model="form.circular_number" type="text" placeholder="SAH/2024/001"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Category</label>
                            <select v-model="form.category"
                                    class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300 bg-white">
                                <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Issue Date</label>
                            <input v-model="form.issued_date" type="date"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Academic Year</label>
                            <input v-model="form.academic_year" type="text" placeholder="2025-26"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">PDF File *</label>
                            <input type="file" accept=".pdf,.doc,.docx" required
                                   @change="form.file = $event.target.files[0]"
                                   class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                        </div>
                    </div>
                    <button type="submit" :disabled="form.processing"
                            class="bg-purple-600 text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-purple-700 transition disabled:opacity-50">
                        Upload Circular
                    </button>
                </form>
            </div>

            <!-- Filter bar -->
            <div class="flex flex-wrap gap-2">
                <button @click="activeCategory = ''"
                        :class="activeCategory === '' ? 'bg-purple-600 text-white' : 'bg-white text-gray-600 border border-gray-200'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition">All</button>
                <button v-for="c in categories" :key="c"
                        @click="activeCategory = c"
                        :class="activeCategory === c ? 'bg-purple-600 text-white' : 'bg-white text-gray-600 border border-gray-200'"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold transition">{{ c }}</button>
            </div>

            <!-- Circulars table -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Title</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Number</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Category</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Date</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Year</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="c in filtered" :key="c.id" class="hover:bg-gray-50">
                            <td class="px-5 py-3 font-medium text-gray-800">{{ c.title }}</td>
                            <td class="px-5 py-3 text-gray-500 font-mono text-xs">{{ c.circular_number || '—' }}</td>
                            <td class="px-5 py-3">
                                <span class="text-xs bg-purple-50 text-purple-700 px-2 py-0.5 rounded-full font-medium">
                                    {{ c.category || 'General' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-gray-400 text-xs">
                                {{ c.issued_date ? new Date(c.issued_date).toLocaleDateString('en-IN') : '—' }}
                            </td>
                            <td class="px-5 py-3 text-gray-400 text-xs">{{ c.academic_year || '—' }}</td>
                            <td class="px-5 py-3 text-right space-x-3">
                                <a :href="c.file_path" target="_blank" class="text-xs text-purple-500 hover:underline">View</a>
                                <button @click="remove(c)" class="text-xs text-red-400 hover:underline">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!filtered.length">
                            <td colspan="6" class="px-5 py-10 text-center text-gray-400">No circulars found.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    sahodaya:  Object,
    circulars: { type: Array, default: () => [] },
});

const categories     = ['General', 'Academic', 'Kalotsav', 'Meeting', 'Exam', 'Sports', 'Finance', 'Other'];
const activeCategory = ref('');

const filtered = computed(() =>
    activeCategory.value
        ? props.circulars.filter(c => c.category === activeCategory.value)
        : props.circulars
);

const form = useForm({
    title:           '',
    circular_number: '',
    category:        'General',
    issued_date:     '',
    academic_year:   '',
    file:            null,
});

function upload() {
    form.post(`/sahodaya-admin/${props.sahodaya.id}/circulars`, {
        forceFormData: true,
        onSuccess: () => form.reset(),
    });
}

function remove(c) {
    if (!confirm(`Delete circular "${c.title}"?`)) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/circulars/${c.id}`);
}
</script>
