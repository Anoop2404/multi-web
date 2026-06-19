<template>
    <SchoolAdminLayout title="Registration Details" :school="school">
        <div class="max-w-2xl space-y-6">
            <p class="text-sm text-gray-500">
                Update the details you submitted when joining this Sahodaya. School name, affiliation number, and school code
                are managed by your Sahodaya admin.
            </p>

            <div v-if="$page.props.flash?.success"
                 class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">
                {{ $page.props.flash.success }}
            </div>

            <!-- Read-only summary -->
            <section class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-3">
                <h3 class="font-bold text-gray-800">School Identity</h3>
                <dl class="grid sm:grid-cols-2 gap-3 text-sm">
                    <div v-for="field in readOnlyFields" :key="field.label">
                        <dt class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ field.label }}</dt>
                        <dd class="text-gray-800 mt-0.5 font-medium">{{ field.value }}</dd>
                    </div>
                </dl>
            </section>

            <!-- Registration details -->
            <form v-if="editableFields.length" @submit.prevent="saveProfile" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
                <h3 class="font-bold text-gray-800">Registration Details</h3>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div v-for="field in editableFields" :key="field.key"
                         :class="field.key === 'address' ? 'sm:col-span-2' : ''">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                            {{ field.label }}
                            <span v-if="field.required" class="text-red-500">*</span>
                        </label>

                        <select v-if="field.key === 'highest_class'"
                                v-model="profileForm[field.key]"
                                :required="field.required"
                                class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
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
                                  class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2 resize-none"></textarea>

                        <input v-else
                               v-model="profileForm[field.key]"
                               :type="fieldInputType(field.key)"
                               :required="field.required"
                               :placeholder="field.placeholder"
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">

                        <p v-if="profileForm.errors[field.key]" class="text-xs text-red-500 mt-1">
                            {{ profileForm.errors[field.key] }}
                        </p>
                    </div>
                </div>

                <button type="submit" :disabled="profileForm.processing"
                        class="bg-blue-600 text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-blue-700 disabled:opacity-50">
                    Save registration details
                </button>
            </form>

            <!-- Login account -->
            <form @submit.prevent="saveAccount" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
                <div>
                    <h3 class="font-bold text-gray-800">Login Account</h3>
                    <p class="text-xs text-gray-500 mt-1">
                        Gmail address used to sign in.
                        <span v-if="account.email_verified" class="text-green-600 font-medium">Verified</span>
                        <span v-else class="text-amber-600 font-medium">Not verified — check your inbox</span>
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Display name</label>
                    <input v-model="accountForm.name" type="text"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                    <p v-if="accountForm.errors.name" class="text-xs text-red-500 mt-1">{{ accountForm.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Gmail login email *</label>
                    <input v-model="accountForm.email" type="email" required
                           placeholder="your.school@gmail.com"
                           class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                    <p class="text-xs text-gray-400 mt-1">Must be a @gmail.com address. Changing email requires verification again.</p>
                    <p v-if="accountForm.errors.email" class="text-xs text-red-500 mt-1">{{ accountForm.errors.email }}</p>
                </div>

                <div class="border-t border-gray-100 pt-4 space-y-4">
                    <p class="text-xs font-semibold text-gray-600 uppercase tracking-wide">Change password</p>
                    <p class="text-xs text-gray-500">Leave blank to keep your current password.</p>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Current password</label>
                        <input v-model="accountForm.current_password" type="password" autocomplete="current-password"
                               class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        <p v-if="accountForm.errors.current_password" class="text-xs text-red-500 mt-1">
                            {{ accountForm.errors.current_password }}
                        </p>
                    </div>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">New password</label>
                            <input v-model="accountForm.password" type="password" autocomplete="new-password"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                            <p v-if="accountForm.errors.password" class="text-xs text-red-500 mt-1">
                                {{ accountForm.errors.password }}
                            </p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Confirm new password</label>
                            <input v-model="accountForm.password_confirmation" type="password" autocomplete="new-password"
                                   class="w-full border border-gray-200 rounded-lg px-4 py-2.5 text-sm focus:outline-none focus:ring-2">
                        </div>
                    </div>
                </div>

                <button type="submit" :disabled="accountForm.processing"
                        class="bg-blue-600 text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-blue-700 disabled:opacity-50">
                    Save login account
                </button>
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
    if (key === 'principal_email') return 'email';
    if (key === 'phone' || key === 'principal_phone') return 'tel';
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
