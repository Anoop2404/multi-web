<template>
    <SchoolAdminLayout title="Achievements" :school="school" :show-header-title="false">
        <PageHeader title="Achievements" eyebrow="Website"
            description="School website content and public pages." />


        <div class="space-y-6">
            <!-- Add form -->
            <div class="card">
                <h3 class="font-bold text-gray-800 mb-4">{{ editing ? 'Edit Achievement' : 'Add Achievement' }}</h3>
                <form @submit.prevent="save" class="space-y-4">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="form-label mb-1.5">Title *</label>
                            <input v-model="form.title" type="text" required
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Category</label>
                            <select v-model="form.category"
                                    class="field">
                                <option value="">— Select —</option>
                                <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Level</label>
                            <select v-model="form.level"
                                    class="field">
                                <option value="">— Select —</option>
                                <option v-for="l in levels" :key="l" :value="l">{{ l }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Date Achieved</label>
                            <input v-model="form.achieved_at" type="date"
                                   class="field">
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Photo / Trophy Image</label>
                            <input type="file" accept="image/*" @change="form.image = $event.target.files[0]"
                                   class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label mb-1.5">Description</label>
                            <textarea v-model="form.description" rows="3"
                                      class="field resize-none"></textarea>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" :disabled="form.processing"
                                class="bg-amber-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-amber-700 transition disabled:opacity-50">
                            {{ editing ? 'Save Changes' : 'Add Achievement' }}
                        </button>
                        <button v-if="editing" type="button" @click="cancelEdit"
                                class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- List -->
            <div class="card card--flush">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Achievement</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Category</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Level</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Date</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="item in achievements" :key="item.id" class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <img v-if="item.image" :src="item.image" class="h-10 w-10 object-cover rounded-lg border border-gray-100">
                                    <div class="w-10 h-10 bg-amber-50 rounded-lg flex items-center justify-center text-amber-600 text-lg" v-else>★</div>
                                    <div>
                                        <p class="font-medium text-gray-800">{{ item.title }}</p>
                                        <p v-if="item.description" class="text-xs text-gray-400 line-clamp-1">{{ item.description }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-3">
                                <span v-if="item.category" class="text-xs bg-amber-50 text-amber-700 px-2 py-0.5 rounded-full font-medium">{{ item.category }}</span>
                                <span v-else class="text-gray-300">—</span>
                            </td>
                            <td class="px-5 py-3 text-gray-500 text-xs">{{ item.level || '—' }}</td>
                            <td class="px-5 py-3 text-gray-400 text-xs">
                                {{ item.achieved_at ? new Date(item.achieved_at).toLocaleDateString('en-IN') : '—' }}
                            </td>
                            <td class="px-5 py-3 text-right space-x-3">
                                <button @click="startEdit(item)" class="text-xs text-blue-500 hover:underline">Edit</button>
                                <button @click="remove(item)" class="text-xs text-red-400 hover:underline">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!achievements.length">
                            <td colspan="5" class="px-5 py-10 text-center text-gray-400">No achievements yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    school:       Object,
    achievements: { type: Array, default: () => [] },
});

const categories = ['Academic', 'Sports', 'Cultural', 'Science', 'Arts', 'Social', 'Other'];
const levels     = ['School Level', 'District Level', 'State Level', 'National Level', 'International Level'];

const editing = ref(null);

const form = useForm({
    title:       '',
    category:    '',
    level:       '',
    achieved_at: '',
    description: '',
    image:       null,
});

function startEdit(item) {
    editing.value = item.id;
    form.title       = item.title;
    form.category    = item.category ?? '';
    form.level       = item.level ?? '';
    form.achieved_at = item.achieved_at?.slice(0, 10) ?? '';
    form.description = item.description ?? '';
    form.image       = null;
}

function cancelEdit() {
    editing.value = null;
    form.reset();
}

function save() {
    if (editing.value) {
        form.transform(d => ({ ...d, _method: 'PUT' }))
            .post(`/school-admin/${props.school.id}/achievements/${editing.value}`, {
                forceFormData: true,
                onSuccess: () => { editing.value = null; form.reset(); },
            });
    } else {
        form.post(`/school-admin/${props.school.id}/achievements`, {
            forceFormData: true,
            onSuccess: () => form.reset(),
        });
    }
}

function remove(item) {
    if (!confirm(`Delete "${item.title}"?`)) return;
    router.delete(`/school-admin/${props.school.id}/achievements/${item.id}`);
}
</script>
