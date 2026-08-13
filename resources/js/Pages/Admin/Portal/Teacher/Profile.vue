<template>
    <PortalLayout
        role-label="Teacher Portal"
        title="My Profile & Security"
        :subtitle="school.name"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="teacher?.photo_url"
        show-avatar-placeholder
    >
        <!-- Identity Summary Card -->
        <section class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm mb-6">
            <div class="flex items-start gap-4 border-b border-slate-100 pb-4 mb-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#eff6ff] text-[#0f3d7a] text-lg font-bold">
                    👤
                </span>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Teacher Record & Credentials</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Maintained by your school administration & Sahodaya board.</p>
                </div>
            </div>

            <div v-if="teacher" class="flex flex-col sm:flex-row items-start gap-6">
                <div class="shrink-0 relative">
                    <img
                        v-if="teacher.photo_url"
                        :src="teacher.photo_url"
                        :alt="teacher.name"
                        class="h-24 w-24 rounded-2xl object-cover border-2 border-white shadow-md ring-2 ring-[#0f3d7a]/20"
                    >
                    <div
                        v-else
                        class="h-24 w-24 rounded-2xl border-2 border-dashed border-slate-300 bg-slate-50 flex items-center justify-center text-xs text-slate-400 text-center px-2 font-medium"
                    >
                        No Photo
                    </div>
                </div>

                <dl class="flex-1 grid gap-4 sm:grid-cols-2 text-xs min-w-0 bg-slate-50/80 p-4 rounded-2xl border border-slate-100">
                    <div>
                        <dt class="font-semibold text-slate-500 uppercase tracking-wider text-[10px]">Full Name</dt>
                        <dd class="mt-1 font-bold text-slate-900 text-sm">{{ teacher.name }}</dd>
                    </div>
                    <div v-if="teacher.reg_no">
                        <dt class="font-semibold text-slate-500 uppercase tracking-wider text-[10px]">Register Number</dt>
                        <dd class="mt-1 font-mono font-bold text-[#0f3d7a] text-sm">{{ teacher.reg_no }}</dd>
                    </div>
                    <div v-if="teacher.designation">
                        <dt class="font-semibold text-slate-500 uppercase tracking-wider text-[10px]">Designation</dt>
                        <dd class="mt-1 font-semibold text-slate-900 text-sm">{{ teacher.designation }}</dd>
                    </div>
                    <div v-if="teacher.subject">
                        <dt class="font-semibold text-slate-500 uppercase tracking-wider text-[10px]">Primary Subject</dt>
                        <dd class="mt-1 font-semibold text-slate-900 text-sm">{{ teacher.subject }}</dd>
                    </div>
                </dl>
            </div>
            <EmptyState v-else title="No teacher record linked" description="Ask your school administrator to link your account to a teacher record." icon="👤" />
            <p v-if="teacher && !teacher.photo_url" class="text-xs text-slate-400 mt-4 leading-relaxed">
                ℹ️ Profile photo is managed by your school admin from the Sahodaya School Portal.
            </p>
        </section>

        <!-- Contact details -->
        <form @submit.prevent="saveProfile" class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm mb-6 space-y-4">
            <div class="flex items-start gap-4 border-b border-slate-100 pb-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-[#0f3d7a] text-lg font-bold">
                    ✉️
                </span>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Account & Contact Details</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Used for portal sign-in and automated notifications.</p>
                </div>
            </div>

            <FormGrid>
                <FormField label="Full Name" required :error="profileForm.errors.name">
                    <input v-model="profileForm.name" type="text" class="field" required>
                </FormField>
                <FormField label="Email Address" required :error="profileForm.errors.email">
                    <input v-model="profileForm.email" type="email" class="field" required>
                </FormField>
                <FormField label="Phone Number" :error="profileForm.errors.phone">
                    <input v-model="profileForm.phone" type="text" class="field" placeholder="+91">
                </FormField>
                <FormField label="Designation" :error="profileForm.errors.designation">
                    <input v-model="profileForm.designation" type="text" class="field">
                </FormField>
            </FormGrid>
            <FormActions>
                <button type="submit" class="btn-primary text-xs !min-h-0 !py-2.5 px-5 shadow-sm" :disabled="profileForm.processing">
                    {{ profileForm.processing ? 'Saving…' : 'Save Profile Changes' }}
                </button>
                <span v-if="profileForm.recentlySuccessful" class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                    ✓ Saved successfully
                </span>
            </FormActions>
        </form>

        <!-- Security & Password -->
        <form @submit.prevent="savePassword" class="card rounded-2xl border border-slate-200/80 bg-white p-6 shadow-sm space-y-4">
            <div class="flex items-start gap-4 border-b border-slate-100 pb-4">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-900 text-lg font-bold">
                    🔒
                </span>
                <div>
                    <h2 class="text-base font-bold text-slate-900">Security & Password</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Update your password regularly to protect your teacher portal account.</p>
                </div>
            </div>

            <FormGrid>
                <FormField label="Current Password" required :error="passwordForm.errors.current_password" class-extra="sm:col-span-2">
                    <input v-model="passwordForm.current_password" type="password" class="field" required autocomplete="current-password">
                </FormField>
                <FormField label="New Password" required :error="passwordForm.errors.password">
                    <input v-model="passwordForm.password" type="password" class="field" required autocomplete="new-password">
                </FormField>
                <FormField label="Confirm New Password" required>
                    <input v-model="passwordForm.password_confirmation" type="password" class="field" required autocomplete="new-password">
                </FormField>
            </FormGrid>
            <FormActions>
                <button type="submit" class="btn-primary text-xs !min-h-0 !py-2.5 px-5 shadow-sm" :disabled="passwordForm.processing">
                    {{ passwordForm.processing ? 'Updating…' : 'Update Password' }}
                </button>
                <span v-if="passwordForm.recentlySuccessful" class="text-xs font-bold text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-lg border border-emerald-200">
                    ✓ Password updated successfully
                </span>
            </FormActions>
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

