<template>
    <PortalLayout
        role-label="Student Portal"
        title="My profile"
        :subtitle="school.name"
        accent="indigo"
        :nav-items="navItems"
    >
        <form @submit.prevent="saveProfile" class="card mb-4 space-y-3">
            <h2 class="font-semibold text-sm">Contact details</h2>
            <label class="block text-sm">
                <span class="text-gray-600">Email</span>
                <input v-model="profileForm.email" type="email" class="field mt-1" required>
            </label>
            <label class="block text-sm">
                <span class="text-gray-600">Parent phone</span>
                <input v-model="profileForm.parent_phone" type="text" class="field mt-1">
            </label>
            <button type="submit" class="btn-primary text-sm" :disabled="profileForm.processing">Save profile</button>
        </form>

        <form @submit.prevent="savePassword" class="card space-y-3">
            <h2 class="font-semibold text-sm">Change password</h2>
            <label class="block text-sm">
                <span class="text-gray-600">Current password</span>
                <input v-model="passwordForm.current_password" type="password" class="field mt-1" required>
            </label>
            <label class="block text-sm">
                <span class="text-gray-600">New password</span>
                <input v-model="passwordForm.password" type="password" class="field mt-1" required>
            </label>
            <label class="block text-sm">
                <span class="text-gray-600">Confirm new password</span>
                <input v-model="passwordForm.password_confirmation" type="password" class="field mt-1" required>
            </label>
            <button type="submit" class="btn-secondary text-sm" :disabled="passwordForm.processing">Update password</button>
        </form>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    school: Object,
    student: Object,
    user: Object,
});

const profileForm = useForm({
    email: props.user.email ?? '',
    parent_phone: props.student.parent_phone ?? '',
});

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const navItems = computed(() => [
    { href: `/portal/student/${props.school.id}`, label: 'Dashboard' },
    { href: `/portal/student/${props.school.id}/profile`, label: 'Profile' },
]);

function saveProfile() {
    profileForm.put(`/portal/student/${props.school.id}/profile`, { preserveScroll: true });
}

function savePassword() {
    passwordForm.put(`/portal/student/${props.school.id}/profile/password`, {
        preserveScroll: true,
        onSuccess: () => passwordForm.reset(),
    });
}
</script>
