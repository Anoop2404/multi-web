<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="My profile"
        :subtitle="school.name"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="teacher?.photo_url"
        show-avatar-placeholder
    >
        <!-- Identity summary -->
        <section class="profile-section-card mb-6">
            <div class="profile-section-head">
                <div class="flex items-start gap-3">
                    <span class="profile-step-icon">👤</span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Teacher record</p>
                        <h2 class="text-lg font-bold text-slate-900">Teacher details</h2>
                        <p class="mt-1 text-sm text-slate-500">Maintained by your school administrator.</p>
                    </div>
                </div>
            </div>
            <div class="profile-section-body">
                <div v-if="teacher" class="flex items-start gap-4">
                    <div class="shrink-0">
                        <img
                            v-if="teacher.photo_url"
                            :src="teacher.photo_url"
                            :alt="teacher.name"
                            class="h-20 w-20 rounded-full object-cover border-2 border-[#eff6ff] shadow-sm"
                        >
                        <div
                            v-else
                            class="h-20 w-20 rounded-full border-2 border-dashed border-slate-300 bg-slate-50 flex items-center justify-center text-xs text-slate-400 text-center px-1"
                        >
                            No photo
                        </div>
                    </div>
                    <dl class="flex-1 grid gap-3 sm:grid-cols-2 text-sm min-w-0">
                        <div>
                            <dt class="text-slate-500">Name</dt>
                            <dd class="mt-0.5 font-medium text-slate-900">{{ teacher.name }}</dd>
                        </div>
                        <div v-if="teacher.reg_no">
                            <dt class="text-slate-500">Register no.</dt>
                            <dd class="mt-0.5 font-medium text-slate-900">{{ teacher.reg_no }}</dd>
                        </div>
                        <div v-if="teacher.designation">
                            <dt class="text-slate-500">Designation</dt>
                            <dd class="mt-0.5 font-medium text-slate-900">{{ teacher.designation }}</dd>
                        </div>
                        <div v-if="teacher.subject">
                            <dt class="text-slate-500">Subject</dt>
                            <dd class="mt-0.5 font-medium text-slate-900">{{ teacher.subject }}</dd>
                        </div>
                    </dl>
                </div>
                <EmptyState v-else title="No teacher record linked" description="Ask your school administrator to link your account to a teacher record." icon="👤" />
                <p v-if="teacher && !teacher.photo_url" class="text-xs text-slate-400 mt-3">
                    Profile photo is uploaded by your school admin from the Teachers page.
                </p>
            </div>
        </section>

        <!-- Contact details -->
        <form @submit.prevent="saveProfile" class="profile-section-card mb-6">
            <div class="profile-section-head">
                <div class="flex items-start gap-3">
                    <span class="profile-step-icon">✉️</span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Account</p>
                        <h2 class="text-lg font-bold text-slate-900">Contact details</h2>
                        <p class="mt-1 text-sm text-slate-500">Used for sign-in and portal notifications.</p>
                    </div>
                </div>
            </div>
            <div class="profile-section-body">
                <FormGrid>
                    <FormField label="Full name" required :error="profileForm.errors.name">
                        <input v-model="profileForm.name" type="text" class="field" required>
                    </FormField>
                    <FormField label="Email" required :error="profileForm.errors.email">
                        <input v-model="profileForm.email" type="email" class="field" required>
                    </FormField>
                    <FormField label="Phone" :error="profileForm.errors.phone">
                        <input v-model="profileForm.phone" type="text" class="field">
                    </FormField>
                    <FormField label="Designation" :error="profileForm.errors.designation">
                        <input v-model="profileForm.designation" type="text" class="field">
                    </FormField>
                </FormGrid>
                <FormActions>
                    <button type="submit" class="btn-primary" :disabled="profileForm.processing">
                        {{ profileForm.processing ? 'Saving…' : 'Save profile' }}
                    </button>
                    <span v-if="profileForm.recentlySuccessful" class="text-xs font-semibold text-emerald-700">Saved ✓</span>
                </FormActions>
            </div>
        </form>

        <!-- Password -->
        <form @submit.prevent="savePassword" class="profile-section-card">
            <div class="profile-section-head">
                <div class="flex items-start gap-3">
                    <span class="profile-step-icon">🔒</span>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Security</p>
                        <h2 class="text-lg font-bold text-slate-900">Change password</h2>
                        <p class="mt-1 text-sm text-slate-500">Choose a strong password you don't use elsewhere.</p>
                    </div>
                </div>
            </div>
            <div class="profile-section-body">
                <FormGrid>
                    <FormField label="Current password" required :error="passwordForm.errors.current_password" class-extra="sm:col-span-2">
                        <input v-model="passwordForm.current_password" type="password" class="field" required autocomplete="current-password">
                    </FormField>
                    <FormField label="New password" required :error="passwordForm.errors.password">
                        <input v-model="passwordForm.password" type="password" class="field" required autocomplete="new-password">
                    </FormField>
                    <FormField label="Confirm new password" required>
                        <input v-model="passwordForm.password_confirmation" type="password" class="field" required autocomplete="new-password">
                    </FormField>
                </FormGrid>
                <FormActions>
                    <button type="submit" class="btn-secondary" :disabled="passwordForm.processing">
                        {{ passwordForm.processing ? 'Updating…' : 'Update password' }}
                    </button>
                    <span v-if="passwordForm.recentlySuccessful" class="text-xs font-semibold text-emerald-700">Password updated ✓</span>
                </FormActions>
            </div>
        </form>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import FormField from '@/Components/ui/FormField.vue';
import FormGrid from '@/Components/ui/FormGrid.vue';
import FormActions from '@/Components/ui/FormActions.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
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
    phone:       props.teacher?.mobile ?? '',
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
