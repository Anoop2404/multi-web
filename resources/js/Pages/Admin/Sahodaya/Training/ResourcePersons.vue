<template>
    <SahodayaAdminLayout title="Resource persons" :sahodaya="sahodaya" :publicUrl="publicUrl"
                         :pendingPaymentsCount="pendingPaymentsCount" :show-header-title="false">
        <PageHeader title="Resource persons / trainers" eyebrow="Teacher training"
                    description="Master list of trainers and facilitators. Assign them to sessions from each program.">
            <template #actions>
                <Link :href="`/sahodaya-admin/${sahodaya.id}/training`" class="btn-secondary text-sm">
                    ← Programs
                </Link>
                <button type="button" class="btn-primary text-sm" @click="showAdd = !showAdd">
                    {{ showAdd ? 'Cancel' : '+ Add person' }}
                </button>
            </template>
        </PageHeader>

        <form v-if="showAdd" @submit.prevent="createPerson" class="card mb-4 space-y-3">
            <h3 class="section-title">New resource person</h3>
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label class="label-xs">Name</label>
                    <input v-model="addForm.name" class="field" required placeholder="Full name">
                </div>
                <div>
                    <label class="label-xs">Designation</label>
                    <input v-model="addForm.designation" class="field" placeholder="e.g. Master Trainer">
                </div>
                <div>
                    <label class="label-xs">Email</label>
                    <input v-model="addForm.email" type="email" class="field" placeholder="optional">
                </div>
                <div>
                    <label class="label-xs">Mobile</label>
                    <input v-model="addForm.mobile" class="field" placeholder="optional">
                </div>
                <div class="sm:col-span-2">
                    <label class="label-xs">Bio</label>
                    <textarea v-model="addForm.bio" class="field" rows="2" placeholder="Short profile (optional)"></textarea>
                </div>
            </div>
            <button type="submit" class="btn-primary text-sm" :disabled="addForm.processing">Save</button>
        </form>

        <div class="card overflow-hidden p-0">
            <EmptyState v-if="!resourcePersons.length"
                        title="No resource persons yet"
                        description="Add trainers and facilitators to assign them when scheduling sessions." />
            <table v-else class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Designation</th>
                        <th>Programs</th>
                        <th>Sessions</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="person in resourcePersons" :key="person.id">
                        <td>
                            <template v-if="editId === person.id">
                                <input v-model="editForm.name" class="field !py-1 !text-xs mb-1" required>
                                <textarea v-model="editForm.bio" class="field !py-1 !text-xs" rows="2" placeholder="Bio"></textarea>
                            </template>
                            <div v-else>
                                <p class="font-medium text-slate-900">{{ person.name }}</p>
                                <p v-if="person.bio" class="text-xs text-slate-500 mt-0.5 line-clamp-2">{{ person.bio }}</p>
                            </div>
                        </td>
                        <td class="text-sm text-slate-600">
                            <template v-if="editId === person.id">
                                <input v-model="editForm.email" type="email" class="field !py-1 !text-xs mb-1" placeholder="Email">
                                <input v-model="editForm.mobile" class="field !py-1 !text-xs" placeholder="Mobile">
                            </template>
                            <template v-else>
                                <p>{{ person.email || '—' }}</p>
                                <p class="text-xs text-slate-400">{{ person.mobile || '' }}</p>
                            </template>
                        </td>
                        <td>
                            <input v-if="editId === person.id" v-model="editForm.designation" class="field !py-1 !text-xs">
                            <span v-else>{{ person.designation || '—' }}</span>
                        </td>
                        <td>{{ person.programs_count ?? 0 }}</td>
                        <td>{{ person.sessions_count ?? 0 }}</td>
                        <td>
                            <label v-if="editId === person.id" class="inline-flex items-center gap-1 text-xs">
                                <input v-model="editForm.is_active" type="checkbox" class="rounded"> Active
                            </label>
                            <span v-else :class="person.is_active ? 'text-emerald-700' : 'text-slate-400'" class="text-xs font-semibold">
                                {{ person.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <template v-if="editId === person.id">
                                <button type="button" class="text-xs font-semibold text-indigo-600 mr-2" @click="saveEdit(person)">Save</button>
                                <button type="button" class="text-xs text-slate-500" @click="editId = null">Cancel</button>
                            </template>
                            <template v-else>
                                <button type="button" class="text-xs font-semibold text-indigo-600 mr-2" @click="startEdit(person)">Edit</button>
                                <button type="button" class="text-xs text-red-600" @click="removePerson(person)">Remove</button>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </SahodayaAdminLayout>
</template>

<script setup>
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import SahodayaAdminLayout from '@/Layouts/SahodayaAdminLayout.vue';

const props = defineProps({
    sahodaya: Object,
    publicUrl: String,
    pendingPaymentsCount: Number,
    resourcePersons: { type: Array, default: () => [] },
});

const showAdd = ref(false);
const editId = ref(null);

const addForm = useForm({
    name: '',
    email: '',
    mobile: '',
    designation: '',
    bio: '',
    is_active: true,
});

const editForm = useForm({
    name: '',
    email: '',
    mobile: '',
    designation: '',
    bio: '',
    is_active: true,
});

function createPerson() {
    addForm.post(`/sahodaya-admin/${props.sahodaya.id}/training/resource-persons`, {
        preserveScroll: true,
        onSuccess: () => {
            addForm.reset();
            showAdd.value = false;
        },
    });
}

function startEdit(person) {
    editId.value = person.id;
    editForm.name = person.name ?? '';
    editForm.email = person.email ?? '';
    editForm.mobile = person.mobile ?? '';
    editForm.designation = person.designation ?? '';
    editForm.bio = person.bio ?? '';
    editForm.is_active = !!person.is_active;
}

function saveEdit(person) {
    editForm.put(`/sahodaya-admin/${props.sahodaya.id}/training/resource-persons/${person.id}`, {
        preserveScroll: true,
        onSuccess: () => { editId.value = null; },
    });
}

function removePerson(person) {
    if (!window.confirm(`Remove ${person.name}? If assigned, they will be deactivated instead.`)) return;
    router.delete(`/sahodaya-admin/${props.sahodaya.id}/training/resource-persons/${person.id}`, {
        preserveScroll: true,
    });
}
</script>
