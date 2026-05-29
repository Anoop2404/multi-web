<template>
    <SahodayaAdminLayout title="Office Bearers" :sahodaya="sahodaya">
        <div class="space-y-6">
            <!-- Add / Edit form -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h3 class="font-bold text-gray-800 mb-4">{{ editing ? 'Edit Office Bearer' : 'Add Office Bearer' }}</h3>
                <form @submit.prevent="save" class="space-y-4">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Full Name *</label>
                            <input v-model="form.name" type="text" required
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Role / Designation *</label>
                            <input v-model="form.role" type="text" required placeholder="President, Secretary, Treasurer..."
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">School Name</label>
                            <input v-model="form.school_name" type="text" placeholder="School they represent"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Phone</label>
                            <input v-model="form.phone" type="tel"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Term From (Year)</label>
                            <input v-model="form.term_from" type="number" min="2000" max="2099" placeholder="2024"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Term To (Year)</label>
                            <input v-model="form.term_to" type="number" min="2000" max="2099" placeholder="2025"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Photo</label>
                            <input type="file" accept="image/*" @change="form.photo = $event.target.files[0]"
                                   class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Display Order</label>
                            <input v-model="form.display_order" type="number" min="0"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit" :disabled="form.processing"
                                class="bg-purple-600 text-white px-5 py-2.5 rounded-lg text-sm font-semibold hover:bg-purple-700 transition disabled:opacity-50">
                            {{ editing ? 'Save Changes' : 'Add Bearer' }}
                        </button>
                        <button v-if="editing" type="button" @click="cancelEdit"
                                class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                    </div>
                </form>
            </div>

            <!-- List -->
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="b in bearers" :key="b.id"
                     class="bg-white rounded-xl shadow-sm border border-gray-100 p-5 flex items-center gap-4">
                    <img v-if="b.photo" :src="b.photo" class="w-14 h-14 rounded-full object-cover border border-gray-100 shrink-0">
                    <div class="w-14 h-14 rounded-full bg-purple-50 flex items-center justify-center text-purple-600 text-xl font-bold shrink-0" v-else>
                        {{ b.name[0] }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-gray-800 truncate">{{ b.name }}</p>
                        <p class="text-xs text-purple-600 font-medium">{{ b.role }}</p>
                        <p v-if="b.school_name" class="text-xs text-gray-400 truncate">{{ b.school_name }}</p>
                        <p v-if="b.term_from" class="text-xs text-gray-400">{{ b.term_from }}–{{ b.term_to }}</p>
                    </div>
                    <div class="flex flex-col gap-1 shrink-0">
                        <button @click="startEdit(b)" class="text-xs text-blue-500 hover:underline text-right">Edit</button>
                        <button @click="remove(b)" class="text-xs text-red-400 hover:underline text-right">Delete</button>
                    </div>
                </div>

                <div v-if="!bearers.length" class="sm:col-span-2 lg:col-span-3 bg-white rounded-xl border border-dashed border-gray-200 p-10 text-center text-gray-400">
                    No office bearers added yet.
                </div>
            </div>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';

const props = defineProps({
    sahodaya: Object,
    bearers:  { type: Array, default: () => [] },
});

const editing = ref(null);

const form = useForm({
    name:          '',
    role:          '',
    school_name:   '',
    phone:         '',
    term_from:     '',
    term_to:       '',
    display_order: 0,
    photo:         null,
});

function startEdit(b) {
    editing.value      = b.id;
    form.name          = b.name;
    form.role          = b.role;
    form.school_name   = b.school_name ?? '';
    form.phone         = b.phone ?? '';
    form.term_from     = b.term_from ?? '';
    form.term_to       = b.term_to ?? '';
    form.display_order = b.display_order ?? 0;
    form.photo         = null;
}

function cancelEdit() {
    editing.value = null;
    form.reset();
}

function save() {
    if (editing.value) {
        form.transform(d => ({ ...d, _method: 'PUT' }))
            .post(`/sahodaya-admin/${props.sahodaya.id}/office-bearers/${editing.value}`, {
                forceFormData: true,
                onSuccess: () => { editing.value = null; form.reset(); },
            });
    } else {
        form.post(`/sahodaya-admin/${props.sahodaya.id}/office-bearers`, {
            forceFormData: true,
            onSuccess: () => form.reset(),
        });
    }
}

function remove(b) {
    if (!confirm(`Remove "${b.name}"?`)) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/office-bearers/${b.id}`);
}
</script>
