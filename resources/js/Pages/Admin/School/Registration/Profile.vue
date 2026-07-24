<template>
    <SchoolAdminLayout title="Registration Details" :school="school" :show-header-title="false">
        <div class="mx-auto max-w-5xl space-y-6">
            <MembershipWorkflowNav v-if="registration"
                                   :school="school"
                                   :profile="profile"
                                   :registration="registration"
                                   current="profile" />

            <!-- Hero + completion -->
            <div class="profile-hero">
                <div class="profile-hero-grid">
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-widest text-[#0f3d7a]/70">Registration profile</p>
                        <h1 class="mt-1 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ school.name }}</h1>
                        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-600">
                            Keep your school contact and leadership details up to date for Sahodaya membership and fest registrations.
                            Each section saves independently.
                        </p>
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span v-if="school.school_prefix"
                                  class="inline-flex items-center rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                Code · {{ school.school_prefix }}
                            </span>
                            <span v-if="school.is_non_affiliated"
                                  class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-900">
                                Non-affiliated school
                            </span>
                            <span v-else
                                  class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-900">
                                CBSE affiliated
                            </span>
                            <span class="profile-status-pill"
                                  :class="membershipApproved ? 'profile-status-pill--done' : 'profile-status-pill--pending'">
                                {{ membershipLabel }}
                            </span>
                        </div>
                    </div>
                    <div class="profile-completion-ring shrink-0" :style="{ '--pct': profileProgress }">
                        <div class="profile-completion-ring-inner">
                            <span class="profile-completion-value">{{ profileProgress }}%</span>
                            <span class="profile-completion-label">Complete</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 setup-progress" role="progressbar" :aria-valuenow="profileProgress" aria-valuemin="0" aria-valuemax="100">
                    <div class="setup-progress-bar" :style="{ width: `${profileProgress}%` }" />
                </div>
                <p class="mt-2 text-xs font-medium text-slate-500">
                    {{ completedSectionCount }}/{{ totalSectionCount }} sections complete
                </p>
            </div>

            <!-- Leadership checklist alert -->
            <div v-if="leadershipContacts && !leadershipContacts.complete"
                 class="rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50 to-orange-50/50 p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-amber-950">Leadership contacts needed</p>
                        <p class="mt-1 text-sm text-amber-900/85">
                            Fest registrations may be held until principal and events coordinator details are on file.
                            Scroll down to the <strong>Principal</strong> and <strong>Other leadership contacts</strong> sections.
                        </p>
                    </div>
                    <button type="button" class="btn-primary text-xs !py-2"
                            @click="scrollToSection(firstIncompleteSectionKey)">
                        Complete now →
                    </button>
                </div>
                <ul class="profile-checklist mt-4">
                    <li v-for="role in leadershipChecklist" :key="role.key"
                        class="profile-checklist-item"
                        :class="role.done ? 'profile-checklist-item--done' : ''">
                        <span>{{ role.done ? '✓' : '○' }}</span>
                        <div class="min-w-0 flex-1">
                            <span class="font-semibold">{{ role.label }}</span>
                            <span v-if="!role.done && role.missing?.length" class="block text-xs opacity-80">
                                Missing: {{ role.missing.join(', ') }}
                            </span>
                        </div>
                        <button v-if="!role.done" type="button"
                                class="shrink-0 text-xs font-semibold underline"
                                @click="scrollToSection(role.sectionKey)">
                            Fill in
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Section quick nav -->
            <div class="profile-step-grid">
                <button v-for="(section, index) in activeSections" :key="section.key"
                        type="button"
                        class="profile-step-card"
                        :class="[
                            isSectionComplete(section.key) ? 'profile-step-card--done' : 'profile-step-card--active',
                        ]"
                        @click="scrollToSection(section.key)">
                    <span class="profile-step-icon">{{ sectionIcon(section.key) }}</span>
                    <div class="min-w-0 text-left">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Step {{ index + 1 }}</p>
                        <p class="font-semibold text-slate-900">{{ section.title }}</p>
                        <p class="mt-0.5 text-xs"
                           :class="isSectionComplete(section.key) ? 'text-emerald-700' : 'text-amber-700'">
                            {{ isSectionComplete(section.key) ? 'Complete' : 'Needs attention' }}
                        </p>
                    </div>
                </button>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_280px]">
                <!-- Main forms -->
                <div class="space-y-6">
                    <form v-for="(section, index) in activeSections" :key="section.key"
                          :id="`section-${section.key}`"
                          @submit.prevent="saveSection(section.key)"
                          class="profile-section-card scroll-mt-24"
                          :class="isSectionComplete(section.key) ? 'profile-section-card--done' : 'profile-section-card--pending'">
                        <div class="profile-section-head">
                            <div class="flex items-start gap-3">
                                <span class="profile-step-icon">{{ sectionIcon(section.key) }}</span>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">
                                        Section {{ index + 1 }} of {{ activeSections.length }}
                                    </p>
                                    <h2 class="text-lg font-bold text-slate-900">{{ section.title }}</h2>
                                    <p v-if="section.hint" class="mt-1 text-sm text-slate-500">{{ section.hint }}</p>
                                </div>
                            </div>
                            <span class="profile-status-pill shrink-0"
                                  :class="isSectionComplete(section.key) ? 'profile-status-pill--done' : 'profile-status-pill--pending'">
                                {{ isSectionComplete(section.key) ? '✓ Complete' : 'Pending' }}
                            </span>
                        </div>

                        <div class="profile-section-body">
                            <template v-if="section.key === 'leadership'">
                                <div v-for="group in leadershipFieldGroups" :key="group.key" class="profile-field-group">
                                    <p class="profile-field-group-title">{{ group.label }}</p>
                                    <FormGrid>
                                        <FormField v-for="field in group.fields" :key="field.key"
                                                   :label="field.label"
                                                   :required="field.required"
                                                   :error="sectionForms[section.key].errors[field.key]"
                                                   :class-extra="fieldFullWidth(field.key) ? 'sm:col-span-2' : ''">
                                            <ProfileFieldInput
                                                :field="field"
                                                v-model="sectionForms[section.key][field.key]"
                                                :highest-class-options="highestClassOptions"
                                            />
                                    <p v-if="field.hint" class="mt-1 text-xs text-slate-500">{{ field.hint }}</p>
                                        </FormField>
                                    </FormGrid>
                                </div>
                            </template>

                            <FormGrid v-else-if="fieldsForSection(section.key).length">
                                <FormField v-for="field in fieldsForSection(section.key)" :key="field.key"
                                           :label="field.label"
                                           :required="field.required"
                                           :error="sectionForms[section.key].errors[field.key]"
                                           :class-extra="fieldFullWidth(field.key) ? 'sm:col-span-2' : ''">
                                    <ProfileFieldInput
                                        :field="field"
                                        v-model="sectionForms[section.key][field.key]"
                                        :highest-class-options="highestClassOptions"
                                    />
                                    <p v-if="field.hint" class="mt-1 text-xs text-slate-500">{{ field.hint }}</p>
                                </FormField>
                            </FormGrid>

                            <FormActions>
                                <button type="submit"
                                        :disabled="sectionForms[section.key].processing"
                                        class="btn-primary">
                                    {{ sectionForms[section.key].processing ? 'Saving…' : saveLabel(section.key) }}
                                </button>
                            </FormActions>
                        </div>
                    </form>

                    <!-- Login account -->
                    <form id="section-account" @submit.prevent="saveAccount"
                          class="profile-section-card scroll-mt-24">
                        <div class="profile-section-head">
                            <div class="flex items-start gap-3">
                                <span class="profile-step-icon">🔐</span>
                                <div>
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Account</p>
                                    <h2 class="text-lg font-bold text-slate-900">Login account</h2>
                                    <p class="mt-1 text-sm text-slate-500">Gmail address used to sign in to this portal.</p>
                                </div>
                            </div>
                            <span class="profile-status-pill"
                                  :class="account.email_verified ? 'profile-status-pill--done' : 'profile-status-pill--pending'">
                                {{ account.email_verified ? '✓ Verified' : 'Email pending' }}
                            </span>
                        </div>

                        <div class="profile-section-body space-y-6">
                            <FormGrid>
                                <FormField label="Display name" :error="accountForm.errors.name" class-extra="sm:col-span-2">
                                    <input v-model="accountForm.name" type="text" class="field">
                                </FormField>
                                <FormField label="Login email" required :error="accountForm.errors.email" class-extra="sm:col-span-2"
                                           hint="Must be a valid email address. Changing email requires verification again.">
                                    <input v-model="accountForm.email" type="email" required placeholder="your.school@example.com" class="field">
                                </FormField>
                            </FormGrid>

                            <div class="profile-field-group">
                                <p class="profile-field-group-title">Change password</p>
                                <p class="mb-4 text-sm text-slate-500">Leave blank to keep your current password.</p>
                                <FormGrid>
                                    <FormField label="Current password" :error="accountForm.errors.current_password" class-extra="sm:col-span-2">
                                        <input v-model="accountForm.current_password" type="password" autocomplete="current-password" class="field">
                                    </FormField>
                                    <FormField label="New password" :error="accountForm.errors.password">
                                        <input v-model="accountForm.password" type="password" autocomplete="new-password" class="field">
                                    </FormField>
                                    <FormField label="Confirm new password">
                                        <input v-model="accountForm.password_confirmation" type="password" autocomplete="new-password" class="field">
                                    </FormField>
                                </FormGrid>
                            </div>

                            <FormActions>
                                <button type="submit" :disabled="accountForm.processing" class="btn-primary">
                                    {{ accountForm.processing ? 'Saving…' : 'Save login account' }}
                                </button>
                            </FormActions>
                        </div>
                    </form>
                </div>

                <!-- Sidebar -->
                <aside class="space-y-4 lg:sticky lg:top-6 lg:self-start">
                    <div class="profile-sidebar-card">
                        <h3 class="text-sm font-bold text-slate-900">School identity</h3>
                        <p class="mt-1 text-xs text-slate-500">Key identifiers and membership status</p>
                        <dl class="mt-4">
                            <div v-for="field in readOnlyFields" :key="field.label" class="profile-identity-item">
                                <dt class="text-[10px] font-bold uppercase tracking-wide text-slate-400">{{ field.label }}</dt>
                                <dd class="text-sm font-semibold text-slate-900">{{ field.value }}</dd>
                            </div>
                        </dl>
                    </div>

                    <nav class="profile-sidebar-card">
                        <h3 class="mb-3 text-sm font-bold text-slate-900">On this page</h3>
                        <button v-for="section in activeSections" :key="section.key"
                                type="button"
                                class="profile-nav-link"
                                @click="scrollToSection(section.key)">
                            <span>{{ sectionIcon(section.key) }}</span>
                            <span class="min-w-0 flex-1 truncate">{{ section.title }}</span>
                            <span class="text-xs"
                                  :class="isSectionComplete(section.key) ? 'text-emerald-600' : 'text-amber-600'">
                                {{ isSectionComplete(section.key) ? '✓' : '!' }}
                            </span>
                        </button>
                        <button type="button" class="profile-nav-link" @click="scrollToSection('account')">
                            <span>🔐</span>
                            <span class="flex-1">Login account</span>
                        </button>
                    </nav>

                    <div class="rounded-xl border border-[#dbeafe] bg-[#f0f9ff] p-4 text-xs leading-relaxed text-[#0f3d7a]">
                        <p class="font-semibold">Tip</p>
                        <p class="mt-1 opacity-90">
                            Save each section after editing. Principal and events coordinator are required before fest registrations can proceed. Vice principal is optional.
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import ProfileFieldInput from '@/Components/school/ProfileFieldInput.vue';
import MembershipWorkflowNav from '@/Components/school/MembershipWorkflowNav.vue';
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    school:              Object,
    profileData:         { type: Object, default: () => ({}) },
    profile:             { type: Object, default: null },
    registration:        { type: Object, default: null },
    editableFields:      { type: Array, default: () => [] },
    profileSections:     { type: Array, default: () => [] },
    readOnlyFields:      { type: Array, default: () => [] },
    highestClassOptions: { type: Object, default: () => ({}) },
    account:             { type: Object, default: () => ({}) },
    leadershipContacts:  { type: Object, default: null },
});

const SECTION_GROUPS = {
    school: ['school'],
    principal: ['principal'],
    leadership: ['leadership'],
};

const SECTION_ICONS = {
    school: '🏫',
    principal: '👤',
    leadership: '👥',
};

const LEADERSHIP_GROUPS = [
    { key: 'vice_principal', label: 'Vice principal (optional)', prefix: 'vice_principal_', optional: true },
    { key: 'event_coordinator', label: 'Events coordinator', prefix: 'event_coordinator_', optional: false },
];

function fieldsForSection(sectionKey) {
    const groups = SECTION_GROUPS[sectionKey] ?? [];
    return props.editableFields.filter(f => groups.includes(f.group));
}

const activeSections = computed(() =>
    props.profileSections.filter(s => fieldsForSection(s.key).length > 0),
);

const leadershipFieldGroups = computed(() =>
    LEADERSHIP_GROUPS.map(group => ({
        ...group,
        fields: fieldsForSection('leadership').filter(f => f.key.startsWith(group.prefix)),
    })).filter(g => g.fields.length > 0),
);

function fieldFilled(key) {
    const value = props.profileData[key];
    return value !== null && value !== undefined && String(value).trim() !== '';
}

function isSectionComplete(sectionKey) {
    const fields = fieldsForSection(sectionKey);
    if (!fields.length) return true;
    const required = fields.filter(f => f.required);
    const toCheck = required.length ? required : fields;
    return toCheck.every(f => fieldFilled(f.key));
}

const completedSectionCount = computed(() =>
    activeSections.value.filter(s => isSectionComplete(s.key)).length,
);

const totalSectionCount = computed(() => activeSections.value.length);

const profileProgress = computed(() =>
    totalSectionCount.value
        ? Math.round((completedSectionCount.value / totalSectionCount.value) * 100)
        : 100,
);

const firstIncompleteSectionKey = computed(() =>
    activeSections.value.find(s => !isSectionComplete(s.key))?.key ?? 'leadership',
);

const membershipLabel = computed(() => {
    const status = props.readOnlyFields.find(f => f.label === 'Membership Status')?.value ?? 'Pending';
    return status;
});

const membershipApproved = computed(() =>
    String(membershipLabel.value).toLowerCase() === 'approved',
);

const leadershipChecklist = computed(() => {
    const roles = props.leadershipContacts?.pending ?? [];
    const requiredRoles = [
        { key: 'principal', label: 'Principal', sectionKey: 'principal' },
        { key: 'event_coordinator', label: 'Events Coordinator', sectionKey: 'leadership' },
    ];

    return requiredRoles.map(role => {
        const pending = roles.find(r => r.key === role.key);
        return {
            ...role,
            done: !pending,
            missing: pending?.missing ?? [],
        };
    });
});

function sectionIcon(key) {
    return SECTION_ICONS[key] ?? '📋';
}

function fieldFullWidth(key) {
    return key === 'address';
}

function sectionInitialData(sectionKey) {
    const data = { section: sectionKey };
    for (const field of fieldsForSection(sectionKey)) {
        data[field.key] = props.profileData[field.key] ?? '';
    }
    return data;
}

const sectionForms = {
    school: useForm(sectionInitialData('school')),
    principal: useForm(sectionInitialData('principal')),
    leadership: useForm(sectionInitialData('leadership')),
};

const accountForm = useForm({
    name:                  props.account.name ?? '',
    email:                 props.account.email ?? '',
    current_password:      '',
    password:              '',
    password_confirmation: '',
});

function saveLabel(sectionKey) {
    return {
        school: 'Save school contact',
        principal: 'Save principal details',
        leadership: 'Save leadership contacts',
    }[sectionKey] ?? 'Save';
}

function saveSection(sectionKey) {
    sectionForms[sectionKey].put(`/school-admin/${props.school.id}/registration/profile`);
}

function saveAccount() {
    accountForm.put(`/school-admin/${props.school.id}/registration/account`, {
        onSuccess: () => {
            accountForm.current_password = '';
            accountForm.password = '';
            accountForm.password_confirmation = '';
        },
    });
}

function scrollToSection(key) {
    const el = document.getElementById(`section-${key}`);
    el?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
</script>
