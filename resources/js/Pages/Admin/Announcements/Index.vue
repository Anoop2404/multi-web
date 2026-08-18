<template>
    <AdminLayout title="Announcements">
        <div class="space-y-4 max-w-4xl">
            <p class="text-sm text-gray-600">
                Shown as a banner to the audience you pick, on every admin page, for as long as the window below is open.
                A <strong>maintenance</strong> notice is informational only — it does not block access.
            </p>

            <form @submit.prevent="create" class="card space-y-3">
                <h3 class="font-semibold text-sm">New announcement</h3>

                <div v-if="Object.keys(form.errors).length > 0" class="p-3 text-xs bg-red-50 text-red-700 rounded-lg border border-red-200">
                    <ul class="list-disc pl-4 space-y-0.5">
                        <li v-for="(err, key) in form.errors" :key="key">{{ err }}</li>
                    </ul>
                </div>

                <input v-model="form.title" class="field w-full" placeholder="Title" maxlength="255" required>
                <textarea v-model="form.body" class="field w-full" rows="3" placeholder="Message" required></textarea>

                <div class="grid sm:grid-cols-4 gap-3">
                    <select v-model="form.type" class="field">
                        <option value="info">Info</option>
                        <option value="warning">Warning</option>
                        <option value="critical">Critical</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                    <select v-model="form.audience" class="field">
                        <option value="all">Everyone</option>
                        <option value="superadmin">Superadmins</option>
                        <option value="state_admin">State admins</option>
                        <option value="sahodaya_admin">Sahodaya admins</option>
                        <option value="school_admin">School admins</option>
                    </select>
                    <input v-model="form.starts_at" type="datetime-local" class="field" title="Starts (optional — immediate if blank)">
                    <input v-model="form.ends_at" type="datetime-local" class="field" title="Ends (optional — indefinite if blank)">
                </div>
                <button class="btn-primary" :disabled="form.processing">Create announcement</button>
            </form>

            <div class="card card--flush">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3">Title</th>
                            <th class="p-3">Type</th>
                            <th class="p-3">Audience</th>
                            <th class="p-3">Window</th>
                            <th class="p-3">Status</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="a in announcements" :key="a.id" class="border-t align-top">
                            <td class="p-3">
                                <p class="font-medium">{{ a.title }}</p>
                                <p class="text-xs text-gray-500 mt-0.5 max-w-xs">{{ a.body }}</p>
                            </td>
                            <td class="p-3 text-xs">{{ typeLabels[a.type] }}</td>
                            <td class="p-3 text-xs">{{ audienceLabels[a.audience] }}</td>
                            <td class="p-3 text-xs text-gray-600">
                                {{ a.starts_at ? formatDateTime(a.starts_at) : 'Now' }} →
                                {{ a.ends_at ? formatDateTime(a.ends_at) : 'Indefinite' }}
                            </td>
                            <td class="p-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                      :class="a.is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'">
                                    {{ a.is_active ? 'Active' : 'Disabled' }}
                                </span>
                            </td>
                            <td class="p-3 text-right space-x-2 whitespace-nowrap">
                                <button @click="openEdit(a)" class="text-indigo-600 text-xs font-semibold">Edit</button>
                                <button @click="remove(a)" class="text-red-600 text-xs font-semibold">Remove</button>
                            </td>
                        </tr>
                        <tr v-if="!announcements.length">
                            <td colspan="6" class="p-6 text-center text-gray-400">No announcements yet — create one above.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Modal :show="!!editing" :title="editing ? `Edit ${editing.title}` : ''" @close="editing = null">
            <form @submit.prevent="saveEdit" class="space-y-3">
                <div v-if="Object.keys(editForm.errors).length > 0" class="p-3 text-xs bg-red-50 text-red-700 rounded-lg border border-red-200">
                    <ul class="list-disc pl-4 space-y-0.5">
                        <li v-for="(err, key) in editForm.errors" :key="key">{{ err }}</li>
                    </ul>
                </div>

                <input v-model="editForm.title" class="field w-full" placeholder="Title" maxlength="255" required>
                <textarea v-model="editForm.body" class="field w-full" rows="3" placeholder="Message" required></textarea>

                <div class="grid sm:grid-cols-2 gap-3">
                    <select v-model="editForm.type" class="field">
                        <option value="info">Info</option>
                        <option value="warning">Warning</option>
                        <option value="critical">Critical</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                    <select v-model="editForm.audience" class="field">
                        <option value="all">Everyone</option>
                        <option value="superadmin">Superadmins</option>
                        <option value="state_admin">State admins</option>
                        <option value="sahodaya_admin">Sahodaya admins</option>
                        <option value="school_admin">School admins</option>
                    </select>
                    <input v-model="editForm.starts_at" type="datetime-local" class="field" title="Starts (optional — immediate if blank)">
                    <input v-model="editForm.ends_at" type="datetime-local" class="field" title="Ends (optional — indefinite if blank)">
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
import { useForm, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { useConfirm } from '@/composables/useConfirm';
import { formatDateTime } from '@/support/calendarDates.js';

const { confirm } = useConfirm();

defineProps({ announcements: { type: Array, default: () => [] } });

const typeLabels = { info: 'Info', warning: 'Warning', critical: 'Critical', maintenance: 'Maintenance' };
const audienceLabels = {
    all: 'Everyone', superadmin: 'Superadmins', state_admin: 'State admins',
    sahodaya_admin: 'Sahodaya admins', school_admin: 'School admins',
};

function emptyForm() {
    return { title: '', body: '', type: 'info', audience: 'all', starts_at: '', ends_at: '', is_active: true };
}

const form = useForm(emptyForm());
const editing = ref(null);
const editForm = useForm(emptyForm());

function create() {
    form.post('/admin/announcements', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function openEdit(announcement) {
    editing.value = announcement;
    editForm.title = announcement.title;
    editForm.body = announcement.body;
    editForm.type = announcement.type;
    editForm.audience = announcement.audience;
    editForm.starts_at = announcement.starts_at ? announcement.starts_at.slice(0, 16) : '';
    editForm.ends_at = announcement.ends_at ? announcement.ends_at.slice(0, 16) : '';
    editForm.is_active = announcement.is_active;
    editForm.clearErrors();
}

function saveEdit() {
    editForm.put(`/admin/announcements/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => { editing.value = null; },
    });
}

async function remove(announcement) {
    if (!(await confirm({ message: `Remove "${announcement.title}"?`, destructive: true }))) return;
    router.delete(`/admin/announcements/${announcement.id}`, { preserveScroll: true });
}
</script>
