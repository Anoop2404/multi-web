<template>
    <SchoolAdminLayout title="Website forms" :school="school" :show-header-title="false">
        <PageHeader
            title="Website forms"
            eyebrow="Public website"
            description="Manage form fields, confirmation text, notifications, and messages received from the website."
        />

        <form class="card mb-6 grid gap-4 sm:grid-cols-2 items-end max-w-4xl" @submit.prevent="createForm">
            <div>
                <label class="form-label mb-1.5">New form name</label>
                <input v-model="create.name" class="field" required placeholder="Contact or feedback form">
            </div>
            <div>
                <label class="form-label mb-1.5">Notification email</label>
                <input v-model="create.notify_email" type="email" class="field" placeholder="office@school.org">
            </div>
            <button type="submit" class="btn-primary sm:col-span-2 w-fit" :disabled="create.processing">
                Create form
            </button>
        </form>

        <div class="space-y-4 max-w-5xl">
            <section v-for="item in forms" :key="item.id" class="card">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="font-bold text-slate-900">{{ item.name }}</h2>
                            <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                  :class="item.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">
                                {{ item.is_active ? 'Active' : 'Hidden' }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Public path: /forms/{{ item.slug }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ item.submissions_count || 0 }} messages received</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Link :href="`${base}/${item.id}/submissions`" class="btn-secondary text-sm">Messages</Link>
                        <button type="button" class="btn-secondary text-sm" @click="startEdit(item)">Edit form</button>
                        <button v-if="item.slug !== 'contact'" type="button" class="btn-ghost text-sm text-red-600" @click="remove(item)">Remove</button>
                    </div>
                </div>

                <form v-if="editingId === item.id" class="mt-6 border-t border-slate-100 pt-6 space-y-5" @submit.prevent="saveEdit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="form-label mb-1.5">Form name</label>
                            <input v-model="edit.name" class="field" required>
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Notification email</label>
                            <input v-model="edit.notify_email" type="email" class="field" placeholder="Uses the school contact email when blank">
                        </div>
                        <div class="sm:col-span-2">
                            <label class="form-label mb-1.5">Confirmation message</label>
                            <input v-model="edit.success_message" class="field" maxlength="500">
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-6">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="edit.is_active" type="checkbox" class="rounded">
                            Show this form publicly
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input v-model="edit.honeypot_enabled" type="checkbox" class="rounded">
                            Spam protection
                        </label>
                    </div>

                    <div>
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-slate-900">Form fields</h3>
                                <p class="text-xs text-slate-500">Labels and placeholders appear on the public website.</p>
                            </div>
                            <button type="button" class="btn-secondary text-sm" @click="addField">Add field</button>
                        </div>

                        <div class="space-y-3">
                            <div v-for="(field, index) in edit.fields_json" :key="index" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                                    <div>
                                        <label class="form-label mb-1">Label</label>
                                        <input v-model="field.label" class="field" required>
                                    </div>
                                    <div>
                                        <label class="form-label mb-1">Field key</label>
                                        <input v-model="field.key" class="field" pattern="[a-z][a-z0-9_]*" required>
                                    </div>
                                    <div>
                                        <label class="form-label mb-1">Type</label>
                                        <select v-model="field.type" class="field">
                                            <option value="text">Short text</option>
                                            <option value="email">Email</option>
                                            <option value="tel">Phone</option>
                                            <option value="textarea">Long message</option>
                                            <option value="date">Date</option>
                                            <option value="number">Number</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="form-label mb-1">Placeholder</label>
                                        <input v-model="field.placeholder" class="field">
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center justify-between">
                                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                        <input v-model="field.required" type="checkbox" class="rounded">
                                        Required
                                    </label>
                                    <button v-if="edit.fields_json.length > 1" type="button" class="text-xs font-semibold text-red-600" @click="edit.fields_json.splice(index, 1)">
                                        Remove field
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" class="btn-primary" :disabled="edit.processing">Save form</button>
                        <button type="button" class="btn-secondary" @click="cancelEdit">Cancel</button>
                    </div>
                </form>
            </section>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import { useConfirm } from '@/composables/useConfirm';

const props = defineProps({
    school: Object,
    forms: { type: Array, default: () => [] },
});

const base = `/school-admin/${props.school.id}/website/forms`;
const editingId = ref(null);
const { confirm } = useConfirm();
const create = useForm({ name: '', notify_email: '' });
const edit = useForm({
    name: '',
    notify_email: '',
    success_message: '',
    is_active: true,
    honeypot_enabled: true,
    fields_json: [],
});

function createForm() {
    create.post(base, { preserveScroll: true, onSuccess: () => create.reset() });
}

function startEdit(item) {
    editingId.value = item.id;
    edit.name = item.name;
    edit.notify_email = item.notify_email || '';
    edit.success_message = item.success_message || '';
    edit.is_active = Boolean(item.is_active);
    edit.honeypot_enabled = Boolean(item.honeypot_enabled);
    edit.fields_json = (item.fields_json || []).map(field => ({
        key: field.key || '',
        label: field.label || '',
        type: field.type || 'text',
        placeholder: field.placeholder || '',
        required: Boolean(field.required),
    }));
}

function addField() {
    edit.fields_json.push({ key: '', label: '', type: 'text', placeholder: '', required: false });
}

function saveEdit() {
    edit.put(`${base}/${editingId.value}`, {
        preserveScroll: true,
        onSuccess: cancelEdit,
    });
}

function cancelEdit() {
    editingId.value = null;
    edit.reset();
}

async function remove(item) {
    if (!(await confirm({ message: `Remove form "${item.name}"?`, destructive: true }))) return;
    router.delete(`${base}/${item.id}`, { preserveScroll: true });
}
</script>
