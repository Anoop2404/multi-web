<template>
    <AdminLayout title="State users">
        <div class="space-y-4 max-w-4xl">
            <div class="flex items-start justify-between gap-3">
                <p class="text-sm text-gray-600">
                    Manage state-level administrators and read-only state staff. These accounts have platform-wide access (not tied to a Sahodaya tenant).
                </p>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" class="btn-secondary text-sm whitespace-nowrap" @click="exportCredentials">
                        ↓ Export credentials
                    </button>
                    <button type="button" class="btn-primary text-sm whitespace-nowrap" @click="openCreate">
                        + New user
                    </button>
                </div>
            </div>

            <div class="card flex flex-wrap items-center gap-3 py-3">
                <label class="text-xs font-semibold text-gray-500" for="role-filter">Filter by role</label>
                <select id="role-filter" v-model="roleFilter" class="field field--sm max-w-xs">
                    <option value="all">All roles ({{ users.length }})</option>
                    <option v-for="r in assignableRoles" :key="r.value" :value="r.value">
                        {{ r.label }} ({{ countForRole(r.value) }})
                    </option>
                </select>
                <span class="text-xs text-gray-400">{{ filteredUsers.length }} of {{ users.length }} shown</span>
            </div>

            <div class="card card--flush">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3">Name</th>
                            <th class="p-3">Email</th>
                            <th class="p-3">Roles</th>
                            <th class="p-3">State</th>
                            <th class="p-3">Status</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in filteredUsers" :key="u.id" class="border-t align-top">
                            <td class="p-3 font-medium">{{ u.name }}</td>
                            <td class="p-3 text-gray-600">{{ u.email }}</td>
                            <td class="p-3 text-xs">{{ u.roles.join(', ') }}</td>
                            <td class="p-3 text-xs">
                                <span v-if="u.state_name" class="text-gray-700">{{ u.state_name }}</span>
                                <span v-else class="text-amber-600 font-medium">Unassigned</span>
                            </td>
                            <td class="p-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                      :class="u.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'">
                                    {{ u.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="p-3 text-right space-x-2 whitespace-nowrap">
                                <button @click="openEdit(u)" class="text-indigo-600 text-xs font-semibold">Edit</button>
                                <button @click="toggleActive(u)" class="text-xs font-semibold" :class="u.is_active ? 'text-amber-600' : 'text-emerald-600'">
                                    {{ u.is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                                <button @click="remove(u)" class="text-red-600 text-xs font-semibold">Remove</button>
                            </td>
                        </tr>
                        <tr v-if="!users.length">
                            <td colspan="6" class="p-6 text-center text-gray-400">No state users yet.</td>
                        </tr>
                        <tr v-else-if="!filteredUsers.length">
                            <td colspan="6" class="p-6 text-center text-gray-400">No users with this role.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="creating" title="New state user" @close="creating = false">
            <form @submit.prevent="createUser" class="space-y-3">
                <div v-if="Object.keys(form.errors).length > 0" class="p-3 text-xs bg-red-50 text-red-700 rounded-lg border border-red-200">
                    <ul class="list-disc pl-4 space-y-0.5">
                        <li v-for="(err, key) in form.errors" :key="key">{{ err }}</li>
                    </ul>
                </div>

                <input v-model="form.name" class="field w-full" placeholder="Full name" required>
                <input v-model="form.email" type="email" class="field w-full" placeholder="Email" required>
                <input v-model="form.password" type="password" class="field w-full" placeholder="Password (min 8)" minlength="8" required>
                <select v-model="form.state_id" class="field w-full">
                    <option value="">No state assigned yet (locks them out until assigned)</option>
                    <option v-for="s in states" :key="s.id" :value="s.id">{{ s.name }} ({{ s.code }})</option>
                </select>
                <div>
                    <p class="text-xs font-semibold text-gray-500 mb-1">Roles <span class="text-red-500">*</span></p>
                    <div class="flex flex-wrap gap-2">
                        <label v-for="r in assignableRoles" :key="r.value" class="text-xs flex items-center gap-1 border rounded-lg px-2 py-1 cursor-pointer hover:bg-gray-50">
                            <input type="checkbox" :value="r.value" v-model="form.roles">
                            {{ r.label }}
                        </label>
                    </div>
                    <p v-if="form.errors.roles" class="text-xs text-red-600 mt-1">{{ form.errors.roles }}</p>
                </div>
                <div class="flex gap-2 justify-end pt-2">
                    <button type="button" @click="creating = false" class="px-3 py-2 text-sm text-gray-600">Cancel</button>
                    <button class="btn-primary" :disabled="form.processing">
                        {{ form.processing ? 'Creating…' : 'Create user' }}
                    </button>
                </div>
            </form>
        </Modal>

        <Modal :show="!!editing" :title="editing ? `Edit ${editing.name}` : ''" @close="editing = null">
            <form @submit.prevent="saveEdit" class="space-y-3">
                <div v-if="Object.keys(editForm.errors).length > 0" class="p-3 text-xs bg-red-50 text-red-700 rounded-lg border border-red-200">
                    <ul class="list-disc pl-4 space-y-0.5">
                        <li v-for="(err, key) in editForm.errors" :key="key">{{ err }}</li>
                    </ul>
                </div>

                <input v-model="editForm.name" class="field w-full" placeholder="Full name" required>
                <input v-model="editForm.email" type="email" class="field w-full" placeholder="Email" required>
                <input v-model="editForm.password" type="password" class="field w-full" placeholder="New password (leave blank to keep)">
                <select v-model="editForm.state_id" class="field w-full">
                    <option value="">No state assigned yet (locks them out until assigned)</option>
                    <option v-for="s in states" :key="s.id" :value="s.id">{{ s.name }} ({{ s.code }})</option>
                </select>
                <div class="flex flex-wrap gap-2">
                    <label v-for="r in assignableRoles" :key="r.value" class="text-xs flex items-center gap-1 border rounded-lg px-2 py-1 cursor-pointer hover:bg-gray-50">
                        <input type="checkbox" :value="r.value" v-model="editForm.roles">
                        {{ r.label }}
                    </label>
                </div>
                <div class="flex gap-2 justify-end pt-2">
                    <button type="button" @click="editing = null" class="px-3 py-2 text-sm text-gray-600">Cancel</button>
                    <button class="btn-primary" :disabled="editForm.processing">Save</button>
                </div>
            </form>
        </Modal>
    </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Modal from '@/Components/ui/Modal.vue';
import { useConfirm } from '@/composables/useConfirm';

const { confirm } = useConfirm();

const props = defineProps({ users: Array, states: { type: Array, default: () => [] }, assignableRoles: Array });

const creating = ref(false);
const roleFilter = ref('all');

const filteredUsers = computed(() => {
    if (roleFilter.value === 'all') return props.users;
    return props.users.filter((u) => u.roles.includes(roleFilter.value));
});

function countForRole(role) {
    return props.users.filter((u) => u.roles.includes(role)).length;
}

const form = useForm({ name: '', email: '', password: '', roles: ['state_admin'], state_id: '' });
const editing = ref(null);
const editForm = useForm({ name: '', email: '', password: '', roles: [], state_id: '' });

function openCreate() {
    form.reset();
    form.clearErrors();
    creating.value = true;
}

function createUser() {
    form.post('/admin/state-users', {
        preserveScroll: true,
        onSuccess: () => { form.reset('name', 'email', 'password', 'state_id'); creating.value = false; },
    });
}

function openEdit(user) {
    editing.value = user;
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.password = '';
    editForm.roles = [...user.roles];
    editForm.state_id = user.state_id ?? '';
    editForm.clearErrors();
}

function saveEdit() {
    editForm.put(`/admin/state-users/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; },
    });
}

async function remove(user) {
    if (!(await confirm({ message: `Remove ${user.name}?`, destructive: true }))) return;
    router.delete(`/admin/state-users/${user.id}`, { preserveScroll: true });
}

async function toggleActive(user) {
    if (user.is_active && !(await confirm({ message: `Deactivate ${user.name}? They won't be able to log in until reactivated.`, destructive: true }))) return;
    router.patch(`/admin/state-users/${user.id}/toggle-active`, {}, { preserveScroll: true });
}

function exportCredentials() {
    window.location.href = '/admin/state-users/export-credentials';
}
</script>
