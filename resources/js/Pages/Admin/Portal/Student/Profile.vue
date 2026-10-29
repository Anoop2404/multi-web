<template>
    <PortalLayout
        role-label="Student Portal"
        title="My Profile &amp; Account Settings"
        :subtitle="`${school.name} · Student ID ${student.reg_no}`"
        accent="navy"
        :nav-items="navItems"
        :avatar-url="student.photo_url || ''"
    >
        <div class="max-w-3xl mx-auto space-y-6 py-2">
            <!-- Lock notification banner if locked -->
            <div v-if="studentEditLock?.locked" class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-xs sm:text-sm text-amber-900 space-y-1 shadow-xs">
                <p class="font-bold flex items-center gap-1.5 text-amber-950">
                    <span>🔒</span>
                    <span>Student Record Locked</span>
                </p>
                <p class="text-amber-800">
                    {{ studentEditLock.message ?? 'School coordinators manage official student names and registration numbers.' }}
                    Ask your school administrator to submit an official change request if changes are needed.
                </p>
            </div>

            <!-- Student Identity Card -->
            <section class="rounded-3xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="font-extrabold text-base text-slate-900">Student Identity</h2>
                    <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200">
                        Official Record
                    </span>
                </div>

                <div class="flex flex-col sm:flex-row items-start gap-5">
                    <div class="shrink-0 relative">
                        <img
                            v-if="student.photo_url"
                            :src="student.photo_url"
                            :alt="student.name"
                            class="h-20 w-20 sm:h-24 sm:w-24 rounded-2xl object-cover border-2 border-[#0f3d7a]/20 shadow-sm"
                        >
                        <div
                            v-else
                            class="h-20 w-20 sm:h-24 sm:w-24 rounded-2xl border border-dashed border-slate-300 bg-slate-50 flex flex-col items-center justify-center text-xs text-slate-400 p-2 text-center"
                        >
                            <span>👤</span>
                            <span class="text-[10px] mt-1 font-semibold">No photo</span>
                        </div>
                    </div>

                    <dl class="flex-1 grid gap-4 sm:grid-cols-2 text-xs sm:text-sm min-w-0">
                        <div class="rounded-xl bg-slate-50 p-3 border border-slate-100">
                            <dt class="text-slate-500 font-semibold">Full Name</dt>
                            <dd class="mt-0.5 font-extrabold text-slate-900 text-sm sm:text-base">{{ student.name }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3 border border-slate-100">
                            <dt class="text-slate-500 font-semibold">Student ID (Registration No.)</dt>
                            <dd class="mt-0.5 font-mono font-extrabold text-slate-900 text-sm sm:text-base">{{ student.reg_no }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3 border border-slate-100 sm:col-span-2">
                            <dt class="text-slate-500 font-semibold">School</dt>
                            <dd class="mt-0.5 font-bold text-slate-800">{{ school.name }}</dd>
                        </div>
                    </dl>
                </div>

                <p class="text-xs text-slate-500 leading-relaxed pt-1">
                    ℹ️ Student name and photos printed on certificates and hall tickets are verified by your school office. Contact your school coordinator for corrections.
                </p>
            </section>

            <!-- Editable Contact Details Form -->
            <form @submit.prevent="saveProfile" class="rounded-3xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-sm space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="font-extrabold text-base text-slate-900">Communication Contacts</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Where notifications and password reset links are sent</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField label="Email Address" required>
                        <input
                            v-model="profileForm.email"
                            type="email"
                            class="field"
                            required
                        >
                    </FormField>

                    <FormField label="Parent / Guardian Phone" hint="10-digit mobile number for SMS notifications">
                        <input
                            v-model="profileForm.parent_phone"
                            type="tel"
                            class="field"
                            placeholder="e.g. 9876543210"
                        >
                    </FormField>
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        class="btn-primary text-xs font-bold min-h-11 px-5"
                        :disabled="profileForm.processing"
                    >
                        {{ profileForm.processing ? 'Saving…' : 'Save contact details' }}
                    </button>
                </div>
            </form>

            <!-- Password Change Form -->
            <form @submit.prevent="savePassword" class="rounded-3xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-sm space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="font-extrabold text-base text-slate-900">Security &amp; Password</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Keep your student portal login secure</p>
                </div>

                <div class="space-y-3">
                    <FormField label="Current Password" required>
                        <input
                            v-model="passwordForm.current_password"
                            type="password"
                            class="field"
                            required
                            autocomplete="current-password"
                        >
                    </FormField>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <FormField label="New Password" hint="Minimum 8 characters" required>
                            <input
                                v-model="passwordForm.password"
                                type="password"
                                class="field"
                                required
                                autocomplete="new-password"
                            >
                        </FormField>

                        <FormField label="Confirm New Password" required>
                            <input
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                class="field"
                                required
                                autocomplete="new-password"
                            >
                        </FormField>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button
                        type="submit"
                        class="btn-secondary text-xs font-bold min-h-11 px-5"
                        :disabled="passwordForm.processing"
                    >
                        {{ passwordForm.processing ? 'Updating…' : 'Update password' }}
                    </button>
                </div>
            </form>
        </div>
    </PortalLayout>
</template>

<script setup>
import PortalLayout from '@/Layouts/PortalLayout.vue';
import FormField from '@/Components/ui/FormField.vue';
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
