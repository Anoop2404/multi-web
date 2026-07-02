<template>
    <AdminLayout title="State users">
        <div class="space-y-4 max-w-4xl">
            <p class="text-sm text-gray-600">
                Manage state-level administrators and read-only state staff. These accounts have platform-wide access (not tied to a Sahodaya tenant).
            </p>

            <form @submit.prevent="createUser" class="card space-y-3">
                <h3 class="font-semibold text-sm">New state user</h3>
                <div class="grid sm:grid-cols-2 gap-3">
                    <input v-model="form.name" class="field" placeholder="Full name" required>
                    <input v-model="form.email" type="email" class="field" placeholder="Email" required>
                    <input v-model="form.password" type="password" class="field sm:col-span-2" placeholder="Password (min 8)" minlength="8" required>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-500 mb-1">Roles</p>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="r in assignableRoles" :key="r.value" class="text-xs flex items-center gap-1 border rounded-lg px-2 py-1">
                            <input type="checkbox" :value="r.value" v-model="form.roles">
                            {{ r.label }}
                        </label>
                    </div>
                </div>
                <button class="btn-primary" :disabled="form.processing">Create user</button>
            </form>

            <div class="card card--flush">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3">Name</th>
                            <th class="p-3">Email</th>
                            <th class="p-3">Roles</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in users" :key="u.id" class="border-t align-top">
                            <td class="p-3 font-medium">{{ u.name }}</td>
                            <td class="p-3 text-gray-600">{{ u.email }}</td>
                            <td class="p-3 text-xs">{{ u.roles.join(', ') }}</td>
                            <td class="p-3 text-right space-x-2">
                                <button @click="openEdit(u)" class="text-indigo-600 text-xs font-semibold">Edit</button>
                                <button @click="remove(u)" class="text-red-600 text-xs font-semibold">Remove</button>
                            </td>
                        </tr>
                        <tr v-if="!users.length">
                            <td colspan="4" class="p-6 text-center text-gray-400">No state users yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="editing = null">
            <form @submit.prevent="saveEdit" class="bg-white rounded-xl p-5 w-full max-w-md space-y-3 shadow-xl">
                <h3 class="font-semibold">Edit {{ editing.name }}</h3>
                <input v-model="editForm.name" class="field" placeholder="Full name" required>
                <input v-model="editForm.email" type="email" class="field" placeholder="Email" required>
                <input v-model="editForm.password" type="password" class="field" placeholder="New password (leave blank to keep)">
                <div class="flex flex-wrap gap-2">
                    <label v-for="r in assignableRoles" :key="r.value" class="text-xs flex items-center gap-1 border rounded-lg px-2 py-1">
                        <input type="checkbox" :value="r.value" v-model="editForm.roles">
                        {{ r.label }}
                    </label>
                </div>
                <div class="flex gap-2 justify-end pt-2">
                    <button type="button" @click="editing = null" class="px-3 py-2 text-sm text-gray-600">Cancel</button>
                    <button class="btn-primary" :disabled="editForm.processing">Save</button>
                </div>
            </form>
        </div>
    </AdminLayout>
</template>

<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps({ users: Array, assignableRoles: Array });

const form = useForm({ name: '', email: '', password: '', roles: [] });
const editing = ref(null);
const editForm = useForm({ name: '', email: '', password: '', roles: [] });

function createUser() {
    form.post('/admin/state-users', { preserveScroll: true, onSuccess: () => form.reset() });
}

function openEdit(user) {
    editing.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.password = '';
    editForm.roles = [...user.roles];
}

function saveEdit() {
    editForm.put(`/admin/state-users/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; },
    });
}

function remove(user) {
    if (!confirm(`Remove ${user.name}?`)) return;
    router.delete(`/admin/state-users/${user.id}`, { preserveScroll: true });
}
</script>

