<template>
    <PortalLayout
        role-label="Student Portal"
        title="My profile"
        :subtitle="school.name"
        accent="indigo"
        :nav-items="navItems"
    >
        <div v-if="studentEditLock?.locked" class="notice-banner notice-banner--warning mb-4 text-sm">
            <p class="font-semibold text-amber-900">Student records locked</p>
            <p class="mt-1 text-amber-900/90">
                {{ studentEditLock.message ?? 'Your school cannot edit student records right now.' }}
                Ask your school admin to submit a change request on your behalf.
            </p>
        </div>

        <section class="card mb-4">
            <h2 class="font-semibold text-sm mb-3">Student details</h2>
            <div class="flex items-start gap-4">
                <div class="shrink-0">
                    <img
                        v-if="student.photo_url"
                        :src="student.photo_url"
                        :alt="student.name"
                        class="h-20 w-20 rounded-lg object-cover border border-gray-200"
                    >
                    <div
                        v-else
                        class="h-20 w-20 rounded-lg border border-dashed border-gray-300 bg-gray-50 flex items-center justify-center text-xs text-gray-400 text-center px-1"
                    >
                        No photo
                    </div>
                </div>
                <dl class="flex-1 grid gap-3 sm:grid-cols-2 text-sm min-w-0">
                    <div>
                        <dt class="text-gray-500">Name</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ student.name }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Register no.</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ student.reg_no }}</dd>
                    </div>
                </dl>
            </div>
        </section>

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
import { studentPortalNavItems } from '@/support/studentPortalNav.js';

const props = defineProps({
    school: Object,
    student: Object,
    user: Object,
    studentEditLock: { type: Object, default: () => ({ locked: false }) },
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

const navItems = computed(() => studentPortalNavItems(props.school.id));

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
