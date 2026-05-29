<template>
    <SchoolAdminLayout title="Testimonials" :school="school">
        <div class="space-y-6">
            <!-- Add / Edit form -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="font-bold text-gray-800 mb-4">{{ editing ? 'Edit Testimonial' : 'Add Testimonial' }}</h3>
                <form @submit.prevent="save" class="space-y-4">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Name *</label>
                            <input v-model="form.name" type="text" required
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Designation</label>
                            <input v-model="form.designation" type="text" placeholder="Parent of Class X Student, Alumnus..."
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Display Order</label>
                            <input v-model="form.display_order" type="number" min="0" placeholder="0"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Testimonial Quote *</label>
                            <textarea v-model="form.quote" rows="4" required
                                      placeholder="Share their experience..."
                                      class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 resize-none"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Photo</label>
                            <input type="file" accept="image/*" @change="form.photo = $event.target.files[0]"
                                   class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-teal-50 file:text-teal-700 hover:file:bg-teal-100">
                        </div>
                        <div class="flex items-center gap-2 pt-5">
                            <input type="checkbox" id="is_active" v-model="form.is_active" class="rounded">
                            <label for="is_active" class="text-sm text-gray-700">Show on website</label>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" :disabled="form.processing"
                                class="bg-teal-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-teal-700 transition disabled:opacity-50">
                            {{ editing ? 'Save Changes' : 'Add Testimonial' }}
                        </button>
                        <button v-if="editing" type="button" @click="cancelEdit"
                                class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- List -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Name</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Designation</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Order</th>
                            <th class="text-left px-5 py-3 text-xs font-semibold text-gray-500 uppercase">Active</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="t in testimonials" :key="t.id" class="hover:bg-gray-50">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <img v-if="t.photo" :src="t.photo" class="w-9 h-9 rounded-full object-cover border border-gray-100 shrink-0">
                                    <div class="w-9 h-9 rounded-full bg-teal-50 flex items-center justify-center text-teal-600 font-bold text-sm shrink-0" v-else>
                                        {{ t.name[0] }}
                                    </div>
                                    <p class="font-medium text-gray-800">{{ t.name }}</p>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-gray-500 text-xs">{{ t.designation || '—' }}</td>
                            <td class="px-5 py-3 text-gray-400 text-xs">{{ t.display_order ?? '—' }}</td>
                            <td class="px-5 py-3">
                                <span :class="t.is_active ? 'bg-green-50 text-green-700' : 'text-gray-300'"
                                      class="text-xs font-medium">
                                    {{ t.is_active ? '● Active' : '○ Hidden' }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right space-x-3">
                                <button @click="startEdit(t)" class="text-xs text-blue-500 hover:underline">Edit</button>
                                <button @click="remove(t)" class="text-xs text-red-400 hover:underline">Delete</button>
                            </td>
                        </tr>
                        <tr v-if="!testimonials.length">
                            <td colspan="5" class="px-5 py-10 text-center text-gray-400">No testimonials yet.</td>
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
    testimonials: { type: Array, default: () => [] },
});

const editing = ref(null);

const form = useForm({
    name:          '',
    designation:   '',
    quote:         '',
    display_order: '',
    is_active:     true,
    photo:         null,
});

function startEdit(t) {
    editing.value        = t.id;
    form.name            = t.name;
    form.designation     = t.designation ?? '';
    form.quote           = t.quote;
    form.display_order   = t.display_order ?? '';
    form.is_active       = t.is_active ?? true;
    form.photo           = null;
}

function cancelEdit() {
    editing.value = null;
    form.reset();
    form.is_active = true;
}

function save() {
    if (editing.value) {
        form.transform(d => ({ ...d, _method: 'PUT' }))
            .post(`/school-admin/${props.school.id}/testimonials/${editing.value}`, {
                forceFormData: true,
                onSuccess: () => { editing.value = null; form.reset(); form.is_active = true; },
            });
    } else {
        form.post(`/school-admin/${props.school.id}/testimonials`, {
            forceFormData: true,
            onSuccess: () => { form.reset(); form.is_active = true; },
        });
    }
}

function remove(t) {
    if (!confirm(`Delete testimonial from "${t.name}"?`)) return;
    router.delete(`/school-admin/${props.school.id}/testimonials/${t.id}`);
}
</script>