<template>
    <SchoolAdminLayout title="Teachers" :school="school" :show-header-title="false">
        <PageHeader title="Teachers" eyebrow="Students"
            description="Student records, teachers, and portal access." />


        <div class="space-y-4">
            <form @submit.prevent="addTeacher" class="card space-y-3">
                <h3 class="font-semibold text-gray-900 text-sm">Add teacher</h3>
                <div class="grid sm:grid-cols-2 gap-3">
                    <input v-model="form.name" class="field" placeholder="Full name" required>
                    <input v-model="form.email" type="email" class="field" placeholder="Email (for portal login)">
                    <input v-model="form.designation" class="field" placeholder="Designation">
                    <input v-model="form.subject" class="field" placeholder="Subject">
                </div>
                <label v-if="form.email" class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" v-model="form.create_login" class="rounded">
                    Create portal login
                </label>
                <input v-if="form.create_login && form.email" v-model="form.password" type="password"
                       class="field max-w-xs" placeholder="Portal password (min 8 chars)" minlength="8">
                <button class="btn-primary">Add teacher</button>
            </form>

            <div class="card card--flush overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="p-3">Photo</th>
                            <th class="p-3">Name</th>
                            <th class="p-3">Email</th>
                            <th class="p-3">Designation</th>
                            <th class="p-3">Portal</th>
                            <th class="p-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="t in teachers" :key="t.id" class="border-t">
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <img v-if="t.photo_url" :src="t.photo_url" :alt="t.name"
                                         class="h-10 w-10 rounded-full object-cover border border-slate-200">
                                    <span v-else class="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-500">
                                        {{ initials(t.name) }}
                                    </span>
                                    <label class="cursor-pointer text-xs font-semibold text-[#0f3d7a]">
                                        Upload
                                        <input type="file" accept="image/*" class="sr-only"
                                               @change="uploadPhoto(t, $event)">
                                    </label>
                                </div>
                            </td>
                            <td class="p-3 font-medium">{{ t.name }}</td>
                            <td class="p-3 text-gray-600">{{ t.email || '—' }}</td>
                            <td class="p-3 text-gray-600">{{ t.designation || '—' }}</td>
                            <td class="p-3">
                                <span v-if="t.user_id" class="text-xs text-green-700 bg-green-50 px-2 py-0.5 rounded">Active</span>
                                <span v-else class="text-xs text-gray-400">—</span>
                            </td>
                            <td class="p-3 text-right">
                                <button @click="remove(t)" class="text-red-600 text-xs font-semibold">Remove</button>
                            </td>
                        </tr>
                        <tr v-if="!teachers.length">
                            <td colspan="6" class="p-6 text-center text-gray-400">No teachers yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import { useForm, router } from '@inertiajs/vue3';
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';

const props = defineProps({ school: Object, teachers: Array });

const form = useForm({
    name: '',
    email: '',
    designation: '',
    subject: '',
    create_login: false,
    password: '',
});

function addTeacher() {
    form.post(`/school-admin/${props.school.id}/teachers`, {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}

function remove(teacher) {
    if (!confirm(`Remove ${teacher.name}?`)) return;
    router.delete(`/school-admin/${props.school.id}/teachers/${teacher.id}`, { preserveScroll: true });
}

function initials(name) {
    return (name || '?').split(/\s+/).slice(0, 2).map((p) => p[0]?.toUpperCase() ?? '').join('') || '?';
}

function uploadPhoto(teacher, event) {
    const file = event.target.files?.[0];
    if (!file) return;
    const data = new FormData();
    data.append('photo', file);
    router.post(`/school-admin/${props.school.id}/teachers/${teacher.id}/photo`, data, {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => { event.target.value = ''; },
    });
}
</script>
