<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="My profile"
        :subtitle="school.name"
        accent="emerald"
        :nav-items="navItems"
    >
        <section class="card mb-4">
            <h2 class="font-semibold text-sm mb-3">Teacher details</h2>
            <dl v-if="teacher" class="grid gap-3 sm:grid-cols-2 text-sm">
                <div>
                    <dt class="text-gray-500">Name</dt>
                    <dd class="mt-0.5 font-medium text-gray-900">{{ teacher.name }}</dd>
                </div>
                <div v-if="teacher.designation">
                    <dt class="text-gray-500">Designation</dt>
                    <dd class="mt-0.5 font-medium text-gray-900">{{ teacher.designation }}</dd>
                </div>
                <div v-if="teacher.subject">
                    <dt class="text-gray-500">Subject</dt>
                    <dd class="mt-0.5 font-medium text-gray-900">{{ teacher.subject }}</dd>
                </div>
            </dl>
            <p v-else class="text-sm text-gray-500">No teacher record linked to your account.</p>
        </section>

        <form @submit.prevent="saveProfile" class="card mb-4 space-y-3">
            <h2 class="font-semibold text-sm">Contact details</h2>
            <p v-if="profileForm.errors.email || profileForm.errors.name" class="text-xs text-red-600">
                {{ profileForm.errors.name || profileForm.errors.email }}
            </p>
            <label class="block text-sm">
                <span class="text-gray-600">Full name</span>
                <input v-model="profileForm.name" type="text" class="field mt-1" required>
            </label>
            <label class="block text-sm">
                <span class="text-gray-600">Email</span>
                <input v-model="profileForm.email" type="email" class="field mt-1" required>
            </label>
            <label class="block text-sm">
                <span class="text-gray-600">Phone</span>
                <input v-model="profileForm.phone" type="text" class="field mt-1">
            </label>
            <label class="block text-sm">
                <span class="text-gray-600">Designation</span>
                <input v-model="profileForm.designation" type="text" class="field mt-1">
            </label>
            <button type="submit" class="btn-primary text-sm" :disabled="profileForm.processing">Save profile</button>
        </form>

        <form @submit.prevent="savePassword" class="card space-y-3">
            <h2 class="font-semibold text-sm">Change password</h2>
            <p v-if="passwordForm.errors.current_password || passwordForm.errors.password" class="text-xs text-red-600">
                {{ passwordForm.errors.current_password || passwordForm.errors.password }}
            </p>
            <label class="block text-sm">
                <span class="text-gray-600">Current password</span>
                <input v-model="passwordForm.current_password" type="password" class="field mt-1" required autocomplete="current-password">
            </label>
            <label class="block text-sm">
                <span class="text-gray-600">New password</span>
                <input v-model="passwordForm.password" type="password" class="field mt-1" required autocomplete="new-password">
            </label>
            <label class="block text-sm">
                <span class="text-gray-600">Confirm new password</span>
                <input v-model="passwordForm.password_confirmation" type="password" class="field mt-1" required autocomplete="new-password">
            </label>
            <button type="submit" class="btn-secondary text-sm" :disabled="passwordForm.processing">Update password</button>
        </form>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { useForm } from '@inertiajs/vue3';
import { teacherPortalNavItems } from '@/support/teacherPortalNav.js';

const props = defineProps({
    school:  Object,
    teacher: { type: Object, default: null },
    user:    Object,
});

const navItems = teacherPortalNavItems(props.school.id);

const profileForm = useForm({
    name:        props.user.name ?? '',
    email:       props.user.email ?? '',
    phone:       props.teacher?.phone ?? '',
    designation: props.teacher?.designation ?? '',
});

const passwordForm = useForm({
    current_password:      '',
    password:              '',
    password_confirmation: '',
});

function saveProfile() {
    profileForm.put(`/portal/teacher/${props.school.id}/profile`, { preserveScroll: true });
}

function savePassword() {
    passwordForm.put(`/portal/teacher/${props.school.id}/profile/password`, {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}
</script>
