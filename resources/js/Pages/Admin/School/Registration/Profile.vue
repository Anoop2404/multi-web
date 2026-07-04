<template>
    <SchoolAdminLayout title="Registration Details" :school="school" :show-header-title="false">
        <PageHeader
            title="Registration details"
            eyebrow="Profile"
            description="Update the details you submitted when joining this Sahodaya. School name, affiliation number, and school code are managed by your Sahodaya admin."
        />

        <div v-if="leadershipContacts && !leadershipContacts.complete" class="notice-banner notice-banner--warning mb-6 max-w-2xl">
            <p class="font-semibold text-amber-900">Leadership contacts pending</p>
            <p class="text-sm mt-1 text-amber-900/90">
                Complete details for:
                <span v-for="(item, i) in leadershipContacts.pending" :key="item.key">
                    {{ item.label }}<span v-if="i < leadershipContacts.pending.length - 1">, </span>
                </span>.
                Sahodaya may hold fest registrations until principal, vice principal, and events coordinator are on file.
            </p>
        </div>

        <div class="max-w-2xl space-y-6">
            <section class="card space-y-3">
                <h3 class="section-title text-base">School identity</h3>
                <dl class="grid gap-3 sm:grid-cols-2 text-sm">
                    <div v-for="field in readOnlyFields" :key="field.label">
                        <dt class="form-label text-slate-500">{{ field.label }}</dt>
                        <dd class="mt-1 font-medium text-slate-900">{{ field.value }}</dd>
                    </div>
                </dl>
            </section>

            <form v-if="editableFields.length" @submit.prevent="saveProfile" class="space-y-5">
                <FormSection title="Registration details" hint="Contact and school information visible to Sahodaya admins.">
                    <FormGrid>
                        <FormField v-for="field in editableFields" :key="field.key"
                                   :label="field.label"
                                   :required="field.required"
                                   :error="profileForm.errors[field.key]"
                                   :class-extra="field.key === 'address' ? 'sm:col-span-2' : ''">
                            <select v-if="field.key === 'highest_class'"
                                    v-model="profileForm[field.key]"
                                    :required="field.required"
                                    class="field">
                                <option value="">—</option>
                                <option v-for="(label, value) in highestClassOptions" :key="value" :value="value">
                                    {{ label }}
                                </option>
                            </select>
                            <textarea v-else-if="field.key === 'address'"
                                      v-model="profileForm[field.key]"
                                      rows="3"
                                      :required="field.required"
                                      :placeholder="field.placeholder"
                                      class="field resize-none"></textarea>
                            <input v-else
                                   v-model="profileForm[field.key]"
                                   :type="fieldInputType(field.key)"
                                   :required="field.required"
                                   :placeholder="field.placeholder"
                                   class="field">
                        </FormField>
                    </FormGrid>
                    <FormActions>
                        <button type="submit" :disabled="profileForm.processing" class="btn-primary">
                            {{ profileForm.processing ? 'Saving…' : 'Save registration details' }}
                        </button>
                    </FormActions>
                </FormSection>
            </form>

            <form @submit.prevent="saveAccount" class="space-y-5">
                <FormSection title="Login account" hint="Gmail address used to sign in to this portal.">
                    <p class="text-xs text-slate-500 mb-4">
                        <span v-if="account.email_verified" class="font-medium text-emerald-600">Email verified</span>
                        <span v-else class="font-medium text-amber-600">Not verified — check your inbox</span>
                    </p>
                    <FormGrid>
                        <FormField label="Display name" :error="accountForm.errors.name" class-extra="sm:col-span-2">
                            <input v-model="accountForm.name" type="text" class="field">
                        </FormField>
                        <FormField label="Gmail login email" required :error="accountForm.errors.email" class-extra="sm:col-span-2"
                                   hint="Must be a @gmail.com address. Changing email requires verification again.">
                            <input v-model="accountForm.email" type="email" required placeholder="your.school@gmail.com" class="field">
                        </FormField>
                    </FormGrid>

                    <div class="mt-6 border-t border-slate-100 pt-5 space-y-4">
                        <p class="section-title">Change password</p>
                        <p class="section-desc">Leave blank to keep your current password.</p>
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
                </FormSection>
            </form>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    school:              Object,
    profileData:         { type: Object, default: () => ({}) },
    editableFields:      { type: Array, default: () => [] },
    readOnlyFields:      { type: Array, default: () => [] },
    highestClassOptions: { type: Object, default: () => ({}) },
    account:             { type: Object, default: () => ({}) },
    leadershipContacts:  { type: Object, default: null },
});

const profileForm = useForm({ ...props.profileData });

const accountForm = useForm({
    name:                 props.account.name ?? '',
    email:                props.account.email ?? '',
    current_password:     '',
    password:             '',
    password_confirmation: '',
});

function fieldInputType(key) {
    if (key.endsWith('_email')) return 'email';
    if (key.endsWith('_phone') || key === 'phone') return 'tel';
    if (key === 'website') return 'url';
    return 'text';
}

function saveProfile() {
    profileForm.put(`/school-admin/${props.school.id}/registration/profile`);
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
</script>
