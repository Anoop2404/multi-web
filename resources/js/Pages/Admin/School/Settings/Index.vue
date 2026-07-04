<template>
    <SchoolAdminLayout title="School Settings" :school="school" :show-header-title="false">
        <PageHeader title="School Settings" eyebrow="School"
            description="School profile and configuration." />


        <div class="max-w-2xl space-y-6">
            <div v-if="!school.school_prefix" class="notice-banner notice-banner--warning text-sm">
                <p class="font-semibold">Set your school code first</p>
                <p class="mt-1">
                    Annual registration and student numbering require a school code.
                    <Link :href="`/school-admin/${school.id}/setup/code`" class="font-semibold underline ml-1">Set school code →</Link>
                </p>
            </div>

            <div class="notice-banner notice-banner--info text-sm">
                <p class="font-semibold">Before annual registration</p>
                <p class="mt-1">Complete your school code, contact details, and student records. Phone and email appear on receipts and your public school profile.</p>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Logo & Branding -->
                <div class="card space-y-5">
                    <h3 class="font-bold text-gray-800">Logo & Identity</h3>

                    <div v-if="settings.logo">
                        <p class="text-xs font-semibold text-gray-600 mb-2">Current Logo</p>
                        <img :src="settings.logo" class="h-16 object-contain rounded border border-gray-100 bg-gray-50 px-3">
                    </div>

                    <div>
                        <label class="form-label mb-1.5">{{ settings.logo ? 'Replace Logo' : 'Upload Logo' }}</label>
                        <input type="file" accept="image/*" @change="form.logo = $event.target.files[0]"
                               class="w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                        <p class="text-xs text-gray-400 mt-1">PNG or SVG recommended. Shown in navbar and footer.</p>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="card space-y-4">
                    <h3 class="font-bold text-gray-800">Contact Information</h3>

                    <div class="grid sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label mb-1.5">Phone <span class="text-red-500">*</span></label>
                            <input v-model="form.phone" type="tel" placeholder="+91 484 123 4567"
                                   class="field" required>
                        </div>
                        <div>
                            <label class="form-label mb-1.5">Email <span class="text-red-500">*</span></label>
                            <input v-model="form.email" type="email"
                                   class="field" required>
                        </div>
                    </div>

                    <div>
                        <label class="form-label mb-1.5">Address <span class="text-red-500">*</span></label>
                        <textarea v-model="form.address" rows="3"
                                  class="field resize-none"></textarea>
                    </div>

                    <div>
                        <label class="form-label mb-1.5">City / Town</label>
                        <input v-model="form.address_city" type="text" placeholder="Thrissur"
                               class="field">
                    </div>
                </div>

                <!-- Social Media -->
                <div class="card space-y-4">
                    <h3 class="font-bold text-gray-800">Social Media Links</h3>

                    <div v-for="platform in ['facebook','youtube','instagram']" :key="platform">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5 capitalize">{{ platform }}</label>
                        <input v-model="form[platform]" type="url"
                               :placeholder="`https://www.${platform}.com/yourpage`"
                               class="field font-mono">
                    </div>
                </div>

                <!-- SEO -->
                <div class="card space-y-4">
                    <h3 class="font-bold text-gray-800">SEO & Search</h3>
                    <p class="text-xs text-gray-400 -mt-1">Controls how your school appears in Google and social media previews.</p>

                    <div>
                        <label class="form-label mb-1.5">
                            Page Title <span class="font-normal text-gray-400">(max 70 chars)</span>
                        </label>
                        <input v-model="form.seo_title" type="text" maxlength="70"
                               :placeholder="school.name"
                               class="field">
                        <p class="text-xs text-gray-400 mt-1">{{ (form.seo_title || '').length }}/70 chars</p>
                    </div>

                    <div>
                        <label class="form-label mb-1.5">
                            Meta Description <span class="font-normal text-gray-400">(max 160 chars)</span>
                        </label>
                        <textarea v-model="form.seo_description" rows="2" maxlength="160"
                                  placeholder="A brief description of your school for search engines..."
                                  class="field resize-none"></textarea>
                        <p class="text-xs text-gray-400 mt-1">{{ (form.seo_description || '').length }}/160 chars</p>
                    </div>

                    <div>
                        <label class="form-label mb-1.5">Tagline</label>
                        <input v-model="form.seo_tagline" type="text" maxlength="200"
                               placeholder="Excellence in Education since 1985"
                               class="field">
                    </div>

                    <div>
                        <label class="form-label mb-1.5">
                            Keywords <span class="font-normal text-gray-400">(comma-separated)</span>
                        </label>
                        <input v-model="form.seo_keywords" type="text"
                               placeholder="CBSE school Thrissur, Kerala school admissions, best school Kerala"
                               class="field">
                    </div>
                </div>

                <!-- Language -->
                <div class="card space-y-4">
                    <h3 class="font-bold text-gray-800">Site Language</h3>
                    <p class="text-xs text-gray-400 -mt-1">Sets the HTML lang attribute for accessibility and search engines.</p>
                    <div>
                        <label class="form-label mb-1.5">Public site language</label>
                        <select v-model="form.locale"
                                class="field">
                            <option value="en">English</option>
                            <option value="ml">Malayalam</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                    <button type="submit" :disabled="form.processing"
                            class="btn-primary disabled:opacity-50">
                        Save Settings
                    </button>
                    <span v-if="$page.props.flash?.success" class="text-sm text-green-600 font-medium">✓ Saved!</span>
                </div>
            </form>
        </div>
    </SchoolAdminLayout>
</template>

<script setup>
import SchoolAdminLayout from '@/Layouts/SchoolAdminLayout.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    school:   Object,
    settings: { type: Object, default: () => ({}) },
});

const contact = props.settings.contact ?? {};

const seo = props.settings.seo ?? {};

const form = useForm({
    phone:           contact.phone ?? '',
    email:           contact.email ?? '',
    address:         contact.address ?? '',
    address_city:    props.settings.address_city ?? '',
    facebook:        props.settings.widgets?.social_links?.facebook ?? '',
    youtube:         props.settings.widgets?.social_links?.youtube ?? '',
    instagram:       props.settings.widgets?.social_links?.instagram ?? '',
    logo:            null,
    seo_title:       seo.title ?? '',
    seo_description: seo.description ?? '',
    seo_tagline:     seo.tagline ?? '',
    seo_keywords:    seo.keywords ?? '',
    locale:          props.settings.locale ?? 'en',
});

function submit() {
    form.post(`/school-admin/${props.school.id}/settings`, { forceFormData: true });
}
</script>
