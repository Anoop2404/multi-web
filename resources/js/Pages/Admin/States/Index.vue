<template>
    <AdminLayout title="States">
        <div class="space-y-4 max-w-4xl">
            <p class="text-sm text-gray-600">
                States sit above Sahodaya clusters. Create one, then assign state_admin accounts to it under
                <Link href="/admin/state-users" class="link-brand">State Users</Link>.
            </p>

            <form @submit.prevent="createState" class="card space-y-3">
                <h3 class="font-semibold text-sm">New state</h3>

                <div v-if="Object.keys(form.errors).length > 0" class="p-3 text-xs bg-red-50 text-red-700 rounded-lg border border-red-200">
                    <ul class="list-disc pl-4 space-y-0.5">
                        <li v-for="(err, key) in form.errors" :key="key">{{ err }}</li>
                    </ul>
                </div>

                <div class="grid sm:grid-cols-3 gap-3">
                    <div>
                        <input v-model="form.code" class="field w-full font-mono uppercase" placeholder="Code (e.g. KL)" maxlength="10" required>
                    </div>
                    <div class="sm:col-span-2">
                        <input v-model="form.name" class="field w-full" placeholder="State name" required>
                    </div>
                </div>
                <button class="btn-primary" :disabled="form.processing">Create state</button>
            </form>

            <div class="card card--flush">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3">Code</th>
                            <th class="p-3">Name</th>
                            <th class="p-3">Users</th>
                            <th class="p-3">Status</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="s in states" :key="s.id" class="border-t align-top">
                            <td class="p-3 font-mono text-xs">{{ s.code }}</td>
                            <td class="p-3 font-medium">{{ s.name }}</td>
                            <td class="p-3 text-xs text-gray-600">{{ s.platform_users_count }}</td>
                            <td class="p-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                      :class="s.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                    {{ s.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="p-3 text-right space-x-2 whitespace-nowrap">
                                <button @click="openEdit(s)" class="text-indigo-600 text-xs font-semibold">Edit</button>
                                <button @click="remove(s)" class="text-red-600 text-xs font-semibold">Remove</button>
                            </td>
                        </tr>
                        <tr v-if="!states.length">
                            <td colspan="5" class="p-6 text-center text-gray-400">No states yet — create one above.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="!!editing" :title="editing ? `Edit ${editing.name}` : ''" @close="editing = null">
            <form @submit.prevent="saveEdit" class="space-y-3">
                <div v-if="Object.keys(editForm.errors).length > 0" class="p-3 text-xs bg-red-50 text-red-700 rounded-lg border border-red-200">
                    <ul class="list-disc pl-4 space-y-0.5">
                        <li v-for="(err, key) in editForm.errors" :key="key">{{ err }}</li>
                    </ul>
                </div>

                <div class="grid sm:grid-cols-3 gap-3">
                    <input v-model="editForm.code" class="field font-mono uppercase" placeholder="Code" maxlength="10" required>
                    <input v-model="editForm.name" class="field sm:col-span-2" placeholder="State name" required>
                </div>
                <div class="grid sm:grid-cols-2 gap-3">
                    <input v-model="editForm.contact_name" class="field" placeholder="Contact name">
                    <input v-model="editForm.contact_email" type="email" class="field" placeholder="Contact email">
                    <input v-model="editForm.contact_phone" class="field" placeholder="Contact phone">
                    <input v-model="editForm.default_academic_year" class="field" placeholder="Default academic year (e.g. 2026-27)">
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input v-model="editForm.is_active" type="checkbox" class="rounded">
                    Active
                </label>
                <div class="flex gap-2 justify-end pt-2">
                    <button type="button" @click="editing = null" class="px-3 py-2 text-sm text-gray-600">Cancel</button>
                    <button class="btn-primary" :disabled="editForm.processing">Save</button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();

defineProps({ states: { type: Array, default: () => [] } });

const form = useForm({ code: '', name: '' });
const editing = ref(null);
const editForm = useForm({
    code: '', name: '', is_active: true,
    contact_name: '', contact_email: '', contact_phone: '', default_academic_year: '',
});

function createState() {
    form.post('/admin/states', {
        preserveScroll: true,
        onSuccess: () => form.reset('code', 'name'),
    });
}

function openEdit(state) {
    editing.value = state;
    editForm.code = state.code;
    editForm.name = state.name;
    editForm.is_active = state.is_active;
    editForm.contact_name = state.contact_name ?? '';
    editForm.contact_email = state.contact_email ?? '';
    editForm.contact_phone = state.contact_phone ?? '';
    editForm.default_academic_year = state.default_academic_year ?? '';
    editForm.clearErrors();
}

function saveEdit() {
    editForm.put(`/admin/states/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; },
    });
}

async function remove(state) {
    if (!(await confirm({ message: `Remove ${state.name}? This only works if it has no users or fest programs.`, destructive: true }))) return;
    router.delete(`/admin/states/${state.id}`, { preserveScroll: true });
}
</script>
